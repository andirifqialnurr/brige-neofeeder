<?php

namespace Tests\Feature;

use App\Models\SyncAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class FileRetentionTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_preview_preserves_files_and_apply_preserves_staging(): void
    {
        Storage::fake('uploads');
        [$batch, $row] = $this->syncWorkspace();
        $path = 'imports/'.$batch->tenant_id.'/sample.xlsx';
        Storage::disk('uploads')->put($path, 'synthetic workbook');
        $batch->forceFill(['status' => 'synced', 'file_path' => $path, 'updated_at' => now()->subDays(91)])->save();
        $this->artisan('bridge:prune-import-files --days=90')->assertSuccessful();
        Storage::disk('uploads')->assertExists($path);
        $this->artisan('bridge:prune-import-files --days=90 --apply')->assertSuccessful();
        Storage::disk('uploads')->assertMissing($path);
        $this->assertNull($batch->refresh()->file_path);
        $this->assertDatabaseHas('staging_records', ['id' => $row->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => 'import.file.pruned', 'subject_id' => $batch->id]);
    }

    public function test_unknown_delivery_and_unsafe_paths_are_preserved(): void
    {
        Storage::fake('uploads');
        [$batch, $row] = $this->syncWorkspace();
        $path = 'imports/'.$batch->tenant_id.'/sample.xlsx';
        Storage::disk('uploads')->put($path, 'synthetic workbook');
        $batch->forceFill(['status' => 'failed', 'file_path' => $path, 'updated_at' => now()->subDays(91)])->save();
        SyncAttempt::create(['tenant_id' => $batch->tenant_id, 'staging_record_id' => $row->id, 'action' => 'InsertBiodataMahasiswa', 'status' => 'unknown']);
        $this->artisan('bridge:prune-import-files --days=90 --apply')->assertSuccessful();
        Storage::disk('uploads')->assertExists($path);
        $this->artisan('bridge:prune-import-files --days=0 --apply')->assertFailed();
        $row->syncAttempts()->delete();
        $batch->forceFill(['file_path' => '../outside.xlsx', 'updated_at' => now()->subDays(91)])->save();
        $this->artisan('bridge:prune-import-files --days=90 --apply')->assertSuccessful();
        $this->assertSame('../outside.xlsx', $batch->refresh()->file_path);
    }
}
