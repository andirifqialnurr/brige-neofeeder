<?php

namespace Tests\Feature;

use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Mockery;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_only_admin_can_inspect_global_runtime(): void
    {
        [, , , $token] = $this->syncWorkspace();
        $this->getJson('/api/operations/health')->assertUnauthorized();
        $this->withToken($token)->getJson('/api/operations/health')->assertForbidden();
    }

    public function test_missing_stale_heartbeats_and_failed_jobs_are_reported_without_payloads(): void
    {
        [, , $user, $token] = $this->syncWorkspace();
        $user->update(['role' => 'admin']);
        config(['cache.stores.redis' => ['driver' => 'array']]);
        Redis::shouldReceive('connection->ping')->andReturn('PONG');
        $connection = Mockery::mock();
        $connection->shouldReceive('size')->with('default')->andReturn(3);
        Queue::shouldReceive('connection')->with('redis')->andReturn($connection);
        Cache::store('redis')->put('operations:scheduler', now()->subMinutes(4)->timestamp);
        DB::table('failed_jobs')->insert(['uuid' => 'failed-job', 'connection' => 'redis', 'queue' => 'default',
            'payload' => 'PRIVATE-PAYLOAD', 'exception' => 'PRIVATE-EXCEPTION', 'failed_at' => now()]);
        $response = $this->withToken($token)->getJson('/api/operations/health')->assertOk()
            ->assertJsonPath('data.status', 'degraded')->assertJsonPath('data.failed_jobs_count', 1)
            ->assertJsonPath('data.checks.2.pending_jobs', 3)
            ->assertJsonPath('data.checks.3.status', 'missing')->assertJsonPath('data.checks.4.status', 'stale');
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_heartbeat_records_scheduler_then_only_worker_execution_records_worker(): void
    {
        config(['cache.stores.redis' => ['driver' => 'array']]);
        Queue::fake();
        $this->artisan('bridge:heartbeat')->assertSuccessful();
        $this->assertSame(now()->timestamp, Cache::store('redis')->get('operations:scheduler'));
        $this->assertNull(Cache::store('redis')->get('operations:worker'));
        Queue::assertPushed(RecordWorkerHeartbeat::class);
        (new RecordWorkerHeartbeat)->handle();
        $this->assertSame(now()->timestamp, Cache::store('redis')->get('operations:worker'));
    }
}
