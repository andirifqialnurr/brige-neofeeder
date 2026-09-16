<?php

namespace Tests\Feature;

use App\Jobs\DiscoverDatabaseSchemaJob;
use App\Models\SourceConnection;
use App\Services\Mapping\DatabaseSchemaDiscoveryContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class DatabaseSchemaDiscoveryJobTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_endpoint_queues_one_unique_discovery_and_schema_is_tenant_scoped(): void
    {
        Queue::fake();
        [$source, , , $token] = $this->syncWorkspaceSource();
        $response = $this->withToken($token)->postJson("/api/mapping/sources/{$source->id}/discover-schema")->assertStatus(202)
            ->assertJsonPath('data.schema_discovery_status', 'queued');
        Queue::assertPushed(DiscoverDatabaseSchemaJob::class, 1);
        $this->withToken($token)->postJson("/api/mapping/sources/{$source->id}/discover-schema")->assertStatus(202);
        Queue::assertPushed(DiscoverDatabaseSchemaJob::class, 1);
        $this->withToken($token)->getJson("/api/mapping/sources/{$source->id}/schema")->assertOk()
            ->assertJsonPath('data.source.id', $source->id)->assertJsonCount(0, 'data.tables');
        $this->assertSame('queued', $response->json('data.schema_discovery_status'));
    }

    public function test_job_persists_ready_state_and_never_returns_connection_config(): void
    {
        [$source, , , $token] = $this->syncWorkspaceSource();
        $service = Mockery::mock(DatabaseSchemaDiscoveryContract::class);
        $service->shouldReceive('discover')->once()->andReturn(['tables' => []]);
        $service->shouldReceive('store')->once()->andReturn($source);
        $this->app->instance(DatabaseSchemaDiscoveryContract::class, $service);
        $source->update(['schema_discovery_status' => 'queued']);

        (new DiscoverDatabaseSchemaJob($source->id))->handle($service);

        $this->assertSame('ready', $source->refresh()->schema_discovery_status);
        $this->assertNotNull($source->schema_discovered_at);
        $payload = $this->withToken($token)->getJson("/api/mapping/sources/{$source->id}/schema");
        $payload->assertOk();
        $this->assertStringNotContainsString('connection_config', $payload->getContent());
        $this->assertStringNotContainsString('secret', $payload->getContent());
    }

    public function test_job_marks_failure_without_exposing_exception_message(): void
    {
        [$source] = $this->syncWorkspaceSource();
        $service = Mockery::mock(DatabaseSchemaDiscoveryContract::class);
        $service->shouldReceive('discover')->once()->andThrow(new \RuntimeException('password=private-secret host details'));
        $this->app->instance(DatabaseSchemaDiscoveryContract::class, $service);
        $source->update(['schema_discovery_status' => 'queued']);
        $job = new DiscoverDatabaseSchemaJob($source->id);

        try {
            $job->handle($service);
        } catch (\RuntimeException) {
            // The queue must retry the failure; the persisted status stays safe.
        }
        $job->failed(new \RuntimeException('password=private-secret'));

        $this->assertSame('failed', $source->refresh()->schema_discovery_status);
        $this->assertStringNotContainsString('private-secret', (string) $source->schema_discovery_error);
    }

    /** @return array{0:SourceConnection,1:mixed,2:mixed,3:string} */
    private function syncWorkspaceSource(): array
    {
        [$batch, $row, $user, $token] = $this->syncWorkspace();
        $source = SourceConnection::create([
            'tenant_id' => $user->tenant_id, 'type' => 'database', 'name' => 'siakad.students',
            'sha256' => str_repeat('d', 64), 'headers' => [], 'snapshot' => [], 'row_count' => 0,
            'connection_config' => ['database' => 'siakad', 'password' => 'private-secret'],
        ]);

        return [$source, $batch, $row, $token];
    }
}
