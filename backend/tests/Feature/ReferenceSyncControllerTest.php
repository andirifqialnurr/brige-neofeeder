<?php

namespace Tests\Feature;

use App\Jobs\RefreshNeoFeederReferenceJob;
use App\Models\ApiAccessToken;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ReferenceSyncControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_queue_reference_sync_for_selected_endpoint(): void
    {
        Queue::fake();

        $tenant = Tenant::query()->create([
            'name' => 'Kampus Contoh',
            'code' => 'KMP',
            'status' => 'active',
        ]);
        $plainToken = $this->adminToken();

        $this
            ->withToken($plainToken)
            ->postJson('/api/references/sync', [
                'tenant_id' => $tenant->id,
                'endpoint' => 'GetProdi',
            ])
            ->assertAccepted()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.queued_endpoint_count', 1)
            ->assertJsonPath('data.endpoints.0', 'GetProdi');

        Queue::assertPushed(
            RefreshNeoFeederReferenceJob::class,
            fn (RefreshNeoFeederReferenceJob $job): bool => $job->tenantId === $tenant->id && $job->endpoint === 'GetProdi',
        );
    }

    private function adminToken(): string
    {
        $user = User::query()->create([
            'tenant_id' => null,
            'name' => 'Admin',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'admin',
            'status' => 'active',
        ]);

        $plainToken = 'reference-sync-token-'.str()->random(8);

        ApiAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return $plainToken;
    }
}
