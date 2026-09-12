<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\RefreshNeoFeederReferenceJob;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReferenceSyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $endpoints = $this->referenceEndpoints();

        $payload = $request->validate([
            'tenant_id' => ['nullable', 'uuid', 'exists:tenants,id'],
            'endpoint' => ['nullable', 'string', Rule::in($endpoints)],
        ]);

        /** @var User $user */
        $user = $request->user();
        $tenantId = $user->isAdmin() ? ($payload['tenant_id'] ?? null) : $user->tenant_id;

        if ($tenantId === null) {
            return response()->json(['message' => 'Tenant is required.'], 422);
        }

        if (! $user->isAdmin() && $tenantId !== $user->tenant_id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if (! Tenant::query()->whereKey($tenantId)->exists()) {
            return response()->json(['message' => 'Tenant not found.'], 404);
        }

        $selectedEndpoints = isset($payload['endpoint']) ? [$payload['endpoint']] : $endpoints;

        foreach ($selectedEndpoints as $endpoint) {
            RefreshNeoFeederReferenceJob::dispatch($tenantId, $endpoint);
        }

        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'queued_endpoint_count' => count($selectedEndpoints),
                'endpoints' => $selectedEndpoints,
            ],
        ], 202);
    }

    /**
     * @return list<string>
     */
    private function referenceEndpoints(): array
    {
        return array_values(array_filter(array_map(
            fn (array $operation): ?string => is_string($operation['action'] ?? null) ? $operation['action'] : null,
            config('neofeeder-contracts.channels.references.operations', []),
        )));
    }
}
