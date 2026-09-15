<?php

namespace Tests\Integration;

use App\Jobs\SyncStagingRecordJob;
use App\Models\NeoFeederConnection;
use App\Models\SyncAttempt;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class DeliveryConcurrencyTest extends TestCase
{
    use CreatesSyncWorkspace;

    private array $processes = [];

    private string $simulator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('testing', app()->environment());
        $this->assertSame('mysql', config('database.default'));
        $this->assertStringStartsWith('bridge_integration_', config('database.connections.mysql.database'));
        $this->assertSame('redis', config('cache.default'));
        $this->assertSame('redis', config('queue.default'));
        $this->simulator = 'http://127.0.0.1:'.getenv('SIMULATOR_PORT');
        Http::post($this->simulator.'/scenario', ['mode' => 'success'])->throw();
    }

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            if ($process->isRunning()) {
                $process->stop(0);
            }
        }
        parent::tearDown();
    }

    private function fixture(): array
    {
        [$batch, $row, $user, $token] = $this->syncWorkspace();
        NeoFeederConnection::create([
            'tenant_id' => $batch->tenant_id, 'status' => 'active',
            'base_url' => $this->simulator.'/ws/live2.php', 'username' => 'synthetic',
            'encrypted_password' => Crypt::encryptString('synthetic-password'),
        ]);
        $this->approveSyncBatch($batch, $token);

        return [$batch, $row, $user, $token];
    }

    private function start(array $command, array $env = []): Process
    {
        $process = new Process($command, base_path(), $env, null, 30);
        $process->start();
        $this->processes[] = $process;

        return $process;
    }

    private function until(callable $condition): void
    {
        $deadline = microtime(true) + 15;
        do {
            if ($condition()) {
                return;
            }
            usleep(20000);
        } while (microtime(true) < $deadline);
        $this->fail('Integration condition timed out. '.implode('\n', array_map(fn ($p) => $p->getOutput().$p->getErrorOutput(), $this->processes)));
    }

    private function race(string $path, string $token): void
    {
        $barrier = 'integration:'.Str::uuid();
        Cache::put($barrier.':ready', 0, 30);
        $env = ['INTEGRATION_BARRIER' => $barrier, 'INTEGRATION_PATH' => $path, 'INTEGRATION_TOKEN' => $token];
        $a = $this->start([PHP_BINARY, 'tests/Support/concurrent-request.php'], $env);
        $b = $this->start([PHP_BINARY, 'tests/Support/concurrent-request.php'], $env);
        $this->until(fn () => (int) Cache::get($barrier.':ready') === 2);
        Cache::put($barrier.':go', true, 30);
        foreach ([$a, $b] as $process) {
            $this->assertSame(0, $process->wait(), $process->getOutput().$process->getErrorOutput());
        }
    }

    private function worker(string $queue = 'default'): Process
    {
        return $this->start([PHP_BINARY, 'artisan', 'queue:work', 'redis', '--queue='.$queue, '--once', '--sleep=0', '--tries=1']);
    }

    private function writes(): int
    {
        return collect(Http::get($this->simulator.'/state')->throw()->json('requests'))
            ->where('act', 'InsertBiodataMahasiswa')->count();
    }

    public function test_concurrent_start_and_two_workers_deliver_exactly_once(): void
    {
        [$batch, $row, , $token] = $this->fixture();
        $this->race('/api/import-batches/'.$batch->id.'/sync', $token);
        $this->assertSame(1, $row->syncAttempts()->count());
        $workers = [$this->worker(), $this->worker()];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait(), $worker->getOutput().$worker->getErrorOutput());
        }
        $this->assertSame('synced', $batch->refresh()->status);
        $this->assertSame('success', $row->refresh()->status);
        $this->assertSame(1, $this->writes());
        $this->assertSame(1, $row->syncAttempts()->firstOrFail()->execution_count);
    }

    public function test_concurrent_manual_retry_reuses_one_intent(): void
    {
        [$batch, $row, , $token] = $this->fixture();
        Http::post($this->simulator.'/scenario', ['mode' => 'rejected'])->throw();
        $this->withToken($token)->postJson('/api/import-batches/'.$batch->id.'/sync')->assertOk();
        $this->assertSame(0, $this->worker()->wait());
        $attempt = $row->syncAttempts()->firstOrFail();
        $this->assertSame('failed', $attempt->status);
        $this->race('/api/sync-attempts/'.$attempt->id.'/retry', $token);
        $this->assertSame(1, SyncAttempt::where('retry_of', $attempt->id)->count());
        Http::post($this->simulator.'/scenario', ['mode' => 'success'])->throw();
        $workers = [$this->worker(), $this->worker()];
        foreach ($workers as $worker) {
            $this->assertSame(0, $worker->wait());
        }
        $this->assertSame(1, $this->writes());
        $this->assertSame('synced', $batch->refresh()->status);
    }

    public function test_http_timeout_stays_unknown_and_cannot_be_retried(): void
    {
        [$batch, $row, , $token] = $this->fixture();
        Http::post($this->simulator.'/scenario', ['mode' => 'timeout'])->throw();
        $this->withToken($token)->postJson('/api/import-batches/'.$batch->id.'/sync')->assertOk();
        $worker = $this->start([PHP_BINARY, 'artisan', 'queue:work', 'redis', '--once', '--sleep=0'], ['NEOFEEDER_DEFAULT_TIMEOUT_MS' => '1000']);
        $this->assertSame(0, $worker->wait());
        $attempt = $row->syncAttempts()->firstOrFail();
        $this->assertSame('unknown', $attempt->status);
        $this->assertFalse($attempt->retry_safe);
        $this->withToken($token)->postJson('/api/sync-attempts/'.$attempt->id.'/retry')->assertConflict();
        SyncStagingRecordJob::dispatch($attempt->id);
        $this->assertSame(0, $this->worker()->wait());
        $this->assertSame(1, $this->writes());
    }

    public function test_killed_worker_after_post_recovers_without_resending(): void
    {
        [$batch, $row, , $token] = $this->fixture();
        Http::post($this->simulator.'/scenario', ['mode' => 'kill'])->throw();
        // Dedicated queue: the killed worker leaves a reserved job until retry_after.
        $queue = Queue::getFacadeRoot();
        Queue::fake();
        $this->withToken($token)->postJson('/api/import-batches/'.$batch->id.'/sync')->assertOk();
        $attempt = $row->syncAttempts()->firstOrFail();
        Queue::swap($queue);
        SyncStagingRecordJob::dispatch($attempt->id)->onQueue('killed');
        $worker = $this->worker('killed');
        $this->until(fn () => $this->writes() === 1);
        $worker->stop(0);
        $attempt->refresh();
        $this->assertNotNull($attempt->request_started_at);
        $attempt->forceFill(['attempted_at' => now()->subMinutes(11)])->save();
        $this->artisan('bridge:recover-sync')->assertSuccessful();
        $this->assertSame('unknown', $attempt->refresh()->status);
        $this->assertFalse($attempt->retry_safe);
        $this->withToken($token)->postJson('/api/sync-attempts/'.$attempt->id.'/retry')->assertConflict();
        SyncStagingRecordJob::dispatch($attempt->id);
        $this->assertSame(0, $this->worker()->wait());
        $this->assertSame(1, $this->writes());
    }
}
