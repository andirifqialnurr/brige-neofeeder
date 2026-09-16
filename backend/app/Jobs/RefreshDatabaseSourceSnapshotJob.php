<?php

namespace App\Jobs;

use App\Models\SourceConnection;
use App\Services\Mapping\DatabaseSourceReaderContract;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class RefreshDatabaseSourceSnapshotJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly string $sourceConnectionId) {}

    public function uniqueId(): string
    {
        return $this->sourceConnectionId;
    }

    public function handle(DatabaseSourceReaderContract $reader): void
    {
        $source = DB::transaction(function (): ?SourceConnection {
            $source = SourceConnection::query()->lockForUpdate()->findOrFail($this->sourceConnectionId);
            if ($source->type !== 'database') {
                $this->markFailed($source, 'Refresh snapshot hanya tersedia untuk sumber database.');

                return null;
            }
            $config = $source->connection_config;
            if (! is_array($config) || blank($config['table'] ?? null) || empty($config['columns'] ?? [])) {
                $this->markFailed($source, 'Snapshot pertama belum dibuat. Pilih tabel dan kolom terlebih dahulu.');

                return null;
            }
            if ($source->snapshot_status === 'refreshing'
                && $source->snapshot_started_at?->gt(now()->subMinutes(5))) {
                return null;
            }
            $source->forceFill([
                'snapshot_status' => 'refreshing',
                'snapshot_started_at' => now(),
                'snapshot_error' => null,
            ])->save();

            return $source;
        });

        if (! $source) {
            return;
        }

        try {
            $config = $source->connection_config;
            $snapshot = $reader->read($config);
            $source->forceFill([
                'sha256' => hash('sha256', json_encode([$config['host'], $config['port'], $config['database'], $config['username'], $config['table'], $config['columns'], $snapshot], JSON_THROW_ON_ERROR)),
                'snapshot_version' => ($source->snapshot_version ?? 0) + 1,
                ...$snapshot,
                'snapshot_status' => 'ready',
                'snapshot_refreshed_at' => now(),
                'snapshot_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $source->forceFill([
                'snapshot_status' => 'pending',
                'snapshot_error' => 'Refresh snapshot belum selesai. Periksa koneksi read-only dan log server.',
            ])->save();
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $source = SourceConnection::find($this->sourceConnectionId);
        if ($source) {
            $this->markFailed($source, 'Refresh snapshot gagal. Periksa koneksi read-only dan coba lagi.');
        }
    }

    private function markFailed(SourceConnection $source, string $message): void
    {
        $source->forceFill([
            'snapshot_status' => 'failed',
            'snapshot_error' => $message,
        ])->save();
    }
}
