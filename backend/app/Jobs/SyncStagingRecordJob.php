<?php

namespace App\Jobs;

use App\Models\SyncAttempt;
use App\Services\Sync\NeoFeederRecordSyncService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SyncStagingRecordJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $syncAttemptId,
    ) {
    }

    public function handle(NeoFeederRecordSyncService $syncService): void
    {
        $attempt = SyncAttempt::query()->with('stagingRecord')->findOrFail($this->syncAttemptId);

        try {
            $retryable = $syncService->sync($attempt);

            if ($retryable && $this->attempts() < $this->tries) {
                $this->release(60);
            }
        } catch (Throwable $exception) {
            $attempt->forceFill([
                'status' => 'retrying',
                'error_code' => 'runtime_error',
                'error_desc' => $exception->getMessage(),
                'attempted_at' => now(),
            ])->save();

            if ($this->attempts() < $this->tries) {
                $this->release(60);
            }
        }
    }
}
