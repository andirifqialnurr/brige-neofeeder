<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\DryRun\ImportBatchDryRunService;
use App\Services\Operations\SensitiveData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchDryRunController extends Controller
{
    public function __invoke(Request $request, ImportBatch $importBatch, ImportBatchDryRunService $dryRunService, SensitiveData $privacy): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin() && $user->tenant_id !== $importBatch->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $privacy->present($dryRunService->preview($importBatch)),
        ], 200, ['Cache-Control' => 'no-store']);
    }
}
