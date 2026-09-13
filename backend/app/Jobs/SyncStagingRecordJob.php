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

    public int $tries = 10;

    public int $timeout = 75;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $syncAttemptId,
    ) {}

    public function handle(NeoFeederRecordSyncService $syncService): void
    {
        $attempt = SyncAttempt::query()->with('stagingRecord')->findOrFail($this->syncAttemptId);

        try {
            $retryable = $syncService->sync($attempt);

            if ($retryable) {
                if ($this->attempts() < $this->tries) {
                    $this->release(15);
                } else {
                    $syncService->failSafely($attempt->id);
                }
            }
        } catch (Throwable $exception) {
            $syncService->failSafely($attempt->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        app(NeoFeederRecordSyncService::class)->failSafely($this->syncAttemptId);
    }
}
