<?php

namespace App\Http\Middleware;

use App\Models\ApiAccessToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $plainToken = $request->bearerToken();

        if ($plainToken === null || $plainToken === '') {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $accessToken = ApiAccessToken::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $plainToken))
            ->first();

        if ($accessToken === null || $accessToken->isExpired() || ! $accessToken->user->isActive()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $accessToken->forceFill(['last_used_at' => now()])->save();

        $request->setUserResolver(fn () => $accessToken->user);
        $request->attributes->set('api_access_token', $accessToken);

        return $next($request);
    }
}
