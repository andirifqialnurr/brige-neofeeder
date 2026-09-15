<?php

namespace App\Http\Controllers\Api;

use App\Services\Operations\OperationalHealth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OperationsController
{
    public function __invoke(Request $request, OperationalHealth $health): JsonResponse
    {
        abort_unless($request->user()->isAdmin(), 403);

        return response()->json(['data' => $health->snapshot()], 200, ['Cache-Control' => 'no-store']);
    }
}
