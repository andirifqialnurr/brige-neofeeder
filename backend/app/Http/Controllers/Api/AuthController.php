<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ApiAccessToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /** @var User|null $user */
        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || ! Hash::check($credentials['password'], $user->password) || ! $user->isActive()) {
            return response()->json(['message' => 'Email atau password tidak valid.'], 422);
        }

        $plainToken = Str::random(64);

        $token = $user->accessTokens()->create([
            'name' => 'dashboard',
            'token_hash' => hash('sha256', $plainToken),
            'abilities' => ['*'],
        ]);

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json([
            'token_type' => 'Bearer',
            'access_token' => $plainToken,
            'expires_at' => $token->expires_at,
            'user' => $this->serializeUser($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'user' => $this->serializeUser($user),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        /** @var ApiAccessToken|null $token */
        $token = $request->attributes->get('api_access_token');
        $token?->delete();

        return response()->json(['message' => 'Logged out.']);
    }

    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'status' => $user->status,
        ];
    }
}
