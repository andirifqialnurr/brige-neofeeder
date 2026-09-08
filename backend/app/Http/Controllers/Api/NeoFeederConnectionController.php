<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NeoFeederConnection;
use App\Models\User;
use App\Services\NeoFeeder\NeoFeederCredentialVault;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NeoFeederConnectionController extends Controller
{
    public function __construct(
        private readonly NeoFeederCredentialVault $credentialVault,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $query = NeoFeederConnection::query()->with('tenant')->latest();

        if (! $user->isAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        }

        return response()->json([
            'data' => $query->get()->map(fn (NeoFeederConnection $connection) => $this->serializeConnection($connection)),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'tenant_id' => ['required', 'uuid', 'exists:tenants,id'],
            'base_url' => ['required', 'url', 'max:500'],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,error'],
            'metadata' => ['sometimes', 'array'],
        ]);

        if (! $this->canManageTenant($request, $payload['tenant_id'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $connection = NeoFeederConnection::query()->create([
            'tenant_id' => $payload['tenant_id'],
            'base_url' => $payload['base_url'],
            'username' => $payload['username'] ?? null,
            'encrypted_password' => $this->credentialVault->encryptPassword($payload['password'] ?? null),
            'status' => $payload['status'] ?? 'draft',
            'metadata' => $payload['metadata'] ?? [],
        ]);

        return response()->json([
            'data' => $this->serializeConnection($connection),
        ], 201);
    }

    public function show(Request $request, NeoFeederConnection $neofeederConnection): JsonResponse
    {
        if (! $this->canManageTenant($request, $neofeederConnection->tenant_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        return response()->json([
            'data' => $this->serializeConnection($neofeederConnection),
        ]);
    }

    public function update(Request $request, NeoFeederConnection $neofeederConnection): JsonResponse
    {
        if (! $this->canManageTenant($request, $neofeederConnection->tenant_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $payload = $request->validate([
            'base_url' => ['sometimes', 'url', 'max:500'],
            'username' => ['sometimes', 'nullable', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'max:255'],
            'clear_password' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'string', 'in:draft,active,inactive,error'],
            'metadata' => ['sometimes', 'array'],
        ]);

        $updates = collect($payload)->only(['base_url', 'username', 'status', 'metadata'])->all();

        if (array_key_exists('password', $payload)) {
            $updates['encrypted_password'] = $this->credentialVault->encryptPassword($payload['password']);
        }

        if (($payload['clear_password'] ?? false) === true) {
            $updates['encrypted_password'] = null;
        }

        $neofeederConnection->update($updates);

        return response()->json([
            'data' => $this->serializeConnection($neofeederConnection->refresh()),
        ]);
    }

    private function canManageTenant(Request $request, ?string $tenantId): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isAdmin() || ($tenantId !== null && $user->tenant_id === $tenantId);
    }

    private function serializeConnection(NeoFeederConnection $connection): array
    {
        return [
            'id' => $connection->id,
            'tenant_id' => $connection->tenant_id,
            'base_url' => $connection->base_url,
            'username' => $connection->username,
            'password_configured' => $connection->encrypted_password !== null,
            'status' => $connection->status,
            'last_token_refreshed_at' => $connection->last_token_refreshed_at,
            'last_checked_at' => $connection->last_checked_at,
            'metadata' => $connection->metadata ?? [],
            'created_at' => $connection->created_at,
            'updated_at' => $connection->updated_at,
        ];
    }
}
