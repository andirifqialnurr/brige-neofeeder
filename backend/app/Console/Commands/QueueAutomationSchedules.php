<?php

namespace App\Console\Commands;

use App\Jobs\RunAutomationScheduleJob;
use App\Models\AutomationSchedule;
use App\Services\Automation\AutomationScheduleService;
use Illuminate\Console\Command;

class QueueAutomationSchedules extends Command
{
    protected $signature = 'bridge:queue-automation-schedules';

    protected $description = 'Queue due database snapshot automation schedules';

    public function handle(AutomationScheduleService $service): int
    {
        $queued = 0;
        $ids = AutomationSchedule::query()->where('is_active', true)->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())->orderBy('next_run_at')->pluck('id');
        foreach ($ids as $id) {
            $schedule = AutomationSchedule::find($id);
            if ($schedule && $service->queue($schedule, true)) {
                RunAutomationScheduleJob::dispatch($schedule->id);
                $queued++;
            }
        }

        $this->info("Queued {$queued} automation schedule(s).");

        return self::SUCCESS;
    }
}
