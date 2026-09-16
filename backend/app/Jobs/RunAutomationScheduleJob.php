<?php

namespace App\Jobs;

use App\Models\AutomationSchedule;
use App\Models\User;
use App\Services\Mapping\FileMappingService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

class RunAutomationScheduleJob implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public function __construct(public readonly string $scheduleId) {}

    public function uniqueId(): string
    {
        return $this->scheduleId;
    }

    public function handle(FileMappingService $mapping): void
    {
        $schedule = DB::transaction(function (): ?AutomationSchedule {
            $schedule = AutomationSchedule::query()->lockForUpdate()->findOrFail($this->scheduleId);
            if (! $schedule->is_active || $schedule->status !== 'queued') {
                return null;
            }

            $schedule->forceFill(['status' => 'running', 'last_started_at' => now(), 'last_error' => null])->save();

            return $schedule->load(['source', 'profile', 'creator']);
        });

        if (! $schedule) {
            return;
        }

        $profile = $schedule->profile;
        if (! $profile) {
            $schedule->forceFill([
                'status' => 'failed',
                'last_completed_at' => now(),
                'last_error' => 'Scheduled run gagal. Mapping profile tidak ditemukan.',
            ])->save();

            return;
        }
        $executionLock = Cache::lock($this->lockKey($schedule), $this->timeout + 30);
        if (! $executionLock->get()) {
            $schedule->forceFill(['status' => 'queued', 'last_error' => 'Menunggu kanal yang sedang diproses.'])->save();
            $this->release(30);

            return;
        }

        try {
            $source = $schedule->source;
            if (! $source || ! $profile || $source->tenant_id !== $schedule->tenant_id || $profile->tenant_id !== $schedule->tenant_id) {
                throw new RuntimeException('Konfigurasi schedule tidak valid.');
            }
            if ($schedule->mode !== AutomationSchedule::MODE_FULL) {
                throw new RuntimeException('Mode schedule belum didukung.');
            }
            if ($source->type !== 'database') {
                throw new RuntimeException('Schedule hanya tersedia untuk sumber database.');
            }

            RefreshDatabaseSourceSnapshotJob::dispatchSync($source->id);
            $source->refresh();
            if ($source->snapshot_status !== 'ready') {
                throw new RuntimeException('Snapshot database belum siap untuk mapping.');
            }
            if ($source->row_count < 1) {
                throw new RuntimeException('Snapshot database kosong; batch tidak dibuat.');
            }

            $preview = $mapping->preview($profile, $source, $profile->version);
            $actor = $schedule->creator ?? User::findOrFail($schedule->created_by);
            $batch = $mapping->stage($profile, $source, $profile->version, $preview['preview_hash'], $actor);
            $schedule->forceFill([
                'status' => 'success',
                'last_batch_id' => $batch->id,
                'last_completed_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $schedule->forceFill([
                'status' => 'failed',
                'last_completed_at' => now(),
                'last_error' => 'Scheduled run gagal. Periksa snapshot, mapping profile, dan log server.',
            ])->save();
            throw $exception;
        } finally {
            $executionLock->release();
        }
    }

    public function failed(?Throwable $exception): void
    {
        AutomationSchedule::whereKey($this->scheduleId)->update([
            'status' => 'failed',
            'last_completed_at' => now(),
            'last_error' => 'Scheduled run gagal. Periksa snapshot, mapping profile, dan log server.',
        ]);
    }

    private function lockKey(AutomationSchedule $schedule): string
    {
        return "automation:tenant:{$schedule->tenant_id}:channel:{$schedule->profile?->channel}";
    }
}
