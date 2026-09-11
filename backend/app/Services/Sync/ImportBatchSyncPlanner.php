<?php

namespace App\Services\Sync;

use App\Models\ImportBatch;
use App\Models\SyncAttempt;
use Illuminate\Support\Collection;

final class ImportBatchSyncPlanner
{
    public function __construct(
        private readonly NeoFeederRecordSyncService $recordSyncService,
    ) {
    }

    /**
     * @return Collection<int, SyncAttempt>
     */
    public function plan(ImportBatch $batch): Collection
    {
        $attempts = collect();
        $records = $batch->stagingRecords()
            ->where('status', 'valid')
            ->orderBy('channel')
            ->orderBy('row_number')
            ->get();

        foreach ($records as $record) {
            $action = $this->recordSyncService->actionFor($record);

            if ($action === null) {
                continue;
            }

            $attempts->push(SyncAttempt::query()->create([
                'tenant_id' => $record->tenant_id,
                'staging_record_id' => $record->id,
                'action' => $action,
                'status' => 'queued',
                'request_payload' => $this->recordSyncService->requestPayloadFor($record),
            ]));
        }

        return $attempts;
    }

    public function progress(ImportBatch $batch): array
    {
        $attempts = SyncAttempt::query()
            ->whereHas('stagingRecord', fn ($query) => $query->where('import_batch_id', $batch->id))
            ->get();

        return [
            'import_batch_id' => $batch->id,
            'total_attempts' => $attempts->count(),
            'queued' => $attempts->where('status', 'queued')->count(),
            'syncing' => $attempts->where('status', 'syncing')->count(),
            'success' => $attempts->where('status', 'success')->count(),
            'failed' => $attempts->where('status', 'failed')->count(),
            'retrying' => $attempts->where('status', 'retrying')->count(),
        ];
    }
}
