<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\NeoFeederConnection;
use App\Models\User;
use App\Services\NeoFeeder\NeoFeederClient;
use App\Services\NeoFeeder\NeoFeederCredentialVault;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class NeoFeederConnectionController extends Controller
{
    public function __construct(
        private readonly NeoFeederCredentialVault $credentialVault,
        private readonly NeoFeederClient $neoFeederClient,
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

    public function test(Request $request, NeoFeederConnection $neofeederConnection): JsonResponse
    {
        if (! $this->canManageTenant($request, $neofeederConnection->tenant_id)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($neofeederConnection->username === null || $neofeederConnection->encrypted_password === null) {
            return response()->json([
                'message' => 'Username dan password Neo Feeder wajib diisi sebelum test koneksi.',
            ], 422);
        }

        try {
            $password = $this->credentialVault->decryptPassword($neofeederConnection->encrypted_password);

            if ($password === null) {
                return response()->json(['message' => 'Password Neo Feeder belum dikonfigurasi.'], 422);
            }

            $startedAt = microtime(true);
            $response = $this->neoFeederClient->getToken(
                $neofeederConnection->base_url,
                $neofeederConnection->username,
                $password,
            );

            $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
            $tokenReceived = $this->extractToken($response->data) !== null;
            $connected = $response->successful() && $tokenReceived;

            $neofeederConnection->forceFill([
                'status' => $connected ? 'active' : 'error',
                'last_checked_at' => now(),
                'last_token_refreshed_at' => $connected ? now() : $neofeederConnection->last_token_refreshed_at,
                'metadata' => [
                    ...($neofeederConnection->metadata ?? []),
                    'last_test' => [
                        'ok' => $connected,
                        'duration_ms' => $durationMs,
                        'error_code' => $response->errorCode,
                        'error_desc' => $response->errorDesc,
                        'token_received' => $tokenReceived,
                    ],
                ],
            ])->save();

            $this->writeAuditLog($request, $neofeederConnection, 'neofeeder_connection.tested', [
                'ok' => $connected,
                'duration_ms' => $durationMs,
                'error_code' => $response->errorCode,
                'token_received' => $tokenReceived,
            ]);

            return response()->json([
                'data' => [
                    'ok' => $connected,
                    'error_code' => $response->errorCode,
                    'error_desc' => $response->errorDesc,
                    'token_received' => $tokenReceived,
                    'connection' => $this->serializeConnection($neofeederConnection->refresh()),
                ],
            ], $connected ? 200 : 422);
        } catch (RequestException $exception) {
            return $this->failedConnectionTest($request, $neofeederConnection, 'http_error', $exception->getMessage());
        } catch (Throwable $exception) {
            return $this->failedConnectionTest($request, $neofeederConnection, 'runtime_error', $exception->getMessage());
        }
    }

    private function canManageTenant(Request $request, ?string $tenantId): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isAdmin() || ($tenantId !== null && $user->tenant_id === $tenantId);
    }

    private function failedConnectionTest(
        Request $request,
        NeoFeederConnection $connection,
        string $errorCode,
        string $errorDesc,
    ): JsonResponse {
        $connection->forceFill([
            'status' => 'error',
            'last_checked_at' => now(),
            'metadata' => [
                ...($connection->metadata ?? []),
                'last_test' => [
                    'ok' => false,
                    'error_code' => $errorCode,
                    'error_desc' => $errorDesc,
                    'token_received' => false,
                ],
            ],
        ])->save();

        $this->writeAuditLog($request, $connection, 'neofeeder_connection.test_failed', [
            'error_code' => $errorCode,
        ]);

        return response()->json([
            'data' => [
                'ok' => false,
                'error_code' => $errorCode,
                'error_desc' => $errorDesc,
                'token_received' => false,
                'connection' => $this->serializeConnection($connection->refresh()),
            ],
        ], 422);
    }

    private function extractToken(mixed $data): ?string
    {
        if (! is_array($data)) {
            return null;
        }

        $token = $data['token'] ?? null;

        return is_string($token) && $token !== '' ? $token : null;
    }

    private function writeAuditLog(Request $request, NeoFeederConnection $connection, string $event, array $metadata): void
    {
        /** @var User $user */
        $user = $request->user();

        AuditLog::query()->create([
            'tenant_id' => $connection->tenant_id,
            'actor_id' => $user->id,
            'event' => $event,
            'subject_type' => NeoFeederConnection::class,
            'subject_id' => $connection->id,
            'metadata' => $metadata,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);
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
