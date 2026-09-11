<?php

namespace Tests\Feature;

use App\Models\ApiAccessToken;
use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\StagingRecord;
use App\Models\SyncAttempt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ImportBatchSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_start_sync_and_store_attempt_result(): void
    {
        [$tenant, $plainToken] = $this->tenantOperatorToken();
        $batch = ImportBatch::query()->create([
            'tenant_id' => $tenant->id,
            'source_type' => 'excel',
            'status' => 'dry_run_ready',
        ]);

        NeoFeederConnection::query()->create([
            'tenant_id' => $tenant->id,
            'base_url' => 'https://neo.test/ws/live2.php',
            'username' => 'user',
            'encrypted_password' => Crypt::encryptString('secret'),
            'status' => 'active',
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

        Http::fake([
            'https://neo.test/ws/live2.php' => Http::sequence()
                ->push(['error_code' => '0', 'error_desc' => '', 'data' => ['token' => 'token-1']])
                ->push(['error_code' => '0', 'error_desc' => '', 'data' => ['id_mahasiswa' => 'mhs-1']]),
        ]);

        $this
            ->withToken($plainToken)
            ->postJson("/api/import-batches/{$batch->id}/sync")
            ->assertOk()
            ->assertJsonPath('data.queued_attempts', 1)
            ->assertJsonPath('data.progress.success', 1);

        $attempt = SyncAttempt::query()->firstOrFail();

        $this->assertSame('InsertBiodataMahasiswa', $attempt->action);
        $this->assertSame('success', $attempt->status);
        $this->assertSame('0', $attempt->error_code);
        $this->assertSame('mhs-1', $attempt->identity_payload['id_mahasiswa']);
        $this->assertSame('success', StagingRecord::query()->firstOrFail()->status);
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
            'email' => 'sync@example.test',
            'password' => Hash::make('password'),
            'role' => 'operator',
            'status' => 'active',
        ]);

        $plainToken = 'sync-token';

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
