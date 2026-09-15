<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\ImportBatchApprovalController;
use App\Http\Controllers\Api\ImportBatchController;
use App\Http\Controllers\Api\ImportBatchDryRunController;
use App\Http\Controllers\Api\ImportBatchInspectionController;
use App\Http\Controllers\Api\ImportBatchSyncController;
use App\Http\Controllers\Api\ImportBatchUploadController;
use App\Http\Controllers\Api\NeoFeederConnectionController;
use App\Http\Controllers\Api\OperationsController;
use App\Http\Controllers\Api\ReferenceStatusController;
use App\Http\Controllers\Api\ReferenceSyncController;
use App\Http\Controllers\Api\TemplateWorkbookController;
use App\Http\Controllers\Api\TenantController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);

Route::post('/auth/login', [AuthController::class, 'login']);

Route::middleware('api.token')->group(function (): void {
    Route::get('audit-logs', [AuditLogController::class, 'index']);
    Route::get('audit-logs/export', [AuditLogController::class, 'export']);
    Route::post('import-batches/{importBatch}/rows/{record}/reveal', [ImportBatchInspectionController::class, 'reveal']);
    Route::get('operations/health', OperationsController::class);
    Route::get('dashboard/statistics', DashboardController::class);
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::apiResource('tenants', TenantController::class)->only(['index', 'store', 'show', 'update']);
    Route::post('neofeeder-connections/{neofeederConnection}/test', [NeoFeederConnectionController::class, 'test']);
    Route::apiResource('neofeeder-connections', NeoFeederConnectionController::class)
        ->parameters(['neofeeder-connections' => 'neofeederConnection'])
        ->only(['index', 'store', 'show', 'update']);
    Route::get('templates/neofeeder-workbook', TemplateWorkbookController::class);
    Route::get('references/status', ReferenceStatusController::class);
    Route::post('references/sync', ReferenceSyncController::class);
    Route::get('import-batches', [ImportBatchController::class, 'index']);
    Route::get('import-batches/{importBatch}', [ImportBatchInspectionController::class, 'show']);
    Route::get('import-batches/{importBatch}/rows', [ImportBatchInspectionController::class, 'rows']);
    Route::get('import-batches/{importBatch}/rows/{record}', [ImportBatchInspectionController::class, 'row']);
    Route::get('import-batches/{importBatch}/report', [ImportBatchInspectionController::class, 'report']);
    Route::post('import-batches/upload', ImportBatchUploadController::class);
    Route::post('import-batches/{importBatch}/dry-run', ImportBatchDryRunController::class);
    Route::post('import-batches/{importBatch}/approve', ImportBatchApprovalController::class);
    Route::post('import-batches/{importBatch}/sync', [ImportBatchSyncController::class, 'start']);
    Route::get('import-batches/{importBatch}/sync-progress', [ImportBatchSyncController::class, 'progress']);
    Route::get('import-batches/{importBatch}/sync-attempts', [ImportBatchSyncController::class, 'attempts']);
    Route::post('sync-attempts/{syncAttempt}/retry', [ImportBatchSyncController::class, 'retry']);
});
