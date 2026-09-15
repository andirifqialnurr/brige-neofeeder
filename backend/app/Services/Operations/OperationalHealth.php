<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Throwable;

class OperationalHealth
{
    public function snapshot(): array
    {
        $checks = [];
        $failedJobs = [];
        $failedCount = null;
        try {
            DB::select('SELECT 1');
            $failedCount = DB::table('failed_jobs')->count();
            $failedJobs = DB::table('failed_jobs')->orderByDesc('failed_at')->limit(20)
                ->get(['uuid', 'connection', 'queue', 'failed_at'])->all();
            $checks[] = ['key' => 'database', 'label' => 'Database', 'status' => 'ok'];
        } catch (Throwable) {
            $checks[] = ['key' => 'database', 'label' => 'Database', 'status' => 'unavailable'];
        }
        try {
            Redis::connection()->ping();
            $checks[] = ['key' => 'redis', 'label' => 'Redis', 'status' => 'ok'];
        } catch (Throwable) {
            $checks[] = ['key' => 'redis', 'label' => 'Redis', 'status' => 'unavailable'];
        }
        try {
            $checks[] = ['key' => 'queue', 'label' => 'Antrean', 'status' => 'ok',
                'pending_jobs' => Queue::connection('redis')->size(config('queue.connections.redis.queue', 'default'))];
        } catch (Throwable) {
            $checks[] = ['key' => 'queue', 'label' => 'Antrean', 'status' => 'unavailable'];
        }
        foreach (['worker' => 'Worker', 'scheduler' => 'Scheduler'] as $key => $label) {
            try {
                $timestamp = Cache::store('redis')->get('operations:'.$key);
                $checks[] = ['key' => $key, 'label' => $label,
                    'status' => ! is_numeric($timestamp) ? 'missing' : (now()->timestamp - (int) $timestamp > 180 ? 'stale' : 'ok'),
                    'last_seen_at' => is_numeric($timestamp) ? date(DATE_ATOM, (int) $timestamp) : null];
            } catch (Throwable) {
                $checks[] = ['key' => $key, 'label' => $label, 'status' => 'unavailable', 'last_seen_at' => null];
            }
        }

        return ['status' => collect($checks)->every(fn ($check) => $check['status'] === 'ok') ? 'ok' : 'degraded',
            'checked_at' => now()->toIso8601String(), 'checks' => $checks,
            'failed_jobs_count' => $failedCount, 'recent_failed_jobs' => $failedJobs];
    }
}
