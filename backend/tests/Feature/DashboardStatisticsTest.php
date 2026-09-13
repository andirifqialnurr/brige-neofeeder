<?php

namespace Tests\Feature;

use App\Models\ApiAccessToken;
use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\ReferenceRecord;
use App\Models\StagingRecord;
use App\Models\SyncAttempt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardStatisticsTest extends TestCase
{
    use RefreshDatabase;

    private function fixtures(string $role = 'operator'): array
    {
        $this->travelTo(now()->setDate(2026, 9, 13)->startOfDay()->addHours(12));
        $a = Tenant::create(['name' => 'A', 'code' => 'A', 'status' => 'active']);
        $b = Tenant::create(['name' => 'B', 'code' => 'B', 'status' => 'draft']);
        $user = User::create(['name' => 'Tester', 'email' => 'tester@example.test', 'password' => 'test-password', 'tenant_id' => $a->id, 'role' => $role, 'status' => 'active']);
        ApiAccessToken::create(['user_id' => $user->id, 'name' => 'test', 'token_hash' => hash('sha256', 'statistics-test')]);
        $this->withToken('statistics-test');
        for ($i = 0; $i < 52; $i++) {
            ImportBatch::create(['tenant_id' => $a->id, 'source_type' => 'excel', 'status' => 'validated'])->forceFill(['created_at' => now()->subDays($i % 2 ? 45 : 1)])->save();
        }
        $batch = ImportBatch::create(['tenant_id' => $b->id, 'source_type' => 'excel', 'status' => 'failed']);
        StagingRecord::create(['tenant_id' => $b->id, 'import_batch_id' => $batch->id, 'channel' => 'mahasiswa_biodata', 'sheet_name' => 'mahasiswa_biodata', 'row_number' => 2, 'status' => 'invalid', 'normalized_row' => ['nik' => 'PRIVATE-NIK']]);
        $ownBatch = ImportBatch::where('tenant_id', $a->id)->first();
        $row = StagingRecord::create(['tenant_id' => $a->id, 'import_batch_id' => $ownBatch->id, 'channel' => 'mata_kuliah', 'sheet_name' => 'mata_kuliah', 'row_number' => 2, 'status' => 'valid', 'validation_result' => ['warnings' => [['field' => 'x']]]]);
        foreach (['success', 'failed', 'queued', 'retrying'] as $status) {
            SyncAttempt::create(['tenant_id' => $a->id, 'staging_record_id' => $row->id, 'action' => 'InsertMataKuliah', 'status' => $status]);
        }
        NeoFeederConnection::create(['tenant_id' => $a->id, 'base_url' => 'https://example.invalid', 'status' => 'active']);
        foreach (['1', '2'] as $value) {
            ReferenceRecord::create(['tenant_id' => $a->id, 'endpoint' => 'GetAgama', 'value_key' => 'id_agama', 'value' => $value]);
        }

        return [$a, $b];
    }

    public function test_operator_totals_are_tenant_scoped_and_not_limited_to_fifty_batches(): void
    {
        [, $other] = $this->fixtures();
        $response = $this->getJson('/api/dashboard/statistics?days=14&tenant_id='.$other->id)->assertOk()
            ->assertJsonPath('data.totals.batches', 52)->assertJsonPath('data.totals.campuses', 1)
            ->assertJsonPath('data.totals.staging_rows', 1)->assertJsonPath('data.totals.warning_rows', 1)
            ->assertJsonPath('data.totals.sync_success_rate', 50)->assertJsonPath('data.totals.reference_endpoints_covered', 1)
            ->assertJsonPath('data.totals.reference_rows', 2)->assertJsonCount(14, 'data.activity');
        $this->assertSame(26, collect($response->json('data.activity'))->sum('total'));
        $this->assertStringNotContainsString('PRIVATE-NIK', $response->getContent());
        $this->assertSame(0, $response->json('data.activity.0.total'));
    }

    public function test_admin_can_report_all_tenants_or_one_empty_scope(): void
    {
        [, $b] = $this->fixtures('admin');
        $this->getJson('/api/dashboard/statistics')->assertOk()->assertJsonPath('data.totals.batches', 53)->assertJsonPath('data.totals.campuses', 2);
        $this->getJson('/api/dashboard/statistics?tenant_id='.$b->id)->assertOk()->assertJsonPath('data.totals.batches', 1)->assertJsonPath('data.totals.sync_success_rate', null)->assertJsonPath('data.automation_available', false);
    }

    public function test_invalid_range_and_unauthenticated_requests_are_rejected(): void
    {
        $this->getJson('/api/dashboard/statistics')->assertUnauthorized();
        $this->fixtures();
        $this->getJson('/api/dashboard/statistics?days=10000')->assertUnprocessable();
    }
}
