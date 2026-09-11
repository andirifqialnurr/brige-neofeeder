<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\ReferenceRecord;
use App\Models\StagingRecord;
use App\Models\Tenant;
use App\Services\Validation\ImportBatchValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ImportBatchValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_engine_marks_field_errors_warnings_and_duplicate_rows(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Kampus Contoh',
            'code' => 'KMP',
            'status' => 'active',
        ]);

        $batch = ImportBatch::query()->create([
            'tenant_id' => $tenant->id,
            'source_type' => 'excel',
            'status' => 'ready',
        ]);

        ReferenceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'endpoint' => 'GetAgama',
            'value_key' => 'id_agama',
            'value' => '1',
            'label' => 'Islam',
            'raw_payload' => [],
            'synced_at' => now(),
        ]);

        ReferenceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'endpoint' => 'GetAgama',
            'value_key' => 'id_agama',
            'value' => '2',
            'label' => 'Islam',
            'raw_payload' => [],
            'synced_at' => now(),
        ]);

        foreach ([2, 3] as $rowNumber) {
            StagingRecord::query()->create([
                'tenant_id' => $tenant->id,
                'import_batch_id' => $batch->id,
                'channel' => 'mahasiswa_biodata',
                'sheet_name' => 'mahasiswa_biodata',
                'row_number' => $rowNumber,
                'operation' => 'insert',
                'natural_key' => 'nik=3201010101010001',
                'raw_row' => [],
                'normalized_row' => [
                    'nama_mahasiswa' => '',
                    'jenis_kelamin' => 'X',
                    'tempat_lahir' => 'Batam',
                    'tanggal_lahir' => '01-01-2000',
                    'id_agama' => 'Islam',
                    'nik' => '3201010101010001',
                    'kewarganegaraan' => 'ID',
                    'kelurahan' => 'Batam Kota',
                    'id_wilayah' => $rowNumber === 2 ? '016000' : '016001',
                    'penerima_kps' => '0',
                    'nama_ibu_kandung' => null,
                    'id_kebutuhan_khusus_mahasiswa' => 0,
                    'id_kebutuhan_khusus_ayah' => 0,
                    'id_kebutuhan_khusus_ibu' => 0,
                ],
                'validation_result' => [],
                'status' => 'ready',
            ]);
        }

        $summary = app(ImportBatchValidationService::class)->validate($batch);
        $record = StagingRecord::query()->where('row_number', 2)->firstOrFail();
        $rules = collect($record->validation_result['errors'])->pluck('rule')->all();
        $warningRules = collect($record->validation_result['warnings'])->pluck('rule')->all();
        $infoRules = collect($record->validation_result['info'])->pluck('rule')->all();

        $this->assertSame(2, $summary['invalid_rows']);
        $this->assertSame('invalid', $record->status);
        $this->assertContains('required', $rules);
        $this->assertContains('date_format', $rules);
        $this->assertContains('enum', $rules);
        $this->assertContains('reference_exists', $rules);
        $this->assertContains('duplicate_row', $rules);
        $this->assertContains('ambiguous_reference', $warningRules);
        $this->assertContains('dependency_check', $infoRules);
    }
}
