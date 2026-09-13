<?php

namespace App\Services\Sync;

use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\StagingRecord;
use App\Models\SyncAttempt;
use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\NeoFeeder\Contracts\OperationContract;
use App\Services\NeoFeeder\NeoFeederClient;
use App\Services\NeoFeeder\NeoFeederCredentialVault;
use App\Services\NeoFeeder\Payloads\NeoFeederPayloadBuilder;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class NeoFeederRecordSyncService
{
    public function __construct(
        private readonly NeoFeederContractRegistry $registry,
        private readonly NeoFeederPayloadBuilder $payloadBuilder,
        private readonly NeoFeederClient $client,
        private readonly NeoFeederCredentialVault $credentialVault,
        private readonly ImportBatchApprovalService $approval,
    ) {}

    public function sync(SyncAttempt $attempt): bool
    {
        $record = $attempt->stagingRecord;
        if (! $record instanceof StagingRecord) {
            throw new RuntimeException('Sync attempt does not have a staging record.');
        }
        $record->importBatch->tenant->assertLiveIntegrationAllowed();
        $claim = DB::transaction(function () use ($attempt, $record) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($record->import_batch_id);
            $fresh = SyncAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! in_array($fresh->status, ['queued', 'retrying'], true)) {
                return 'done';
            }
            $this->approval->assertApproved($batch);
            abort_unless($fresh->approval_hash === $batch->approved_hash, 409, 'Attempt tidak terikat pada persetujuan aktif.');
            $record->refresh();
            if ($record->status === 'success') {
                $fresh->forceFill(['status' => 'skipped', 'completed_at' => now()])->save();

                return 'done';
            }
            $dependencies = $this->registry->channel($record->channel)?->dependsOn ?? [];
            $parents = $batch->stagingRecords()->whereIn('channel', $dependencies);
            if ((clone $parents)->whereIn('status', ['valid', 'ready', 'syncing', 'pending'])->exists()) {
                $fresh->forceFill(['status' => 'retrying', 'error_code' => 'dependency_waiting', 'error_desc' => 'Menunggu kanal dependensi selesai.'])->save();

                return 'wait';
            }
            if ((clone $parents)->where('status', '!=', 'success')->exists()) {
                $this->finish($fresh, 'failed', 'dependency_blocked', 'Kanal dependensi belum berhasil. Selesaikan dependensi sebelum retry.', true);

                return 'done';
            }
            $expected = ['act' => $this->actionFor($record), ...$this->requestPayloadFor($record)];
            abort_unless($fresh->request_payload == $expected && $fresh->action === $expected['act'], 409, 'Payload attempt berbeda dari data yang disetujui.');
            $fresh->forceFill(['status' => 'syncing', 'attempted_at' => now(), 'execution_count' => $fresh->execution_count + 1, 'retry_safe' => false])->save();
            $record->forceFill(['status' => 'syncing'])->save();

            return 'claimed';
        });
        if ($claim !== 'claimed') {
            return $claim === 'wait';
        }

        $attempt->refresh();
        try {
            $connection = $this->connection($record);
            $token = $this->token($connection);
        } catch (ConnectionException|RequestException $exception) {
            if ($attempt->execution_count < 3) {
                $attempt->forceFill(['status' => 'retrying', 'error_code' => 'token_network', 'error_desc' => 'Koneksi autentikasi gagal; data belum dikirim.'])->save();
                $record->forceFill(['status' => 'ready'])->save();

                return true;
            }
            $this->finish($attempt, 'failed', 'token_network', 'Autentikasi gagal setelah tiga percobaan; data belum dikirim.', true);

            return false;
        } catch (Throwable $exception) {
            $this->finish($attempt, 'failed', 'authentication_failed', 'Periksa koneksi dan credential Neo Feeder. Data belum dikirim.', true);

            return false;
        }

        // Persist this boundary before the write. A timeout beyond it is never retried blindly.
        $entered = SyncAttempt::whereKey($attempt->id)->where('status', 'syncing')->whereNull('request_started_at')
            ->where('execution_count', $attempt->execution_count)->update(['request_started_at' => now()]);
        if ($entered !== 1) {
            return false;
        }
        $payload = $attempt->request_payload;
        unset($payload['act'], $payload['token']);
        try {
            $response = $this->client->post($connection->base_url, $attempt->action, ['token' => $token, ...$payload]);
        } catch (Throwable $exception) {
            $this->finish($attempt, 'unknown', 'delivery_unknown', 'Hasil pengiriman belum diketahui. Periksa Neo Feeder sebelum mengambil tindakan.', false);

            return false;
        }
        if ($response->errorCode === '') {
            $this->finish($attempt, 'unknown', 'invalid_response', 'Respons Neo Feeder tidak dapat dipastikan. Retry otomatis diblokir.', false);

            return false;
        }
        $operation = $this->operationFor($record);
        $identity = $this->identityPayload($operation, $response->data);
        if ($response->successful() && $operation->type === 'insert' && $operation->responseFields !== [] && $identity === []) {
            $this->finish($attempt, 'unknown', 'missing_identity', 'Neo Feeder melaporkan berhasil tanpa ID hasil. Perlu pemeriksaan sebelum melanjutkan.', false, $response->raw);

            return false;
        }
        $this->finish($attempt, $response->successful() ? 'success' : 'failed',
            $response->errorCode, $response->errorDesc, ! $response->successful(), $response->raw, $identity);

        return false;
    }

    public function failSafely(string $id, ?CarbonInterface $staleBefore = null): void
    {
        $attempt = SyncAttempt::find($id);
        if (! $attempt || ! $attempt->stagingRecord) {
            return;
        }
        DB::transaction(function () use ($attempt, $staleBefore) {
            ImportBatch::query()->lockForUpdate()->findOrFail($attempt->stagingRecord->import_batch_id);
            $attempt = SyncAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if (! in_array($attempt->status, ['queued', 'retrying', 'syncing'], true)) {
                return;
            }
            if ($staleBefore && ($attempt->status !== 'syncing' || ! $attempt->attempted_at || $attempt->attempted_at->gt($staleBefore))) {
                return;
            }
            $unknown = $attempt->request_started_at !== null;
            $this->finish($attempt, $unknown ? 'unknown' : 'failed', $unknown ? 'delivery_unknown' : 'worker_stopped',
                $unknown ? 'Worker berhenti setelah pengiriman dimulai. Periksa hasil di Neo Feeder.' : 'Worker berhenti sebelum pengiriman. Periksa konfigurasi sebelum retry.',
                ! $unknown && $attempt->approval_hash !== null);
        });
    }

    private function finish(SyncAttempt $attempt, string $status, string $code, string $description, bool $retrySafe, ?array $response = null, array $identity = []): void
    {
        DB::transaction(function () use ($attempt, $status, $code, $description, $retrySafe, $response, $identity) {
            $row = $attempt->stagingRecord;
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($row->import_batch_id);
            $attempt->refresh();
            if (in_array($attempt->status, ['success', 'skipped'], true)) {
                return;
            }
            $attempt->forceFill(['status' => $status, 'error_code' => $code, 'error_desc' => $description,
                'retry_safe' => $retrySafe, 'response_payload' => $response, 'identity_payload' => $identity, 'completed_at' => now()])->save();
            $row->forceFill(['status' => $status === 'success' ? 'success' : 'failed', 'validation_result' => [
                ...($row->validation_result ?? []), 'sync' => ['error_code' => $code, 'error_desc' => $description, 'identity' => $identity],
            ]])->save();
            $latest = SyncAttempt::whereHas('stagingRecord', fn ($query) => $query->where('import_batch_id', $batch->id))
                ->orderByDesc('id')->get()->unique('staging_record_id');
            $active = $latest->whereIn('status', ['queued', 'retrying', 'syncing'])->isNotEmpty();
            $failed = $latest->whereNotIn('status', ['success', 'skipped'])->isNotEmpty();
            $batch->forceFill(['status' => $active ? 'syncing' : ($failed ? 'failed' : 'synced')])->save();
        });
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
        if (! is_array($source)) {
            return [];
        }
        $identity = [];

        foreach ($operation->responseFields as $field) {
            if (array_key_exists($field, $source) && filled($source[$field])) {
                $identity[$field] = $source[$field];
            }
        }

        return $identity;
    }
}
