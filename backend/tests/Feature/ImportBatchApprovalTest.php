<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Services\Sync\ImportBatchApprovalService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class ImportBatchApprovalTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_approval_requires_current_dry_run_confirmation_and_tenant_access(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$batch, $row, $user, $token] = $this->syncWorkspace();
        $url = "/api/import-batches/{$batch->id}";
        $this->withToken($token)->postJson($url.'/sync')->assertConflict();
        $this->withToken($token)->postJson($url.'/approve', ['confirmed' => true, 'dry_run_hash' => str_repeat('a', 64)])->assertConflict();
        $hash = $this->withToken($token)->postJson($url.'/dry-run')->assertOk()->json('data.dry_run_hash');
        $this->withToken($token)->postJson($url.'/approve', ['confirmed' => false, 'dry_run_hash' => $hash])->assertUnprocessable();
        [, , , $otherToken] = $this->syncWorkspace();
        $this->withToken($otherToken)->postJson($url.'/approve', ['confirmed' => true, 'dry_run_hash' => $hash])->assertForbidden();
        $this->withToken($token)->postJson($url.'/approve', ['confirmed' => true, 'dry_run_hash' => $hash])->assertOk();
        $this->withToken($token)->postJson($url.'/approve', ['confirmed' => true, 'dry_run_hash' => $hash])->assertOk();
        $this->assertSame($user->id, $batch->refresh()->approved_by);
        $this->assertSame(1, AuditLog::where('event', 'import.sync.approved')->count());
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_input_or_contract_changes_block_sync_and_new_dry_run_revokes_approval(): void
    {
        Queue::fake();
        [$batch, $row, , $token] = $this->syncWorkspace();
        $hash = $this->approveSyncBatch($batch, $token);
        $service = app(ImportBatchApprovalService::class);
        $row->update(['normalized_row' => array_reverse($row->normalized_row, true)]);
        $this->assertSame($hash, $service->fingerprint($batch->refresh()));
        $row->update(['normalized_row' => [...$row->normalized_row, 'nama_mahasiswa' => 'Data Berubah']]);
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/sync")->assertConflict();
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/approve", ['confirmed' => true, 'dry_run_hash' => $hash])->assertConflict();
        $newHash = $this->approveSyncBatch($batch, $token);
        $this->assertNotSame($hash, $newHash);
        config(['neofeeder-contracts.review_revision' => 2]);
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/sync")->assertConflict();
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/dry-run")->assertOk();
        $this->assertNull($batch->refresh()->approved_at);
        Queue::assertNothingPushed();
    }

    public function test_invalid_and_empty_batches_cannot_be_approved(): void
    {
        [$batch, $row, , $token] = $this->syncWorkspace();
        $row->update(['status' => 'invalid']);
        $hash = $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/dry-run")->assertOk()->json('data.dry_run_hash');
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/approve", ['confirmed' => true, 'dry_run_hash' => $hash])->assertConflict();
        $row->delete();
        $hash = $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/dry-run")->assertOk()->json('data.dry_run_hash');
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/approve", ['confirmed' => true, 'dry_run_hash' => $hash])->assertConflict();
    }
}
