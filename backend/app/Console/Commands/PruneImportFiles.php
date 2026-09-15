<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PruneImportFiles extends Command
{
    protected $signature = 'bridge:prune-import-files {--days= : Override configured retention age} {--apply : Delete eligible files; default is preview only}';

    protected $description = 'Preview or remove old completed upload files while preserving staging and unresolved deliveries';

    public function handle(): int
    {
        $input = $this->option('days') ?? config('maintenance.retention_days');
        if (filter_var($input, FILTER_VALIDATE_INT) === false || (int) $input < 1) {
            $this->error('Retensi belum aktif. Tentukan jumlah hari positif.');

            return self::FAILURE;
        }
        $cutoff = now()->subDays((int) $input);
        $count = 0;
        $ids = ImportBatch::where('source_type', 'excel')->whereNotNull('file_path')
            ->whereIn('status', ['synced', 'failed'])->where('updated_at', '<', $cutoff)->pluck('id');
        foreach ($ids as $id) {
            DB::transaction(function () use ($id, $cutoff, &$count): void {
                $batch = ImportBatch::query()->lockForUpdate()->find($id);
                if (! $batch || ! $batch->file_path || $batch->updated_at->gte($cutoff)
                    || ! in_array($batch->status, ['synced', 'failed'], true)) {
                    return;
                }
                if ($batch->stagingRecords()->whereHas('syncAttempts', fn ($query) => $query
                    ->whereIn('status', ['queued', 'syncing', 'retrying', 'unknown'])->orWhere('retry_safe', true))->exists()) {
                    return;
                }
                $prefix = 'imports/'.$batch->tenant_id.'/';
                if (! str_starts_with($batch->file_path, $prefix)
                    || ! preg_match('/^[a-zA-Z0-9_.-]+$/', substr($batch->file_path, strlen($prefix)))) {
                    return;
                }
                $this->line($batch->id);
                $count++;
                if (! $this->option('apply')) {
                    return;
                }
                $disk = Storage::disk('uploads');
                if ($disk->exists($batch->file_path) && ! $disk->delete($batch->file_path)) {
                    throw new \RuntimeException('File tidak dapat dihapus.');
                }
                $batch->forceFill(['file_path' => null, 'summary' => [...($batch->summary ?? []), 'file_pruned_at' => now()->toIso8601String()]])->save();
                AuditLog::create(['tenant_id' => $batch->tenant_id, 'event' => 'import.file.pruned',
                    'subject_type' => ImportBatch::class, 'subject_id' => $batch->id, 'metadata' => ['staging_preserved' => true]]);
            });
        }
        $this->info(($this->option('apply') ? 'Dihapus: ' : 'Kandidat: ').$count);

        return self::SUCCESS;
    }
}
