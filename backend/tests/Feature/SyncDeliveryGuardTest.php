<?php

namespace Tests\Feature;

use App\Jobs\SyncStagingRecordJob;
use App\Models\NeoFeederConnection;
use App\Models\SyncAttempt;
use App\Services\Sync\NeoFeederRecordSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class SyncDeliveryGuardTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    private function planned(): array
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$batch, $row, $user, $token] = $this->syncWorkspace();
        NeoFeederConnection::create(['tenant_id' => $batch->tenant_id, 'status' => 'active', 'base_url' => 'https://neo.test/ws/live2.php', 'username' => 'test', 'encrypted_password' => Crypt::encryptString('synthetic-password')]);
        $this->approveSyncBatch($batch, $token);
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/sync")->assertOk();

        return [$batch, $row, $token, SyncAttempt::firstOrFail()];
    }

    public function test_repeated_start_and_duplicate_worker_do_not_duplicate_delivery(): void
    {
        [$batch, $row, $token, $attempt] = $this->planned();
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/sync")->assertOk();
        $this->assertDatabaseCount('sync_attempts', 1);
        Http::fake(['neo.test/*' => Http::sequence()->push(['error_code' => '0', 'data' => ['token' => 'test-token']])->push(['error_code' => '0', 'data' => ['id_mahasiswa' => 'remote-1']])]);
        $worker = new SyncStagingRecordJob($attempt->id);
        $worker->handle(app(NeoFeederRecordSyncService::class));
        $worker->handle(app(NeoFeederRecordSyncService::class));
        $this->assertSame('synced', $batch->refresh()->status);
        $this->assertSame('success', $row->refresh()->status);
        $this->withToken($token)->postJson("/api/sync-attempts/{$attempt->id}/retry")->assertConflict();
        Http::assertSentCount(2);
        $this->assertArrayNotHasKey('token', $attempt->refresh()->request_payload);
    }

    public function test_timeout_after_post_is_unknown_and_cannot_be_retried(): void
    {
        [$batch, $row, $token, $attempt] = $this->planned();
        Http::fake(fn ($request) => $request['act'] === 'GetToken'
            ? Http::response(['error_code' => '0', 'data' => ['token' => 'test-token']])
            : throw new ConnectionException('Timeout with a secret URL that must not be stored'));
        $service = app(NeoFeederRecordSyncService::class);
        $this->assertFalse($service->sync($attempt));
        $this->assertFalse($service->sync($attempt->refresh()));
        $this->assertSame('unknown', $attempt->refresh()->status);
        $this->assertFalse($attempt->retry_safe);
        $this->assertStringNotContainsString('secret URL', $attempt->error_desc);
        $this->withToken($token)->postJson("/api/sync-attempts/{$attempt->id}/retry")->assertConflict();
        $this->assertDatabaseCount('sync_attempts', 1);
        $this->assertSame('failed', $batch->refresh()->status);
    }

    public function test_preflight_network_retries_are_bounded_and_never_post_records(): void
    {
        [, , , $attempt] = $this->planned();
        $requests = 0;
        Http::fake(function ($request) use (&$requests) {
            $this->assertSame('GetToken', $request['act']);
            $requests++;
            throw new ConnectionException('network');
        });
        $service = app(NeoFeederRecordSyncService::class);
        $this->assertTrue($service->sync($attempt));
        $this->assertTrue($service->sync($attempt->refresh()));
        $this->assertFalse($service->sync($attempt->refresh()));
        $this->assertFalse($service->sync($attempt->refresh()));
        $this->assertSame(3, $requests);
        $this->assertSame('failed', $attempt->refresh()->status);
        $this->assertTrue($attempt->retry_safe);
        $this->assertNull($attempt->request_started_at);
    }

    public function test_manual_retry_is_idempotent_and_tenant_scoped(): void
    {
        [$batch, , $token, $attempt] = $this->planned();
        Http::fake(['neo.test/*' => Http::sequence()->push(['error_code' => '0', 'data' => ['token' => 't']])->push(['error_code' => '123', 'error_desc' => 'Referensi belum tersedia'])]);
        app(NeoFeederRecordSyncService::class)->sync($attempt);
        [, , , $otherToken] = $this->syncWorkspace();
        $url = "/api/sync-attempts/{$attempt->id}/retry";
        $this->withToken($otherToken)->postJson($url)->assertForbidden();
        $id = $this->withToken($token)->postJson($url)->assertAccepted()->json('data.id');
        $this->withToken($token)->postJson($url)->assertAccepted()->assertJsonPath('data.id', $id);
        $this->assertDatabaseCount('sync_attempts', 2);
        $this->assertSame('syncing', $batch->refresh()->status);
        $this->withToken($token)->postJson("/api/sync-attempts/{$id}/retry")->assertConflict();
    }

    public function test_changed_input_and_malformed_success_never_create_blind_retries(): void
    {
        [, $row, , $attempt] = $this->planned();
        $row->update(['normalized_row' => [...$row->normalized_row, 'nama_mahasiswa' => 'Changed']]);
        (new SyncStagingRecordJob($attempt->id))->handle(app(NeoFeederRecordSyncService::class));
        Http::assertNothingSent();
        $this->assertSame('failed', $attempt->refresh()->status);
    }

    public function test_missing_result_id_is_ambiguous_and_recovery_never_resends_a_write(): void
    {
        [$batch, , , $attempt] = $this->planned();
        Http::fake(['neo.test/*' => Http::sequence()->push(['error_code' => '0', 'data' => ['token' => 't']])->push(['error_code' => '0', 'data' => ['id_mahasiswa' => null]])]);
        app(NeoFeederRecordSyncService::class)->sync($attempt);
        $this->assertSame('unknown', $attempt->refresh()->status);
        $this->assertSame('missing_identity', $attempt->error_code);
        $attempt->forceFill(['status' => 'syncing', 'attempted_at' => now()->subMinutes(11), 'request_started_at' => now()->subMinutes(11)])->save();
        $batch->forceFill(['status' => 'syncing'])->save();
        $this->artisan('bridge:recover-sync')->assertSuccessful();
        $this->assertSame('unknown', $attempt->refresh()->status);
        $this->assertFalse($attempt->retry_safe);
        Http::assertSentCount(2);
    }

    public function test_cancelled_claim_cannot_cross_the_post_boundary(): void
    {
        [, , , $attempt] = $this->planned();
        $service = app(NeoFeederRecordSyncService::class);
        Http::fake(function ($request) use ($service, $attempt) {
            $this->assertSame('GetToken', $request['act']);
            $service->failSafely($attempt->id);

            return Http::response(['error_code' => '0', 'data' => ['token' => 't']]);
        });
        $this->assertFalse($service->sync($attempt));
        $this->assertNull($attempt->refresh()->request_started_at);
        $this->assertSame('failed', $attempt->status);
        Http::assertSentCount(1);
    }

    public function test_dependencies_are_planned_in_order_and_block_child_writes_after_failure(): void
    {
        Queue::fake();
        [$batch, $parent, , $token] = $this->syncWorkspace();
        $parent->update(['channel' => 'mata_kuliah', 'sheet_name' => 'mata_kuliah', 'normalized_row' => [
            'id_matkul' => '00000000-0000-4000-8000-000000000001', 'id_prodi' => '00000000-0000-4000-8000-000000000002',
            'kode_mata_kuliah' => 'TEST01', 'nama_mata_kuliah' => 'Matkul Fiktif', 'sks_mata_kuliah' => 3,
        ]]);
        $child = $parent->replicate();
        $child->fill(['channel' => 'kelas_kuliah', 'sheet_name' => 'kelas_kuliah', 'normalized_row' => [
            'id_matkul' => '00000000-0000-4000-8000-000000000001', 'id_prodi' => '00000000-0000-4000-8000-000000000002',
            'id_semester' => '20261', 'nama_kelas_kuliah' => 'A', 'apa_untuk_pditt' => '0',
        ]])->save();
        NeoFeederConnection::create(['tenant_id' => $batch->tenant_id, 'status' => 'active', 'base_url' => 'https://neo.test/ws/live2.php', 'username' => 'test', 'encrypted_password' => Crypt::encryptString('synthetic-password')]);
        $this->approveSyncBatch($batch, $token);
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/sync")->assertOk();
        $attempts = SyncAttempt::orderBy('id')->get();
        $this->assertSame($parent->id, $attempts[0]->staging_record_id);
        $service = app(NeoFeederRecordSyncService::class);
        Http::fake(['neo.test/*' => Http::sequence()->push(['error_code' => '0', 'data' => ['token' => 't']])->push(['error_code' => '123', 'error_desc' => 'Rejected'])]);
        $this->assertTrue($service->sync($attempts[1]));
        Http::assertNothingSent();
        $this->assertFalse($service->sync($attempts[0]));
        $this->assertFalse($service->sync($attempts[1]->refresh()));
        $this->assertSame('dependency_blocked', $attempts[1]->refresh()->error_code);
        $this->withToken($token)->postJson("/api/sync-attempts/{$attempts[1]->id}/retry")->assertConflict();
        Http::assertSentCount(2);
    }
}
