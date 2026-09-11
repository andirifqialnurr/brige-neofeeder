<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ReferenceRecord;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReferenceStatusController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenantId = $this->tenantId($request, $user);
        $operations = collect(config('neofeeder-contracts.channels.references.operations', []));
        $statusByEndpoint = $this->statusByEndpoint($tenantId);

        $endpoints = $operations
            ->map(fn (array $operation): array => $this->serializeEndpoint($operation, $statusByEndpoint))
            ->values();

        return response()->json([
            'data' => [
                'tenant_id' => $tenantId,
                'endpoint_count' => $operations->count(),
                'synced_endpoint_count' => $endpoints->where('status', 'synced')->count(),
                'failed_endpoint_count' => $endpoints->where('status', 'empty')->count(),
                'total_rows' => $endpoints->sum('total_rows'),
                'last_synced_at' => $endpoints->pluck('last_synced_at')->filter()->max(),
                'endpoints' => $endpoints,
            ],
        ]);
    }

    private function tenantId(Request $request, User $user): ?string
    {
        if (! $user->isAdmin()) {
            return $user->tenant_id;
        }

        return $request->query('tenant_id');
    }

    /**
     * @return Collection<string, object{endpoint: string, total_rows: int, last_synced_at: string|null}>
     */
    private function statusByEndpoint(?string $tenantId): Collection
    {
        $query = ReferenceRecord::query()
            ->selectRaw('endpoint, count(*) as total_rows, max(synced_at) as last_synced_at')
            ->groupBy('endpoint');

        if ($tenantId !== null) {
            $query->where('tenant_id', $tenantId);
        }

        return $query->get()->keyBy('endpoint');
    }

    /**
     * @param  Collection<string, object{endpoint: string, total_rows: int, last_synced_at: string|null}>  $statusByEndpoint
     */
    private function serializeEndpoint(array $operation, Collection $statusByEndpoint): array
    {
        $action = (string) ($operation['action'] ?? '');
        $status = $statusByEndpoint->get($action);

        return [
            'name' => (string) ($operation['name'] ?? $action),
            'endpoint' => $action,
            'total_rows' => (int) ($status->total_rows ?? 0),
            'last_synced_at' => $status->last_synced_at ?? null,
            'status' => $status === null ? 'empty' : 'synced',
        ];
    }
}
