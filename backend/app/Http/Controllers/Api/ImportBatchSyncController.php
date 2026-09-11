<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncStagingRecordJob;
use App\Models\ImportBatch;
use App\Models\SyncAttempt;
use App\Models\User;
use App\Services\Sync\ImportBatchSyncPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchSyncController extends Controller
{
    public function start(Request $request, ImportBatch $importBatch, ImportBatchSyncPlanner $planner): JsonResponse
    {
        if (! $this->canAccess($request, $importBatch)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $attempts = $planner->plan($importBatch);

        foreach ($attempts as $attempt) {
            SyncStagingRecordJob::dispatch($attempt->id);
        }

        $importBatch->forceFill(['status' => 'syncing'])->save();

        return response()->json([
            'data' => [
                'import_batch_id' => $importBatch->id,
                'queued_attempts' => $attempts->count(),
                'progress' => $planner->progress($importBatch),
            ],
        ]);
    }

    public function progress(Request $request, ImportBatch $importBatch, ImportBatchSyncPlanner $planner): JsonResponse
    {
        if (! $this->canAccess($request, $importBatch)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $planner->progress($importBatch),
        ]);
    }

    public function retry(Request $request, SyncAttempt $syncAttempt): JsonResponse
    {
        $syncAttempt->load('stagingRecord.importBatch');

        if (! $syncAttempt->stagingRecord || ! $this->canAccess($request, $syncAttempt->stagingRecord->importBatch)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $retry = SyncAttempt::query()->create([
            'tenant_id' => $syncAttempt->tenant_id,
            'staging_record_id' => $syncAttempt->staging_record_id,
            'action' => $syncAttempt->action,
            'status' => 'queued',
            'request_payload' => $syncAttempt->request_payload,
        ]);

        SyncStagingRecordJob::dispatch($retry->id);

        return response()->json([
            'data' => [
                'id' => $retry->id,
                'status' => $retry->status,
            ],
        ], 202);
    }

    private function canAccess(Request $request, ImportBatch $batch): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isAdmin() || $user->tenant_id === $batch->tenant_id;
    }
}
