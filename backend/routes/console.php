<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('bridge:heartbeat', function (): void {
    $this->info('Bridge Neo Feeder scheduler is available.');
})->purpose('Check Bridge Neo Feeder scheduler availability');

Schedule::command('bridge:heartbeat')->dailyAt('00:05');
