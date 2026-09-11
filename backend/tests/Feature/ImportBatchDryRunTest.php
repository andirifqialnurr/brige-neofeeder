<?php

namespace Tests\Feature;

use App\Models\ApiAccessToken;
use App\Models\ImportBatch;
use App\Models\StagingRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImportBatchDryRunTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_preview_dry_run_payloads_before_sync(): void
    {
        [$tenant, $plainToken] = $this->tenantOperatorToken();

        $batch = ImportBatch::query()->create([
            'tenant_id' => $tenant->id,
            'source_type' => 'excel',
            'status' => 'validated',
        ]);

        StagingRecord::query()->create([
            'tenant_id' => $tenant->id,
            'import_batch_id' => $batch->id,
            'channel' => 'mahasiswa_biodata',
            'sheet_name' => 'mahasiswa_biodata',
            'row_number' => 2,
            'operation' => 'insert',
            'natural_key' => 'nik=3201010101010001',
            'raw_row' => [],
            'normalized_row' => $this->validBiodataRow(),
            'validation_result' => ['errors' => [], 'warnings' => [], 'info' => []],
            'status' => 'valid',
        ]);

        StagingRecord::query()->create([
            'tenant_id' => $tenant->id,
            'import_batch_id' => $batch->id,
            'channel' => 'mahasiswa_biodata',
            'sheet_name' => 'mahasiswa_biodata',
            'row_number' => 3,
            'operation' => 'insert',
            'natural_key' => 'nik=invalid',
            'raw_row' => [],
            'normalized_row' => [],
            'validation_result' => [
                'errors' => [
                    ['field' => 'id_agama', 'rule' => 'reference_exists', 'message' => 'Value referensi tidak ditemukan.'],
                ],
                'warnings' => [],
                'info' => [],
            ],
            'status' => 'invalid',
        ]);

        $this
            ->withToken($plainToken)
            ->postJson("/api/import-batches/{$batch->id}/dry-run")
            ->assertOk()
            ->assertJsonPath('data.summary.total_rows', 2)
            ->assertJsonPath('data.summary.valid_rows', 1)
            ->assertJsonPath('data.summary.invalid_rows', 1)
            ->assertJsonPath('data.requires_operator_approval', true)
            ->assertJsonPath('data.approved', false)
            ->assertJsonPath('data.payload_preview.0.candidate_operation', 'insert')
            ->assertJsonPath('data.payload_preview.0.action', 'InsertBiodataMahasiswa')
            ->assertJsonPath('data.payload_preview.1.candidate_operation', 'skip')
            ->assertJsonPath('data.missing_references.0.field', 'id_agama');

        $this->assertSame('dry_run_ready', $batch->refresh()->status);
    }

    /**
     * @return array{0: Tenant, 1: string}
     */
    private function tenantOperatorToken(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Kampus Contoh',
            'code' => 'KMP',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operator',
            'email' => 'operator@example.test',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'status' => 'active',
        ]);

        $plainToken = 'dry-run-token';

        ApiAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return [$tenant, $plainToken];
    }

    private function validBiodataRow(): array
    {
        return [
            'id_mahasiswa' => null,
            'nama_mahasiswa' => 'Mahasiswa Contoh',
            'jenis_kelamin' => 'L',
            'tempat_lahir' => 'Batam',
            'tanggal_lahir' => '2000-01-01',
            'id_agama' => 1,
            'nik' => '3201010101010001',
            'kewarganegaraan' => 'ID',
            'kelurahan' => 'Batam Kota',
            'id_wilayah' => '016000',
            'penerima_kps' => '0',
            'nama_ibu_kandung' => 'Ibu Contoh',
            'id_kebutuhan_khusus_mahasiswa' => 0,
            'id_kebutuhan_khusus_ayah' => 0,
            'id_kebutuhan_khusus_ibu' => 0,
        ];
    }
}
