<?php

namespace App\Jobs;

use App\Models\SourceConnection;
use App\Services\Mapping\DatabaseSchemaDiscoveryContract;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class DiscoverDatabaseSchemaJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(public readonly string $sourceConnectionId) {}

    public function uniqueId(): string
    {
        return $this->sourceConnectionId;
    }

    public function handle(DatabaseSchemaDiscoveryContract $discovery): void
    {
        $source = DB::transaction(function (): ?SourceConnection {
            $source = SourceConnection::query()->lockForUpdate()->findOrFail($this->sourceConnectionId);
            if ($source->type !== 'database') {
                $this->markFailed($source, 'Sumber bukan koneksi database.');

                return null;
            }
            if ($source->schema_discovery_status === 'discovering'
                && $source->schema_discovery_started_at?->gt(now()->subMinutes(5))) {
                return null;
            }
            $source->forceFill([
                'schema_discovery_status' => 'discovering',
                'schema_discovery_started_at' => now(),
                'schema_discovery_error' => null,
            ])->save();

            return $source;
        });

        if (! $source) {
            return;
        }

        try {
            $catalog = $discovery->discover($source);
            $discovery->store($source, $catalog);
            $source->forceFill([
                'schema_discovery_status' => 'ready',
                'schema_discovered_at' => now(),
                'schema_discovery_error' => null,
            ])->save();
        } catch (Throwable $exception) {
            $source->forceFill([
                'schema_discovery_status' => 'pending',
                'schema_discovery_error' => 'Discovery schema belum selesai. Periksa koneksi read-only dan log server.',
            ])->save();
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $source = SourceConnection::find($this->sourceConnectionId);
        if ($source) {
            $this->markFailed($source, 'Discovery schema gagal. Periksa koneksi read-only dan coba lagi.');
        }
    }

    private function markFailed(SourceConnection $source, string $message): void
    {
        $source->forceFill([
            'schema_discovery_status' => 'failed',
            'schema_discovery_error' => $message,
        ])->save();
    }
}
