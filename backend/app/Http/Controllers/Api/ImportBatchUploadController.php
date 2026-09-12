<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\ParseImportWorkbookJob;
use App\Models\ImportBatch;
use App\Models\User;
use App\Services\Templates\NeoFeederTemplateWorkbookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ImportBatchUploadController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payload = $request->validate([
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'file' => [
                'required',
                'file',
                'mimes:xlsx,xls',
                'max:'.((int) config('uploads.max_size_kb', 25 * 1024)),
            ],
        ]);

        $tenantId = $user->isAdmin() ? ($payload['tenant_id'] ?? $user->tenant_id) : $user->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'Tenant is required.'], 422);
        }

        if (! $user->isAdmin() && $tenantId !== $user->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $path = $request->file('file')->store("imports/{$tenantId}", 'uploads');

        $batch = ImportBatch::query()->create([
            'tenant_id' => $tenantId,
            'source_type' => 'excel',
            'file_path' => $path,
            'template_version' => NeoFeederTemplateWorkbookService::TEMPLATE_VERSION,
            'status' => 'uploaded',
            'summary' => [
                'original_name' => $request->file('file')->getClientOriginalName(),
                'size' => $request->file('file')->getSize(),
                'disk' => 'uploads',
            ],
        ]);

        ParseImportWorkbookJob::dispatch($batch->id);

        $batch->load('tenant');

        return response()->json([
            'data' => [
                'id' => $batch->id,
                'tenant_id' => $batch->tenant_id,
                'tenant_name' => $batch->tenant?->name,
                'source_type' => $batch->source_type,
                'status' => $batch->status,
                'file_path' => $batch->file_path,
                'template_version' => $batch->template_version,
                'summary' => $batch->summary ?? [],
                'staging_records_count' => 0,
                'created_at' => $batch->created_at,
                'updated_at' => $batch->updated_at,
                'file_exists' => Storage::disk('uploads')->exists($path),
            ],
        ], 201);
    }
}
