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
        $input = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'search' => 'nullable|string|max:120',
            'tenant_id' => 'nullable|uuid',
        ]);
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
            abort_unless($user->tenant_id, 403);
            $query->where('tenant_id', $user->tenant_id);
        }

        if ($search = trim($input['search'] ?? '')) {
            $query->where(function ($query) use ($search): void {
                $query->where('id', 'like', '%'.$search.'%')
                    ->orWhere('summary->original_name', 'like', '%'.$search.'%')
                    ->orWhereHas('tenant', fn ($tenant) => $tenant->where('name', 'like', '%'.$search.'%'));
            });
        }
        $page = $query->orderBy('id')->paginate($input['per_page'] ?? 50);

        return response()->json([
            'data' => $page->getCollection()->map(fn (ImportBatch $batch) => $this->serializeBatch($batch)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
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
