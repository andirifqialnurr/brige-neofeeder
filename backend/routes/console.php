<?php

use App\Jobs\RecordWorkerHeartbeat;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bridge:heartbeat', function (): void {
    Cache::store('redis')->put('operations:scheduler', now()->timestamp, 86400);
    RecordWorkerHeartbeat::dispatch();
    $this->info('Bridge Neo Feeder scheduler is available.');
})->purpose('Check Bridge Neo Feeder scheduler availability');

Schedule::command('bridge:heartbeat')->everyMinute()->withoutOverlapping();
Schedule::command('bridge:recover-sync')->everyMinute()->withoutOverlapping();
Schedule::command('bridge:queue-automation-schedules')->everyMinute()->withoutOverlapping();
