<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\SyncAttempt;
use App\Models\User;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class ImportBatchSyncPlanner
{
    public function __construct(
        private readonly NeoFeederRecordSyncService $recordSyncService,
        private readonly ImportBatchApprovalService $approval,
        private readonly NeoFeederContractRegistry $registry,
    ) {}

    public function plan(ImportBatch $batch, ?User $actor = null): Collection
    {
        return DB::transaction(function () use ($batch, $actor) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            $batch->tenant->assertLiveIntegrationAllowed();
            $this->approval->assertApproved($batch);
            abort_unless($this->hasCredentials($batch), 409, 'Koneksi aktif dan credential Neo Feeder diperlukan.');
            if ($batch->sync_started_at) {
                // A failed queue dispatch can be replayed without creating another delivery intent.
                return $this->attempts($batch)->where('status', 'queued')->orderBy('id')->get();
            }
            abort_unless($batch->status === 'dry_run_ready', 409, 'Batch belum siap dikirim.');
            abort_if($batch->stagingRecords()->where('status', '!=', 'valid')->exists(), 409, 'Seluruh baris harus valid.');
            abort_if($this->attempts($batch)->exists(), 409, 'Batch memiliki riwayat pengiriman lama. Tinjau sebelum melanjutkan.');
            $order = array_flip($this->registry->dependencyOrder());
            $records = $batch->stagingRecords()->orderBy('row_number')->orderBy('id')->get()
                ->sortBy(fn ($row) => $order[$row->channel] ?? PHP_INT_MAX);
            abort_if($records->isEmpty(), 409, 'Batch tidak memiliki baris.');
            $attempts = collect();
            foreach ($records as $record) {
                $action = $this->recordSyncService->actionFor($record);
                abort_unless($action, 409, 'Kanal tidak memiliki operasi pengiriman.');
                $attempts->push(SyncAttempt::create([
                    'tenant_id' => $batch->tenant_id, 'staging_record_id' => $record->id,
                    'action' => $action, 'status' => 'queued',
                    'approval_hash' => $batch->approved_hash,
                    'idempotency_key' => hash('sha256', $batch->approved_hash.':'.$record->id),
                    'request_payload' => ['act' => $action, ...$this->recordSyncService->requestPayloadFor($record)],
                ]));
                $record->forceFill(['status' => 'ready'])->save();
            }
            $batch->forceFill(['status' => 'syncing', 'sync_started_at' => now()])->save();
            $this->audit($batch, $actor, 'import.sync.started', ['records' => $attempts->count()]);

            return $attempts;
        });
    }

    public function retry(SyncAttempt $attempt, User $actor): SyncAttempt
    {
        return DB::transaction(function () use ($attempt, $actor) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($attempt->stagingRecord->import_batch_id);
            $batch->tenant->assertLiveIntegrationAllowed();
            $this->approval->assertApproved($batch);
            abort_unless($this->hasCredentials($batch), 409, 'Koneksi aktif dan credential Neo Feeder diperlukan.');
            $attempt = SyncAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            if ($existing = SyncAttempt::where('retry_of', $attempt->id)->first()) {
                return $existing;
            }
            abort_unless($this->canRetry($attempt), 409, 'Attempt ini tidak aman atau tidak lagi memenuhi syarat untuk retry.');
            $retry = SyncAttempt::create([
                'tenant_id' => $attempt->tenant_id, 'staging_record_id' => $attempt->staging_record_id,
                'action' => $attempt->action, 'status' => 'queued', 'request_payload' => $attempt->request_payload,
                'approval_hash' => $batch->approved_hash, 'retry_of' => $attempt->id,
                'idempotency_key' => hash('sha256', 'retry:'.$attempt->id),
            ]);
            $attempt->stagingRecord->forceFill(['status' => 'ready'])->save();
            $batch->forceFill(['status' => 'syncing'])->save();
            $this->audit($batch, $actor, 'import.sync.retried', ['previous_attempt_id' => $attempt->id, 'attempt_id' => $retry->id]);

            return $retry;
        });
    }

    public function canRetry(SyncAttempt $attempt): bool
    {
        if ($attempt->status !== 'failed' || ! $attempt->retry_safe || ! $attempt->approval_hash) {
            return false;
        }
        $row = $attempt->stagingRecord;
        if (! $row || $row->status === 'success') {
            return false;
        }
        $dependencies = $this->registry->channel($row->channel)?->dependsOn ?? [];
        if ($row->importBatch->stagingRecords()->whereIn('channel', $dependencies)->where('status', '!=', 'success')->exists()) {
            return false;
        }

        return $row->syncAttempts()->orderByDesc('created_at')->orderByDesc('id')->value('id') === $attempt->id;
    }

    public function progress(ImportBatch $batch): array
    {
        $batch->refresh();
        $attempts = $this->attempts($batch)->get();
        $latest = $attempts->sortByDesc('id')->unique('staging_record_id');
        $active = $latest->whereIn('status', ['queued', 'syncing', 'retrying'])->count();

        return [
            'import_batch_id' => $batch->id, 'status' => $batch->status,
            'started_at' => $batch->sync_started_at, 'total_attempts' => $attempts->count(),
            'queued' => $attempts->where('status', 'queued')->count(),
            'syncing' => $attempts->where('status', 'syncing')->count(),
            'success' => $attempts->where('status', 'success')->count(),
            'failed' => $attempts->where('status', 'failed')->count(),
            'retrying' => $attempts->where('status', 'retrying')->count(),
            'unknown' => $attempts->where('status', 'unknown')->count(),
            'records' => ['total' => $latest->count(), 'success' => $latest->where('status', 'success')->count(),
                'failed' => $latest->where('status', 'failed')->count(), 'unknown' => $latest->where('status', 'unknown')->count(), 'active' => $active],
            'has_credentials' => $this->hasCredentials($batch),
            'is_demo' => ($batch->tenant->metadata['demo'] ?? false) === true,
        ];
    }

    private function attempts(ImportBatch $batch)
    {
        return SyncAttempt::query()->whereHas('stagingRecord', fn ($query) => $query->where('import_batch_id', $batch->id));
    }

    private function hasCredentials(ImportBatch $batch): bool
    {
        return NeoFeederConnection::where('tenant_id', $batch->tenant_id)->where('status', 'active')
            ->whereNotNull('encrypted_password')->where('encrypted_password', '!=', '')
            ->whereNotNull('username')->where('username', '!=', '')->exists();
    }

    private function audit(ImportBatch $batch, ?User $actor, string $event, array $metadata): void
    {
        AuditLog::create(['tenant_id' => $batch->tenant_id, 'actor_id' => $actor?->id,
            'event' => $event, 'subject_type' => ImportBatch::class, 'subject_id' => $batch->id, 'metadata' => $metadata]);
    }
}
