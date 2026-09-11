<?php

namespace Tests\Feature;

use App\Models\ApiAccessToken;
use App\Models\ReferenceRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ReferenceStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_reference_status_returns_endpoint_summary_for_authenticated_tenant(): void
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

        $plainToken = 'reference-status-token';

        ApiAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        ReferenceRecord::query()->create([
            'tenant_id' => $tenant->id,
            'endpoint' => 'GetProdi',
            'value_key' => 'id_prodi',
            'value' => 'prodi-1',
            'label' => 'Informatika',
            'raw_payload' => ['id_prodi' => 'prodi-1', 'nama_program_studi' => 'Informatika'],
            'synced_at' => '2026-09-11 10:00:00',
        ]);

        $endpointCount = count(config('neofeeder-contracts.channels.references.operations', []));

        $this
            ->withToken($plainToken)
            ->getJson('/api/references/status')
            ->assertOk()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.endpoint_count', $endpointCount)
            ->assertJsonPath('data.synced_endpoint_count', 1)
            ->assertJsonPath('data.failed_endpoint_count', $endpointCount - 1)
            ->assertJsonPath('data.total_rows', 1)
            ->assertJsonPath('data.endpoints.1.endpoint', 'GetProdi')
            ->assertJsonPath('data.endpoints.1.total_rows', 1)
            ->assertJsonPath('data.endpoints.1.status', 'synced');
    }
}
