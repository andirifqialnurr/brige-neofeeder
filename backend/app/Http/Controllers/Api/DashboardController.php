<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\ReferenceRecord;
use App\Models\StagingRecord;
use App\Models\SyncAttempt;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $input = $request->validate(['days' => ['sometimes', Rule::in([14, 30, 90])], 'tenant_id' => 'nullable|uuid|exists:tenants,id']);
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->tenant_id, 403);
        $tenantId = $user->isAdmin() ? ($input['tenant_id'] ?? null) : $user->tenant_id;
        $scope = fn ($query) => $tenantId ? $query->where('tenant_id', $tenantId) : $query;
        $tenants = Tenant::query()->when($tenantId, fn ($query) => $query->whereKey($tenantId));
        $campusCount = (clone $tenants)->count();
        $batches = $scope(ImportBatch::query());
        $rows = $scope(StagingRecord::query());
        $connections = $scope(NeoFeederConnection::query());
        $references = $scope(ReferenceRecord::query());
        $attempts = $scope(SyncAttempt::query());
        $group = fn ($query, string $column) => $query->select($column)->selectRaw('COUNT(*) AS total')->groupBy($column)->orderBy($column)->get();
        $batchStatus = $group(clone $batches, 'status');
        $syncStatus = $group(clone $attempts, 'status');
        $knownEndpoints = array_column(config('neofeeder-contracts.channels.references.operations', []), 'action');
        $covered = (clone $references)->whereIn('endpoint', $knownEndpoints)->select('tenant_id', 'endpoint')->distinct()->get()->count();
        $days = (int) ($input['days'] ?? 30);
        $today = now()->startOfDay();
        $start = $today->copy()->subDays($days - 1);
        // Boundaries and date bucketing use the application timezone, also returned to the UI.
        $activity = (clone $batches)->whereBetween('created_at', [$start, $today->copy()->endOfDay()])
            ->selectRaw('DATE(created_at) AS day, COUNT(*) AS total')->groupByRaw('DATE(created_at)')->pluck('total', 'day');
        $channels = $group(clone $rows, 'channel');
        $success = (int) ($syncStatus->firstWhere('status', 'success')?->total ?? 0);
        $failed = (int) ($syncStatus->firstWhere('status', 'failed')?->total ?? 0);

        return response()->json(['data' => [
            'tenant_id' => $tenantId, 'generated_at' => now()->toISOString(), 'timezone' => config('app.timezone'), 'days' => $days,
            'totals' => [
                'campuses' => $campusCount,
                'active_campuses' => (clone $tenants)->where('status', 'active')->count(),
                'active_operators' => $scope(User::query())->where('role', 'operator')->where('status', 'active')->count(),
                'connections' => (clone $connections)->count(),
                'active_connections' => (clone $connections)->where('status', 'active')->count(),
                'reference_rows' => (clone $references)->count(),
                'reference_endpoints_covered' => $covered,
                'reference_endpoints_expected' => $campusCount * count($knownEndpoints),
                'channels' => count(config('neofeeder-contracts.channels')) - 1,
                'batches' => (int) $batchStatus->sum('total'),
                'staging_rows' => (int) $channels->sum('total'),
                'warning_rows' => (clone $rows)->whereJsonLength('validation_result->warnings', '>', 0)->count(),
                'sync_success_rate' => $success + $failed > 0 ? round(100 * $success / ($success + $failed), 1) : null,
            ],
            'batch_statuses' => $batchStatus,
            'row_statuses' => $group(clone $rows, 'status'),
            'sync_statuses' => $syncStatus,
            'channels' => $channels,
            'activity' => collect(range(0, $days - 1))->map(function ($offset) use ($start, $activity) {
                $day = $start->copy()->addDays($offset)->format('Y-m-d');

                return ['date' => $day, 'total' => (int) ($activity[$day] ?? 0)];
            }),
            'automation_available' => false,
        ]], 200, ['Cache-Control' => 'no-store']);
    }
}
