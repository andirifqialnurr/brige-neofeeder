<?php

namespace App\Services\Automation;

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
}
