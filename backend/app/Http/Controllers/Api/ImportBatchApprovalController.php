<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Services\Sync\ImportBatchApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchApprovalController extends Controller
{
    public function __invoke(Request $request, ImportBatch $importBatch, ImportBatchApprovalService $approval): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->tenant_id && $user->tenant_id === $importBatch->tenant_id), 403);
        $input = $request->validate(['confirmed' => 'required|accepted', 'dry_run_hash' => 'required|string|size:64|regex:/^[a-f0-9]+$/']);
        $batch = $approval->approve($importBatch, $user, $input['dry_run_hash']);

        return response()->json(['data' => $batch->only(['id', 'approved_hash', 'approved_at', 'approved_by'])]);
    }
}
