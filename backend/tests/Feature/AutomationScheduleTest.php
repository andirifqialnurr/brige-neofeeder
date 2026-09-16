<?php

namespace Tests\Feature;

use App\Jobs\RefreshDatabaseSourceSnapshotJob;
use App\Jobs\RunAutomationScheduleJob;
use App\Models\AutomationSchedule;
use App\Models\MappingProfile;
use App\Models\SourceConnection;
use App\Models\User;
use App\Services\Mapping\FileMappingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class AutomationScheduleTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_operator_can_create_list_and_manually_queue_database_schedule(): void
    {
        [$schedule, $token] = $this->schedule();
        Queue::fake();

        $response = $this->withToken($token)->postJson('/api/automation/schedules', [
            'tenant_id' => $schedule->tenant_id,
            'source_connection_id' => $schedule->source_connection_id,
            'mapping_profile_id' => $schedule->mapping_profile_id,
            'name' => 'Sync mahasiswa harian',
            'frequency' => 'daily',
        ])->assertCreated()->assertJsonPath('data.status', 'idle');
        $id = $response->json('data.id');

        $this->withToken($token)->getJson('/api/automation/schedules')->assertOk()->assertJsonPath('data.0.name', 'Sync mahasiswa harian');
        $this->withToken($token)->postJson("/api/automation/schedules/{$id}/run")
            ->assertStatus(202)->assertJsonPath('data.status', 'queued');
        Queue::assertPushed(RunAutomationScheduleJob::class, 1);
        $this->assertDatabaseHas('audit_logs', ['event' => 'automation.schedule_run_requested', 'subject_id' => $id]);
    }

    public function test_due_schedule_command_advances_next_run_and_queues_once(): void
    {
        [$schedule, $token] = $this->schedule();
        $response = $this->withToken($token)->postJson('/api/automation/schedules', [
            'source_connection_id' => $schedule->source_connection_id,
            'mapping_profile_id' => $schedule->mapping_profile_id,
            'name' => 'Sync mahasiswa hourly',
            'frequency' => 'hourly',
        ])->assertCreated();
        $id = $response->json('data.id');
        AutomationSchedule::findOrFail($id)->update(['next_run_at' => now()->subMinute()]);
        Queue::fake();

        $this->artisan('bridge:queue-automation-schedules')->expectsOutput('Queued 1 automation schedule(s).')->assertSuccessful();
        Queue::assertPushed(RunAutomationScheduleJob::class, 1);
        $queued = AutomationSchedule::findOrFail($id);
        $this->assertSame('queued', $queued->status);
        $this->assertTrue($queued->next_run_at->isFuture());
        $this->artisan('bridge:queue-automation-schedules')->expectsOutput('Queued 0 automation schedule(s).')->assertSuccessful();
        Queue::assertPushed(RunAutomationScheduleJob::class, 1);
    }

    public function test_admin_can_list_schedule_for_selected_tenant(): void
    {
        [$schedule, $token] = $this->schedule();
        User::findOrFail($schedule->created_by)->update(['role' => 'admin', 'tenant_id' => null]);

        $this->withToken($token)->postJson('/api/automation/schedules', [
            'tenant_id' => $schedule->tenant_id,
            'source_connection_id' => $schedule->source_connection_id,
            'mapping_profile_id' => $schedule->mapping_profile_id,
            'name' => 'Schedule tenant pilihan admin',
            'frequency' => 'daily',
        ])->assertCreated();
        $this->withToken($token)->getJson('/api/automation/schedules?tenant_id='.$schedule->tenant_id)
            ->assertOk()->assertJsonPath('data.0.name', 'Schedule tenant pilihan admin');
    }

    public function test_schedule_job_refreshes_maps_and_stages_without_outbound_request(): void
    {
        Bus::fake([RefreshDatabaseSourceSnapshotJob::class]);
        [$schedule, $token] = $this->schedule();
        $id = $this->withToken($token)->postJson('/api/automation/schedules', [
            'source_connection_id' => $schedule->source_connection_id,
            'mapping_profile_id' => $schedule->mapping_profile_id,
            'name' => 'Sync mahasiswa weekly',
            'frequency' => 'weekly',
        ])->assertCreated()->json('data.id');
        $schedule = AutomationSchedule::findOrFail($id);
        $schedule->update(['status' => 'queued']);

        (new RunAutomationScheduleJob($schedule->id))->handle(app(FileMappingService::class));

        $saved = $schedule->refresh();
        $this->assertSame('success', $saved->status);
        $this->assertNotNull($saved->last_batch_id);
        $this->assertDatabaseHas('import_batches', ['id' => $saved->last_batch_id, 'source_type' => 'mapping']);
        Bus::assertDispatched(RefreshDatabaseSourceSnapshotJob::class);
        $this->assertDatabaseCount('sync_attempts', 0);
    }

    public function test_schedule_cannot_cross_tenant_or_use_file_source(): void
    {
        [$schedule, $token] = $this->schedule();
        [, , , $foreignToken] = $this->syncWorkspace();
        $this->withToken($foreignToken)->getJson('/api/automation/schedules')->assertOk()->assertJsonCount(0, 'data');
        SourceConnection::findOrFail($schedule->source_connection_id)->update(['type' => 'file']);
        $this->withToken($token)->postJson('/api/automation/schedules', [
            'source_connection_id' => $schedule->source_connection_id,
            'mapping_profile_id' => $schedule->mapping_profile_id,
            'name' => 'Invalid',
            'frequency' => 'daily',
        ])->assertUnprocessable();
    }

    /** @return array{0: AutomationSchedule, 1: string} */
    private function schedule(): array
    {
        [, , $user, $token] = $this->syncWorkspace();
        $source = SourceConnection::create([
            'tenant_id' => $user->tenant_id,
            'type' => 'database',
            'name' => 'siakad.mahasiswa',
            'sha256' => str_repeat('a', 64),
            'headers' => [],
            'snapshot' => [['row_number' => 2, 'values' => []]],
            'row_count' => 1,
            'snapshot_status' => 'ready',
            'connection_config' => [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'secret',
                'table' => 'mahasiswa', 'columns' => ['nim'],
            ],
        ]);
        $profile = MappingProfile::create(['tenant_id' => $user->tenant_id, 'name' => 'Biodata otomatis', 'channel' => 'mahasiswa_biodata', 'version' => 1]);
        $profile->versions()->create(['version' => 1, 'rules' => []]);

        return [new AutomationSchedule([
            'tenant_id' => $user->tenant_id,
            'source_connection_id' => $source->id,
            'mapping_profile_id' => $profile->id,
            'created_by' => $user->id,
        ]), $token];
    }
}
