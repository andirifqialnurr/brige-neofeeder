<?php

namespace App\Jobs;

use App\Models\NeoFeederConnection;
use App\Services\NeoFeeder\NeoFeederClient;
use App\Services\NeoFeeder\NeoFeederCredentialVault;
use App\Services\NeoFeeder\References\NeoFeederReferenceSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;

class RefreshNeoFeederReferenceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $endpoint,
    ) {
    }

    public function handle(
        NeoFeederClient $client,
        NeoFeederCredentialVault $credentialVault,
        NeoFeederReferenceSyncService $referenceSyncService,
    ): void
    {
        $connection = NeoFeederConnection::query()
            ->where('tenant_id', $this->tenantId)
            ->where('status', '!=', 'inactive')
            ->latest()
            ->first();

        if (! $connection instanceof NeoFeederConnection) {
            throw new RuntimeException('Neo Feeder connection is not configured.');
        }

        if ($connection->username === null || $connection->encrypted_password === null) {
            throw new RuntimeException('Neo Feeder username and password are required.');
        }

        $password = $credentialVault->decryptPassword($connection->encrypted_password);

        if ($password === null) {
            throw new RuntimeException('Neo Feeder password is not configured.');
        }

        $tokenResponse = $client->getToken($connection->base_url, $connection->username, $password);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException($tokenResponse->errorDesc ?: 'Neo Feeder token request failed.');
        }

        $token = $this->extractToken($tokenResponse->data);

        if ($token === null) {
            throw new RuntimeException('Neo Feeder token response does not contain a token.');
        }

        $referenceResponse = $client->post($connection->base_url, $this->endpoint, [
            'token' => $token,
        ]);

        if (! $referenceResponse->successful()) {
            throw new RuntimeException($referenceResponse->errorDesc ?: "Reference sync failed for {$this->endpoint}.");
        }

        $syncedRows = $referenceSyncService->sync($this->tenantId, $this->endpoint, $referenceResponse->raw);

        $connection->forceFill([
            'last_token_refreshed_at' => now(),
            'metadata' => [
                ...($connection->metadata ?? []),
                'last_reference_sync' => [
                    'endpoint' => $this->endpoint,
                    'rows' => $syncedRows,
                    'synced_at' => now()->toISOString(),
                ],
            ],
        ])->save();
    }

    private function extractToken(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $token = $data['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }
}
