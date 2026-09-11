<?php

namespace App\Services\Sync;

use App\Models\NeoFeederConnection;
use App\Models\StagingRecord;
use App\Models\SyncAttempt;
use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\NeoFeeder\Contracts\OperationContract;
use App\Services\NeoFeeder\NeoFeederClient;
use App\Services\NeoFeeder\NeoFeederCredentialVault;
use App\Services\NeoFeeder\Payloads\NeoFeederPayloadBuilder;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

final class NeoFeederRecordSyncService
{
    public function __construct(
        private readonly NeoFeederContractRegistry $registry,
        private readonly NeoFeederPayloadBuilder $payloadBuilder,
        private readonly NeoFeederClient $client,
        private readonly NeoFeederCredentialVault $credentialVault,
    ) {
    }

    public function sync(SyncAttempt $attempt): bool
    {
        $record = $attempt->stagingRecord;

        if (! $record instanceof StagingRecord) {
            throw new RuntimeException('Sync attempt does not have a staging record.');
        }

        $connection = $this->connection($record);
        $operation = $this->operationFor($record);

        if (! $operation instanceof OperationContract) {
            throw new RuntimeException("No sync operation found for channel [{$record->channel}].");
        }

        $payload = $this->requestPayloadFor($record);

        $attempt->forceFill([
            'status' => 'syncing',
            'action' => $operation->action,
            'request_payload' => ['act' => $operation->action, ...$payload],
            'attempted_at' => now(),
        ])->save();

        $record->forceFill(['status' => 'syncing'])->save();

        try {
            $token = $this->token($connection);
            $response = $this->client->post($connection->base_url, $operation->action, [
                'token' => $token,
                ...$payload,
            ]);
        } catch (RequestException $exception) {
            $attempt->forceFill([
                'status' => 'retrying',
                'error_code' => 'network_error',
                'error_desc' => $exception->getMessage(),
            ])->save();

            return true;
        }

        $identity = $this->identityPayload($operation, $response->data);
        $success = $response->successful();

        $attempt->forceFill([
            'status' => $success ? 'success' : 'failed',
            'response_payload' => $response->raw,
            'error_code' => $response->errorCode,
            'error_desc' => $response->errorDesc,
            'identity_payload' => $identity,
        ])->save();

        $record->forceFill([
            'status' => $success ? 'success' : 'failed',
            'validation_result' => [
                ...($record->validation_result ?? []),
                'sync' => [
                    'error_code' => $response->errorCode,
                    'error_desc' => $response->errorDesc,
                    'identity' => $identity,
                ],
            ],
        ])->save();

        return false;
    }

    public function actionFor(StagingRecord $record): ?string
    {
        return $this->operationFor($record)?->action;
    }

    public function requestPayloadFor(StagingRecord $record): array
    {
        $channel = $this->registry->channel($record->channel);
        $operation = $this->operationFor($record);

        if (! $channel instanceof ChannelContract || ! $operation instanceof OperationContract) {
            return [];
        }

        return $operation->type === 'update'
            ? $this->payloadBuilder->buildUpdate($channel, $operation, $record->normalized_row ?? [])
            : $this->payloadBuilder->buildInsert($channel, $operation, $record->normalized_row ?? []);
    }

    private function operationFor(StagingRecord $record): ?OperationContract
    {
        $channel = $this->registry->channel($record->channel);

        if (! $channel instanceof ChannelContract) {
            return null;
        }

        $row = $record->normalized_row ?? [];
        $type = $channel->identityFields !== [] && collect($channel->identityFields)->every(fn (string $field): bool => filled($row[$field] ?? null))
            ? 'update'
            : 'insert';

        foreach ($channel->operations as $payload) {
            $operation = OperationContract::fromArray($payload);

            if ($operation->type === $type) {
                return $operation;
            }
        }

        return null;
    }

    private function connection(StagingRecord $record): NeoFeederConnection
    {
        return NeoFeederConnection::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('status', 'active')
            ->latest()
            ->firstOrFail();
    }

    private function token(NeoFeederConnection $connection): string
    {
        $password = $this->credentialVault->decryptPassword($connection->encrypted_password);

        if ($connection->username === null || $password === null) {
            throw new RuntimeException('Neo Feeder credential is incomplete.');
        }

        $response = $this->client->getToken($connection->base_url, $connection->username, $password);
        $token = is_array($response->data) ? ($response->data['token'] ?? null) : null;

        if (! $response->successful() || ! is_string($token) || $token === '') {
            throw new RuntimeException('Unable to refresh Neo Feeder token.');
        }

        $connection->forceFill(['last_token_refreshed_at' => now()])->save();

        return $token;
    }

    private function identityPayload(OperationContract $operation, mixed $data): array
    {
        if (! is_array($data)) {
            return [];
        }

        $source = array_is_list($data) ? ($data[0] ?? []) : $data;
        $identity = [];

        foreach ($operation->responseFields as $field) {
            if (array_key_exists($field, $source)) {
                $identity[$field] = $source[$field];
            }
        }

        return $identity;
    }
}
