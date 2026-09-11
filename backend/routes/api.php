<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportBatchUploadController;
use App\Http\Controllers\Api\NeoFeederConnectionController;
use App\Http\Controllers\Api\ReferenceStatusController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('neofeeder-connections/{neofeederConnection}/test', [NeoFeederConnectionController::class, 'test']);
    Route::apiResource('neofeeder-connections', NeoFeederConnectionController::class)
        ->parameters(['neofeeder-connections' => 'neofeederConnection'])
        ->only(['index', 'store', 'show', 'update']);
    Route::get('references/status', ReferenceStatusController::class);
    Route::post('import-batches/upload', ImportBatchUploadController::class);
});
