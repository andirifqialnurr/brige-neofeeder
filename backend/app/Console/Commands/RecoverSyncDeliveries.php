<?php

namespace App\Console\Commands;

use App\Jobs\SyncStagingRecordJob;
use App\Models\ImportBatch;
use App\Models\SyncAttempt;
use App\Services\Sync\NeoFeederRecordSyncService;
use Illuminate\Console\Command;

class RecoverSyncDeliveries extends Command
{
    protected $signature = 'bridge:recover-sync';

    protected $description = 'Recover persisted sync work without replaying ambiguous writes';

    public function handle(NeoFeederRecordSyncService $service): int
    {
        foreach (ImportBatch::whereNotNull('sync_started_at')->where('status', 'syncing')->with('tenant')->cursor() as $batch) {
            if (($batch->tenant->metadata['demo'] ?? false) === true) {
                continue;
            }
            $attempts = fn () => SyncAttempt::whereHas('stagingRecord', fn ($query) => $query->where('import_batch_id', $batch->id));
            foreach ($attempts()->where('status', 'syncing')->where('attempted_at', '<=', now()->subMinutes(10))->get() as $attempt) {
                $service->failSafely($attempt->id, now()->subMinutes(10));
            }
            if ($attempts()->where('status', 'syncing')->exists()) {
                continue;
            }
            $next = $attempts()->whereIn('status', ['queued', 'retrying'])->where('updated_at', '<=', now()->subMinutes(2))->orderBy('id')->first();
            if ($next) {
                SyncStagingRecordJob::dispatch($next->id);
            }
        }

        return self::SUCCESS;
    }
}
