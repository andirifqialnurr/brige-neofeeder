<?php

namespace App\Jobs;

use App\Models\ImportBatch;
use App\Services\Imports\ImportWorkbookParser;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ParseImportWorkbookJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $importBatchId,
    ) {}

    public function handle(ImportWorkbookParser $parser): void
    {
        $batch = ImportBatch::query()->findOrFail($this->importBatchId);

        $parser->parse($batch);
    }

    public function failed(?\Throwable $exception): void
    {
        $batch = ImportBatch::find($this->importBatchId);
        if ($batch && $batch->status === 'parsing') {
            $batch->forceFill(['status' => 'failed', 'summary' => [...($batch->summary ?? []), 'error' => 'Parsing workbook gagal. Periksa file dan unggah sebagai batch baru.']])->save();
        }
    }
}
