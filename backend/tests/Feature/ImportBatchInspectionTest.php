<?php

namespace Tests\Feature;

use App\Http\Middleware\AuthenticateApiToken;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\StagingRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class ImportBatchInspectionTest extends TestCase
{
    use RefreshDatabase;

    private function fixture(): array
    {
        $tenant = Tenant::create(['name' => 'Demo', 'code' => 'DEMO', 'status' => 'active']);
        $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator', 'email' => 'demo@example.test', 'password' => 'test-password', 'role' => 'operator', 'status' => 'active']);
        // Authentication is exercised separately; these tests isolate controller authorization.
        $this->withoutMiddleware(AuthenticateApiToken::class)->actingAs($user);
        $batch = ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'status' => 'invalid', 'summary' => ['original_name' => 'demo.xlsx', 'missing_sheets' => ['kelas_kuliah']]]);
        $row = StagingRecord::create([
            'tenant_id' => $tenant->id, 'import_batch_id' => $batch->id,
            'channel' => 'mahasiswa_biodata', 'sheet_name' => 'mahasiswa_biodata', 'row_number' => 2,
            'operation' => 'insert', 'status' => 'invalid', 'raw_row' => ['nik' => '0000000000000001'],
            'normalized_row' => ['nik' => '0000000000000001'],
            'validation_result' => ['errors' => [['field' => 'nama_mahasiswa', 'rule' => 'required', 'message' => '=HYPERLINK("https://example.test")']], 'warnings' => [], 'info' => []],
        ]);

        return [$tenant, $batch, $row];
    }

    public function test_detail_rows_filters_pagination_and_read_only_payload(): void
    {
        [$tenant, $batch, $row] = $this->fixture();
        $valid = $row->replicate();
        $data = collect(config('neofeeder-contracts.channels.mahasiswa_biodata.fields'))->where('required', true)->mapWithKeys(fn ($field) => [$field['name'] => '1'])->all();
        $valid->fill(['row_number' => 3, 'status' => 'valid', 'validation_result' => [], 'normalized_row' => [...$data, 'nama_mahasiswa' => 'Demo', 'nik' => '0000000000000002']])->save();
        $this->getJson("/api/import-batches/{$batch->id}")->assertOk()->assertJsonPath('data.sheets.0.total_rows', 2);
        $this->getJson("/api/import-batches/{$batch->id}/rows?per_page=1&page=2")->assertOk()->assertJsonPath('meta.total', 2)->assertJsonPath('data.0.row_number', 3);
        $this->getJson("/api/import-batches/{$batch->id}/rows?status=invalid&sheet=mahasiswa_biodata")->assertOk()->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.errors', 1);
        $this->getJson("/api/import-batches/{$batch->id}/rows?status=invalid&sheet=missing")->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/import-batches/{$batch->id}/rows?per_page=1000")->assertUnprocessable();
        $this->getJson("/api/import-batches/{$batch->id}/rows/{$valid->id}")->assertOk()->assertJsonPath('data.payload.record.nik', '0000000000000002')->assertJsonPath('data.candidate_operation', 'insert');
        $this->assertSame('invalid', $batch->refresh()->status);
        $this->assertSame(1, AuditLog::where('event', 'import.row.viewed')->count());
        $this->assertStringNotContainsString('0000000000000002', AuditLog::first()->toJson());
    }

    public function test_cross_tenant_and_cross_batch_access_is_denied(): void
    {
        [$tenant, $batch, $row] = $this->fixture();
        $other = Tenant::create(['name' => 'Other', 'code' => 'OTHER', 'status' => 'active']);
        $foreign = ImportBatch::create(['tenant_id' => $other->id, 'source_type' => 'excel', 'status' => 'validated']);
        foreach (['', '/rows', '/rows/'.$row->id, '/report'] as $suffix) {
            $this->getJson("/api/import-batches/{$foreign->id}{$suffix}")->assertForbidden();
        }
        $another = ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'status' => 'validated']);
        $this->getJson("/api/import-batches/{$another->id}/rows/{$row->id}")->assertNotFound();
        $this->getJson('/api/import-batches?tenant_id='.$other->id)->assertJsonPath('meta.total', 2);
        $this->assertSame(0, AuditLog::count());
    }

    public function test_batch_search_and_pagination_include_older_batches(): void
    {
        [$tenant, $batch] = $this->fixture();
        for ($i = 0; $i < 51; $i++) {
            ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'status' => 'validated']);
        }
        $this->getJson('/api/import-batches?per_page=50&page=2')->assertOk()->assertJsonPath('meta.total', 52)->assertJsonCount(2, 'data');
        $this->getJson('/api/import-batches?search=demo.xlsx')->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.id', $batch->id);
    }

    public function test_report_includes_workbook_and_row_issues_without_executable_formulas(): void
    {
        [, $batch] = $this->fixture();
        $response = $this->get("/api/import-batches/{$batch->id}/report")->assertOk()->assertDownload('temuan-'.$batch->id.'.xlsx');
        $file = tempnam(sys_get_temp_dir(), 'report');
        try {
            file_put_contents($file, $response->streamedContent());
            $workbook = IOFactory::load($file);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame('missing_sheet', $sheet->getCell('F2')->getValue());
            $this->assertSame('required', $sheet->getCell('F3')->getValue());
            $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('G3')->getDataType());
            $this->assertStringStartsWith('=HYPERLINK', $sheet->getCell('G3')->getValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($file);
        }
        $this->assertSame(1, AuditLog::where('event', 'import.report.downloaded')->count());
    }

    public function test_dry_run_does_not_change_an_in_flight_batch(): void
    {
        [, $batch] = $this->fixture();
        $batch->update(['status' => 'parsing']);
        $this->postJson("/api/import-batches/{$batch->id}/dry-run")->assertConflict();
        $this->assertSame('parsing', $batch->refresh()->status);
        $this->getJson("/api/import-batches/{$batch->id}/report")->assertConflict();
    }
}
