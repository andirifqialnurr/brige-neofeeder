<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\Tenant;
use App\Services\Imports\ImportWorkbookParser;
use App\Services\Templates\TrialPackService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class TrialPackTest extends TestCase
{
    use RefreshDatabase;

    public function test_pack_has_separate_single_record_workbooks_and_no_outbound_effects(): void
    {
        Http::preventStrayRequests();
        Queue::fake();
        Storage::fake('uploads');
        $directory = app(TrialPackService::class)->generate();
        $this->assertDatabaseCount('tenants', 0);
        $this->assertDatabaseCount('import_batches', 0);
        $manifest = json_decode(file_get_contents($directory.'/manifest.json'), true);
        $this->assertFalse($manifest['live_verified']);
        $this->assertCount(7, $manifest['files']);
        foreach ($manifest['files'] as $name => $info) {
            $this->assertSame(hash_file('sha256', $directory.'/'.$name), $info['sha256']);
        }
        $tenant = Tenant::create(['name' => 'Trial Pack Test', 'code' => 'PACK', 'status' => 'active']);
        foreach (['01-biodata' => 'mahasiswa_biodata', '02-riwayat' => 'mahasiswa_riwayat_pendidikan'] as $file => $channel) {
            Storage::disk('uploads')->put($file.'.xlsx', file_get_contents($directory.'/'.$file.'.xlsx'));
            $batch = ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'file_path' => $file.'.xlsx', 'status' => 'uploaded']);
            app(ImportWorkbookParser::class)->parse($batch);
            $this->assertSame([], $batch->refresh()->summary['missing_sheets']);
            $this->assertSame(1, $batch->stagingRecords()->count());
            $this->assertSame($channel, $batch->stagingRecords()->firstOrFail()->channel);
            $this->assertSame('invalid', $batch->status); // Placeholder references must be replaced before trial.
        }
        $book = IOFactory::load($directory.'/01-biodata.xlsx');
        try {
            $sheet = $book->getSheetByName('mahasiswa_biodata');
            foreach ($sheet->getRowIterator(1, 1) as $row) {
                foreach ($row->getCellIterator() as $cell) {
                    if ($cell->getValue() === 'nik') {
                        $nik = $sheet->getCell($cell->getColumn().'2');
                        $this->assertSame('0000000000000001', $nik->getValue());
                        $this->assertSame(DataType::TYPE_STRING, $nik->getDataType());
                    }
                }
            }
        } finally {
            $book->disconnectWorksheets();
        }
        $this->assertSame(10, substr_count(file_get_contents($directory.'/response-log.csv'), 'BELUM DIUJI'));
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
