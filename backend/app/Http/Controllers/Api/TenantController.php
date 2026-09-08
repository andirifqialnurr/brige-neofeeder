<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TenantController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = Tenant::query()->latest();

        if (! $user->isAdmin()) {
            $query->whereKey($user->tenant_id);
        }

        return response()->json([
            'data' => $query->get()->map(fn (Tenant $tenant) => $this->serializeTenant($tenant)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'unique:tenants,code'],
            'status' => ['sometimes', 'string', 'in:active,inactive,draft'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $tenant = Tenant::query()->create([
            ...$payload,
            'status' => $payload['status'] ?? 'draft',
            'metadata' => $payload['metadata'] ?? [],
        ]);

        return response()->json([
            'data' => $this->serializeTenant($tenant),
        ], 201);
    }

    public function show(Request $request, Tenant $tenant): JsonResponse
    {
        if (! $this->canAccessTenant($request, $tenant)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $this->serializeTenant($tenant),
        ]);
    }

    public function update(Request $request, Tenant $tenant): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user->isAdmin()) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'code' => ['sometimes', 'string', 'max:50', 'unique:tenants,code,'.$tenant->id],
            'status' => ['sometimes', 'string', 'in:active,inactive,draft'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $tenant->update($payload);

        return response()->json([
            'data' => $this->serializeTenant($tenant->refresh()),
        ]);
    }

    private function canAccessTenant(Request $request, Tenant $tenant): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isAdmin() || $user->tenant_id === $tenant->id;
    }

    private function serializeTenant(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'code' => $tenant->code,
            'status' => $tenant->status,
            'metadata' => $tenant->metadata ?? [],
            'created_at' => $tenant->created_at,
            'updated_at' => $tenant->updated_at,
        ];
    }
}
