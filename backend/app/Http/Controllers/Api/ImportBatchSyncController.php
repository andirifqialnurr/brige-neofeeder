<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SyncStagingRecordJob;
use App\Models\ImportBatch;
use App\Models\SyncAttempt;
use App\Models\User;
use App\Services\Operations\SensitiveData;
use App\Services\Sync\ImportBatchSyncPlanner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\Rule;

class ImportBatchSyncController extends Controller
{
    public function start(Request $request, ImportBatch $importBatch, ImportBatchSyncPlanner $planner): JsonResponse
    {
        if (! $this->canAccess($request, $importBatch)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $attempts = $planner->plan($importBatch, $request->user());

        if ($attempts->isNotEmpty()) {
            Bus::chain($attempts->map(fn ($attempt) => new SyncStagingRecordJob($attempt->id))->all())->dispatch();
        }

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
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function attempts(Request $request, ImportBatch $importBatch, ImportBatchSyncPlanner $planner): JsonResponse
    {
        abort_unless($this->canAccess($request, $importBatch), 403);
        $input = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'status' => ['nullable', Rule::in(['queued', 'retrying', 'syncing', 'success', 'failed', 'unknown', 'skipped'])],
        ]);
        $page = SyncAttempt::whereHas('stagingRecord', fn ($query) => $query->where('import_batch_id', $importBatch->id))
            ->with('stagingRecord.importBatch')->when($input['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('created_at')->orderByDesc('id')->paginate(25);
        $demo = ($importBatch->tenant->metadata['demo'] ?? false) === true;

        return response()->json([
            'data' => $page->getCollection()->map(fn ($attempt) => [
                ...$attempt->only(['id', 'staging_record_id', 'action', 'status', 'error_code', 'error_desc', 'identity_payload', 'created_at', 'attempted_at', 'completed_at', 'retry_of']),
                'sheet_name' => $attempt->stagingRecord->sheet_name,
                'error_desc' => app(SensitiveData::class)->present($attempt->error_desc, false, 'error_desc'),
                'row_number' => $attempt->stagingRecord->row_number,
                'can_retry' => ! $demo && (bool) $importBatch->approved_at && $planner->canRetry($attempt),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ], 200, ['Cache-Control' => 'no-store']);
    }

    public function retry(Request $request, SyncAttempt $syncAttempt, ImportBatchSyncPlanner $planner): JsonResponse
    {
        $syncAttempt->load('stagingRecord.importBatch');

        if (! $syncAttempt->stagingRecord || ! $this->canAccess($request, $syncAttempt->stagingRecord->importBatch)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $retry = $planner->retry($syncAttempt, $request->user());
        if ($retry->status === 'queued') {
            SyncStagingRecordJob::dispatch($retry->id);
        }

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
