<?php

namespace Tests\Feature;

use App\Models\SyncAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class BatchReconciliationTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_report_uses_latest_attempt_preserves_unknown_and_exports_no_payload(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        [$batch, $row, , $token] = $this->syncWorkspace();
        $row->update(['sheet_name' => '=FORMULA', 'status' => 'failed']);
        SyncAttempt::create(['id' => '01900000-0000-7000-8000-000000000001', 'tenant_id' => $batch->tenant_id, 'staging_record_id' => $row->id, 'action' => 'InsertBiodataMahasiswa', 'status' => 'failed']);
        $unknown = SyncAttempt::create(['tenant_id' => $batch->tenant_id, 'staging_record_id' => $row->id, 'action' => 'InsertBiodataMahasiswa', 'status' => 'unknown', 'request_payload' => ['token' => 'secret-token'], 'error_desc' => 'private description']);
        $other = $row->replicate();
        $other->row_number = 3;
        $other->status = 'valid';
        $other->save();
        $url = '/api/import-batches/'.$batch->id.'/reconciliation';
        $report = $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('summary.unknown', 1)->assertJsonPath('summary.not_sent', 1)->assertJsonPath('summary.staging_rows', 2)->assertJsonPath('data.0.attempt_id', $unknown->id);
        $this->assertStringNotContainsString('secret-token', $report->getContent());
        $this->assertStringNotContainsString('private description', $report->getContent());
        $this->withToken($token)->getJson($url.'?state=unknown')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonCount(1, 'data');
        $csv = $this->withToken($token)->get($url.'?format=csv&state=unknown')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=FORMULA", $csv);
        $this->assertStringContainsString('unknown', $csv);
        $this->assertStringNotContainsString('secret-token', $csv);
        $this->assertDatabaseHas('audit_logs', ['event' => 'import.reconciliation.exported', 'subject_id' => $batch->id]);
        [, , , $foreignToken] = $this->syncWorkspace();
        $this->withToken($foreignToken)->getJson($url)->assertForbidden();
        $this->withToken($foreignToken)->get($url.'?format=csv')->assertForbidden();
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
