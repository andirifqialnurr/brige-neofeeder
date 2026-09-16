<?php

namespace App\Http\Controllers\Api;

use App\Jobs\RunAutomationScheduleJob;
use App\Models\AuditLog;
use App\Models\AutomationSchedule;
use App\Models\MappingProfile;
use App\Models\SourceConnection;
use App\Services\Automation\AutomationScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AutomationScheduleController
{
    public function index(Request $request): JsonResponse
    {
        $tenantId = $this->tenant($request, $request->input('tenant_id'));
        $schedules = AutomationSchedule::with(['source:id,name,type,row_count,snapshot_status,snapshot_refreshed_at', 'profile:id,name,channel,version'])
            ->withCount([
                'runs as alert_run_count' => fn ($query) => $query->whereIn('status', ['success', 'failed'])
                    ->where('completed_at', '>=', now()->subDays(AutomationSchedule::ALERT_WINDOW_DAYS)),
                'runs as alert_failed_count' => fn ($query) => $query->where('status', 'failed')
                    ->where('completed_at', '>=', now()->subDays(AutomationSchedule::ALERT_WINDOW_DAYS)),
            ])
            ->where('tenant_id', $tenantId)->latest()->limit(100)->get();

        return response()->json(['data' => $schedules->map(fn (AutomationSchedule $schedule) => $this->view($schedule))->values()], 200, ['Cache-Control' => 'no-store']);
    }

    public function store(Request $request, AutomationScheduleService $service): JsonResponse
    {
        $input = $request->validate([
            'tenant_id' => 'nullable|uuid|exists:tenants,id',
            'source_connection_id' => 'required|uuid|exists:source_connections,id',
            'mapping_profile_id' => 'required|uuid|exists:mapping_profiles,id',
            'name' => 'required|string|max:120',
            'frequency' => ['required', Rule::in(AutomationSchedule::FREQUENCIES)],
            'mode' => ['sometimes', Rule::in(AutomationSchedule::MODES)],
            'error_rate_threshold' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);
        $tenantId = $this->tenant($request, $input['tenant_id'] ?? null);
        $source = SourceConnection::findOrFail($input['source_connection_id']);
        $profile = MappingProfile::findOrFail($input['mapping_profile_id']);
        abort_unless($source->tenant_id === $tenantId && $profile->tenant_id === $tenantId, 403);
        abort_unless($source->type === 'database', 422, 'Schedule hanya tersedia untuk sumber database.');
        abort_unless($source->snapshot_status === 'ready' && $source->row_count > 0, 422, 'Snapshot database harus siap dan berisi data.');
        if (AutomationSchedule::where('source_connection_id', $source->id)->where('mapping_profile_id', $profile->id)->exists()) {
            throw ValidationException::withMessages(['mapping_profile_id' => 'Source dan mapping profile ini sudah memiliki schedule.']);
        }

        $schedule = AutomationSchedule::create([
            'tenant_id' => $tenantId,
            'source_connection_id' => $source->id,
            'mapping_profile_id' => $profile->id,
            'created_by' => $request->user()->id,
            'name' => $input['name'],
            'frequency' => $input['frequency'],
            'mode' => $input['mode'] ?? AutomationSchedule::MODE_FULL,
            'error_rate_threshold' => $input['error_rate_threshold'] ?? 50,
            'is_active' => true,
            'status' => 'idle',
            'next_run_at' => $service->nextRunAt($input['frequency']),
        ])->load(['source', 'profile']);
        $this->audit($request, $tenantId, 'automation.schedule_created', $schedule->id);

        return response()->json(['data' => $this->view($schedule)], 201, ['Cache-Control' => 'no-store']);
    }

    public function update(Request $request, AutomationSchedule $automationSchedule, AutomationScheduleService $service): JsonResponse
    {
        $this->authorizeSchedule($request, $automationSchedule);
        abort_unless(! in_array($automationSchedule->status, ['queued', 'running'], true), 409, 'Schedule sedang berjalan dan belum dapat diubah.');
        $input = $request->validate([
            'name' => 'sometimes|required|string|max:120',
            'frequency' => ['sometimes', Rule::in(AutomationSchedule::FREQUENCIES)],
            'mode' => ['sometimes', Rule::in(AutomationSchedule::MODES)],
            'error_rate_threshold' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'is_active' => 'sometimes|boolean',
        ]);
        $frequency = $input['frequency'] ?? $automationSchedule->frequency;
        $active = array_key_exists('is_active', $input) ? (bool) $input['is_active'] : $automationSchedule->is_active;
        $changedFrequency = $frequency !== $automationSchedule->frequency;
        $automationSchedule->forceFill([
            ...$input,
            'frequency' => $frequency,
            'is_active' => $active,
            'status' => $active ? ($automationSchedule->status === 'failed' ? 'idle' : $automationSchedule->status) : 'idle',
            'next_run_at' => $active && ($changedFrequency || ! $automationSchedule->is_active) ? $service->nextRunAt($frequency) : ($active ? $automationSchedule->next_run_at : null),
            'last_error' => $active && ($changedFrequency || ! $automationSchedule->is_active) ? null : $automationSchedule->last_error,
        ])->save();
        $this->audit($request, $automationSchedule->tenant_id, 'automation.schedule_updated', $automationSchedule->id);

        return response()->json(['data' => $this->view($automationSchedule->fresh(['source', 'profile']))], 200, ['Cache-Control' => 'no-store']);
    }

    public function run(Request $request, AutomationSchedule $automationSchedule, AutomationScheduleService $service): JsonResponse
    {
        $this->authorizeSchedule($request, $automationSchedule);
        abort_unless($automationSchedule->is_active, 422, 'Aktifkan schedule terlebih dahulu.');
        abort_unless($service->queue($automationSchedule), 409, 'Schedule sedang berjalan atau sudah diantrekan.');
        RunAutomationScheduleJob::dispatch($automationSchedule->id);
        $this->audit($request, $automationSchedule->tenant_id, 'automation.schedule_run_requested', $automationSchedule->id);

        return response()->json(['data' => $this->view($automationSchedule->fresh(['source', 'profile']))], 202, ['Cache-Control' => 'no-store']);
    }

    private function view(AutomationSchedule $schedule): array
    {
        return [
            ...$schedule->only(['id', 'tenant_id', 'name', 'frequency', 'mode', 'is_active', 'status', 'next_run_at', 'last_started_at', 'last_completed_at', 'last_error', 'last_batch_id', 'created_at', 'updated_at']),
            'alert' => $this->alert($schedule),
            'source' => $schedule->source?->only(['id', 'name', 'type', 'row_count', 'snapshot_status', 'snapshot_refreshed_at']),
            'profile' => $schedule->profile?->only(['id', 'name', 'channel', 'version']),
        ];
    }

    private function authorizeSchedule(Request $request, AutomationSchedule $schedule): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->tenant_id === $schedule->tenant_id, 403);
    }

    private function tenant(Request $request, ?string $requested = null): string
    {
        $user = $request->user();
        $tenantId = $requested ?: $user->tenant_id;
        abort_unless($tenantId, 422, 'Pilih kampus terlebih dahulu.');
        abort_unless($user->isAdmin() || $user->tenant_id === $tenantId, 403);

        return $tenantId;
    }

    private function audit(Request $request, string $tenantId, string $event, string $subjectId): void
    {
        AuditLog::create(['tenant_id' => $tenantId, 'actor_id' => $request->user()->id, 'event' => $event,
            'subject_type' => AutomationSchedule::class, 'subject_id' => $subjectId, 'metadata' => []]);
    }

    private function alert(AutomationSchedule $schedule): array
    {
        $runCount = (int) ($schedule->getAttribute('alert_run_count') ?? 0);
        $failedCount = (int) ($schedule->getAttribute('alert_failed_count') ?? 0);

        return [
            'active' => (bool) $schedule->alert_active,
            'threshold' => (int) ($schedule->error_rate_threshold ?? 50),
            'run_count' => $runCount,
            'failed_count' => $failedCount,
            'error_rate' => $runCount > 0 ? round(100 * $failedCount / $runCount, 1) : null,
            'minimum_runs' => AutomationSchedule::ALERT_MIN_RUNS,
            'window_days' => AutomationSchedule::ALERT_WINDOW_DAYS,
            'triggered_at' => $schedule->alert_triggered_at,
        ];
    }
}
