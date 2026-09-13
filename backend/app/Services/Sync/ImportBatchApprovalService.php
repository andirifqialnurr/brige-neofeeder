<?php

namespace App\Services\Sync;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;

final class ImportBatchApprovalService
{
    public function fingerprint(ImportBatch $batch): string
    {
        $hash = hash_init('sha256');
        hash_update($hash, $this->canonical([
            $batch->id, $batch->tenant_id, $batch->template_version,
            $batch->summary['missing_sheets'] ?? [], config('neofeeder-contracts'),
        ]));
        foreach ($batch->stagingRecords()->orderBy('id')->cursor() as $row) {
            // Runtime status and sync responses change during delivery, not the approved input.
            hash_update($hash, $this->canonical([
                $row->only(['id', 'tenant_id', 'channel', 'sheet_name', 'row_number', 'normalized_row', 'raw_row']),
                array_intersect_key($row->validation_result ?? [], array_flip(['errors', 'warnings', 'info'])),
            ]));
        }

        return hash_final($hash);
    }

    public function approve(ImportBatch $batch, User $actor, string $hash): ImportBatch
    {
        return DB::transaction(function () use ($batch, $actor, $hash) {
            $batch = ImportBatch::query()->lockForUpdate()->findOrFail($batch->id);
            abort_unless($batch->status === 'dry_run_ready' && $batch->dry_run_hash, 409, 'Jalankan dry-run tanpa error terlebih dahulu.');
            abort_if($batch->stagingRecords()->where('status', '!=', 'valid')->exists() || ! $batch->stagingRecords()->exists(), 409, 'Seluruh baris harus valid dan batch tidak boleh kosong.');
            abort_unless(hash_equals($batch->dry_run_hash, $hash) && hash_equals($hash, $this->fingerprint($batch)), 409, 'Data berubah. Jalankan dry-run dan tinjau ulang.');
            if ($batch->approved_hash === $hash && $batch->approved_at) {
                return $batch;
            }

            $batch->forceFill(['approved_hash' => $hash, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            AuditLog::create([
                'tenant_id' => $batch->tenant_id, 'actor_id' => $actor->id,
                'event' => 'import.sync.approved', 'subject_type' => ImportBatch::class, 'subject_id' => $batch->id,
                'metadata' => ['approved_hash' => $hash],
            ]);

            return $batch;
        });
    }

    public function assertApproved(ImportBatch $batch): void
    {
        abort_unless($batch->approved_at && $batch->approved_by && $batch->approved_hash && $batch->dry_run_hash === $batch->approved_hash, 409, 'Persetujuan operator diperlukan sebelum pengiriman.');
        abort_unless(hash_equals($batch->approved_hash, $this->fingerprint($batch)), 409, 'Data atau contract berubah sejak disetujui. Pengiriman diblokir.');
    }

    private function canonical(mixed $value): string
    {
        $sort = function (mixed $item) use (&$sort): mixed {
            if (! is_array($item)) {
                return $item;
            }
            if (! array_is_list($item)) {
                ksort($item);
            }

            return array_map($sort, $item);
        };

        return json_encode($sort($value), JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION);
    }
}
