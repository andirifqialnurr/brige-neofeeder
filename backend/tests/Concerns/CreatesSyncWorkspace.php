<?php

namespace Tests\Concerns;

use App\Models\ApiAccessToken;
use App\Models\ImportBatch;
use App\Models\StagingRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;

trait CreatesSyncWorkspace
{
    protected function syncWorkspace(): array
    {
        $tenant = Tenant::create(['name' => 'Kampus Test', 'code' => Str::random(8), 'status' => 'active']);
        $user = User::create(['tenant_id' => $tenant->id, 'name' => 'Operator Test', 'email' => Str::uuid().'@example.test', 'password' => 'not-a-login-password', 'role' => 'operator', 'status' => 'active']);
        $token = Str::random(40);
        ApiAccessToken::create(['user_id' => $user->id, 'name' => 'test', 'token_hash' => hash('sha256', $token)]);
        $batch = ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'status' => 'validated']);
        $row = StagingRecord::create([
            'tenant_id' => $tenant->id, 'import_batch_id' => $batch->id,
            'channel' => 'mahasiswa_biodata', 'sheet_name' => 'mahasiswa_biodata', 'row_number' => 2,
            'operation' => 'insert', 'status' => 'valid', 'raw_row' => [],
            'normalized_row' => [
                'nama_mahasiswa' => 'Mahasiswa Fiktif', 'jenis_kelamin' => 'L', 'tempat_lahir' => 'Batam',
                'tanggal_lahir' => '2000-01-01', 'nik' => '0000000000000000', 'id_agama' => 1,
                'kewarganegaraan' => 'ID', 'kelurahan' => 'Batam Kota', 'id_wilayah' => '016000',
                'penerima_kps' => '0', 'nama_ibu_kandung' => 'Ibu Fiktif',
                'id_kebutuhan_khusus_mahasiswa' => 0, 'id_kebutuhan_khusus_ayah' => 0, 'id_kebutuhan_khusus_ibu' => 0,
            ],
            'validation_result' => ['errors' => [], 'warnings' => [], 'info' => []],
        ]);

        return [$batch, $row, $user, $token];
    }

    protected function approveSyncBatch(ImportBatch $batch, string $token): string
    {
        $hash = $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/dry-run")->assertOk()->json('data.dry_run_hash');
        $this->withToken($token)->postJson("/api/import-batches/{$batch->id}/approve", ['confirmed' => true, 'dry_run_hash' => $hash])->assertOk();

        return $hash;
    }
}
