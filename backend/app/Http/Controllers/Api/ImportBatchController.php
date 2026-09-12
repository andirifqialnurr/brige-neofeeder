<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ImportBatchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = ImportBatch::query()
            ->with('tenant')
            ->withCount('stagingRecords')
            ->latest();

        if ($user->isAdmin()) {
            $tenantId = $request->query('tenant_id');

            if (is_string($tenantId) && $tenantId !== '') {
                $query->where('tenant_id', $tenantId);
            }
        } else {
            $query->where('tenant_id', $user->tenant_id);
        }

        return response()->json([
            'data' => $query->limit(50)->get()->map(fn (ImportBatch $batch) => $this->serializeBatch($batch)),
        ]);
    }

    private function serializeBatch(ImportBatch $batch): array
    {
        return [
            'id' => $batch->id,
            'tenant_id' => $batch->tenant_id,
            'tenant_name' => $batch->tenant?->name,
            'source_type' => $batch->source_type,
            'file_path' => $batch->file_path,
            'template_version' => $batch->template_version,
            'status' => $batch->status,
            'summary' => $batch->summary ?? [],
            'staging_records_count' => $batch->staging_records_count ?? 0,
            'created_at' => $batch->created_at,
            'updated_at' => $batch->updated_at,
        ];
    }
}
