<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportBatchController;
use App\Http\Controllers\Api\ImportBatchDryRunController;
use App\Http\Controllers\Api\ImportBatchSyncController;
use App\Http\Controllers\Api\ImportBatchUploadController;
use App\Http\Controllers\Api\NeoFeederConnectionController;
use App\Http\Controllers\Api\ReferenceStatusController;
use App\Http\Controllers\Api\TemplateWorkbookController;
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
    Route::get('templates/neofeeder-workbook', TemplateWorkbookController::class);
    Route::get('references/status', ReferenceStatusController::class);
    Route::get('import-batches', [ImportBatchController::class, 'index']);
    Route::post('import-batches/upload', ImportBatchUploadController::class);
    Route::post('import-batches/{importBatch}/dry-run', ImportBatchDryRunController::class);
    Route::post('import-batches/{importBatch}/sync', [ImportBatchSyncController::class, 'start']);
    Route::get('import-batches/{importBatch}/sync-progress', [ImportBatchSyncController::class, 'progress']);
    Route::post('sync-attempts/{syncAttempt}/retry', [ImportBatchSyncController::class, 'retry']);
});
