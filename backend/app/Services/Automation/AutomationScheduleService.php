<?php

namespace App\Services\Automation;

use App\Models\AuditLog;
use App\Models\AutomationSchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AutomationScheduleService
{
    public function nextRunAt(string $frequency, ?Carbon $from = null): Carbon
    {
        $from ??= now();

        return match ($frequency) {
            'hourly' => $from->copy()->addHour(),
            'daily' => $from->copy()->addDay(),
            'weekly' => $from->copy()->addWeek(),
            default => throw new \InvalidArgumentException('Frekuensi schedule tidak didukung.'),
        };
    }

    public function queue(AutomationSchedule $schedule, bool $dueOnly = false): bool
    {
        return DB::transaction(function () use ($schedule, $dueOnly): bool {
            $fresh = AutomationSchedule::query()->lockForUpdate()->findOrFail($schedule->id);
            if (! $fresh->is_active || in_array($fresh->status, ['queued', 'running'], true)) {
                return false;
            }
            if ($dueOnly && (! $fresh->next_run_at || $fresh->next_run_at->isFuture())) {
                return false;
            }

            $attributes = ['status' => 'queued', 'last_error' => null];
            if ($dueOnly) {
                $attributes['next_run_at'] = $this->nextRunAt($fresh->frequency);
            }
            $fresh->forceFill($attributes)->save();

            return true;
        });
    }

    /**
     * Recalculate the in-app alert from completed runs in the rolling window.
     * Queue contention is intentionally not a run and therefore cannot inflate the error rate.
     *
     * @return array{run_count: int, failed_count: int, error_rate: float|null, active: bool}
     */
    public function refreshAlertState(AutomationSchedule $schedule): array
    {
        $stats = $schedule->runs()
            ->whereIn('status', ['success', 'failed'])
            ->where('completed_at', '>=', now()->subDays(AutomationSchedule::ALERT_WINDOW_DAYS))
            ->selectRaw('COUNT(*) as run_count')
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->first();
        $runCount = (int) ($stats?->run_count ?? 0);
        $failedCount = (int) ($stats?->failed_count ?? 0);
        $errorRate = $runCount > 0 ? round(100 * $failedCount / $runCount, 1) : null;
        $active = $runCount >= AutomationSchedule::ALERT_MIN_RUNS
            && $errorRate !== null
            && $errorRate >= (int) $schedule->error_rate_threshold;
        $wasActive = (bool) $schedule->alert_active;
        $triggeredAt = $active && ! $wasActive ? now() : $schedule->alert_triggered_at;

        $schedule->forceFill([
            'alert_active' => $active,
            'alert_triggered_at' => $triggeredAt,
        ])->save();

        if ($active && ! $wasActive) {
            AuditLog::query()->create([
                'tenant_id' => $schedule->tenant_id,
                'event' => 'automation.schedule_alert_triggered',
                'subject_type' => AutomationSchedule::class,
                'subject_id' => $schedule->id,
                'metadata' => [
                    'run_count' => $runCount,
                    'failed_count' => $failedCount,
                    'error_rate' => $errorRate,
                    'threshold' => (int) $schedule->error_rate_threshold,
                    'window_days' => AutomationSchedule::ALERT_WINDOW_DAYS,
                ],
            ]);
        }

        return [
            'run_count' => $runCount,
            'failed_count' => $failedCount,
            'error_rate' => $errorRate,
            'active' => $active,
        ];
    }
}
