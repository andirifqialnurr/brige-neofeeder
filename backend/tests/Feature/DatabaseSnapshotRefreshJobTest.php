<?php

namespace Tests\Feature;

use App\Jobs\RefreshDatabaseSourceSnapshotJob;
use App\Models\SourceConnection;
use App\Services\Mapping\DatabaseSourceReaderContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class DatabaseSnapshotRefreshJobTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_endpoint_queues_one_unique_snapshot_refresh(): void
    {
        Queue::fake();
        [$source, , , $token] = $this->source();

        $this->withToken($token)->postJson("/api/mapping/sources/{$source->id}/refresh-snapshot")
            ->assertStatus(202)
            ->assertJsonPath('data.snapshot_status', 'queued');
        Queue::assertPushed(RefreshDatabaseSourceSnapshotJob::class, 1);
        $this->withToken($token)->postJson("/api/mapping/sources/{$source->id}/refresh-snapshot")
            ->assertStatus(202);
        Queue::assertPushed(RefreshDatabaseSourceSnapshotJob::class, 1);
        $this->assertDatabaseHas('audit_logs', [
            'event' => 'source.database_snapshot_refresh_requested',
            'subject_id' => $source->id,
        ]);
    }

    public function test_job_refreshes_snapshot_and_keeps_status_safe(): void
    {
        [$source, , , $token] = $this->source();
        $reader = Mockery::mock(DatabaseSourceReaderContract::class);
        $reader->shouldReceive('read')->once()->withArgs(fn (array $config): bool => $config['table'] === 'mahasiswa')
            ->andReturn([
                'headers' => ['nim'],
                'snapshot' => [['row_number' => 1, 'values' => ['nim' => '0001']]],
                'row_count' => 1,
            ]);
        $source->update(['snapshot_status' => 'queued']);

        (new RefreshDatabaseSourceSnapshotJob($source->id))->handle($reader);

        $this->assertSame('ready', $source->refresh()->snapshot_status);
        $this->assertSame(1, $source->row_count);
        $this->assertSame(2, $source->refresh()->snapshot_version);
        $this->withToken($token)->getJson("/api/mapping/sources/{$source->id}/schema")
            ->assertOk()
            ->assertJsonPath('data.source.snapshot_status', 'ready');
    }

    public function test_job_marks_failure_without_exposing_reader_exception(): void
    {
        [$source] = $this->source();
        $reader = Mockery::mock(DatabaseSourceReaderContract::class);
        $reader->shouldReceive('read')->once()->andThrow(new \RuntimeException('password=private-secret'));
        $source->update(['snapshot_status' => 'queued']);
        $job = new RefreshDatabaseSourceSnapshotJob($source->id);

        try {
            $job->handle($reader);
        } catch (\RuntimeException) {
            // The queue must retry the failure; the persisted status stays safe.
        }
        $job->failed(new \RuntimeException('password=private-secret'));

        $this->assertSame('failed', $source->refresh()->snapshot_status);
        $this->assertStringNotContainsString('private-secret', (string) $source->snapshot_error);
    }

    /** @return array{0:SourceConnection,1:mixed,2:mixed,3:string} */
    private function source(): array
    {
        [$batch, $row, $user, $token] = $this->syncWorkspace();
        $source = SourceConnection::create([
            'tenant_id' => $user->tenant_id, 'type' => 'database', 'name' => 'siakad.mahasiswa',
            'sha256' => str_repeat('f', 64), 'headers' => ['nim'], 'snapshot' => [], 'row_count' => 0,
            'schema_discovery_status' => 'ready', 'snapshot_status' => 'ready',
            'connection_config' => [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'private-secret',
                'table' => 'mahasiswa', 'columns' => ['nim'],
            ],
        ]);

        return [$source, $batch, $row, $token];
    }
}
