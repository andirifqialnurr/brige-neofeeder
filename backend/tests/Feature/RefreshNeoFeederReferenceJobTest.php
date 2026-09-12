<?php

namespace Tests\Feature;

use App\Jobs\RefreshNeoFeederReferenceJob;
use App\Models\NeoFeederConnection;
use App\Models\ReferenceRecord;
use App\Models\Tenant;
use App\Services\NeoFeeder\NeoFeederClient;
use App\Services\NeoFeeder\NeoFeederCredentialVault;
use App\Services\NeoFeeder\References\NeoFeederReferenceSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class RefreshNeoFeederReferenceJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_refreshes_reference_records_from_neofeeder_response(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Kampus Contoh',
            'code' => 'KMP',
            'status' => 'active',
        ]);

        NeoFeederConnection::query()->create([
            'tenant_id' => $tenant->id,
            'base_url' => 'https://neofeeder.example.test/ws/live2.php',
            'username' => 'neo-user',
            'encrypted_password' => app(NeoFeederCredentialVault::class)->encryptPassword('neo-pass'),
            'status' => 'active',
            'metadata' => [],
        ]);

        Http::fakeSequence()
            ->push([
                'error_code' => '0',
                'error_desc' => '',
                'data' => ['token' => 'token-123'],
            ])
            ->push([
                'error_code' => '0',
                'error_desc' => '',
                'data' => [
                    [
                        'id_prodi' => 'prodi-1',
                        'kode_program_studi' => '55201',
                        'nama_program_studi' => 'Teknik Informatika',
                    ],
                ],
            ]);

        (new RefreshNeoFeederReferenceJob($tenant->id, 'GetProdi'))->handle(
            app(NeoFeederClient::class),
            app(NeoFeederCredentialVault::class),
            app(NeoFeederReferenceSyncService::class),
        );

        $record = ReferenceRecord::query()->firstOrFail();

        $this->assertSame($tenant->id, $record->tenant_id);
        $this->assertSame('GetProdi', $record->endpoint);
        $this->assertSame('id_prodi', $record->value_key);
        $this->assertSame('prodi-1', $record->value);
        $this->assertSame('Teknik Informatika', $record->label);

        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool => $request['act'] === 'GetToken'
            && $request['username'] === 'neo-user'
            && $request['password'] === 'neo-pass');
        Http::assertSent(fn ($request): bool => $request['act'] === 'GetProdi'
            && $request['token'] === 'token-123');
    }
}
