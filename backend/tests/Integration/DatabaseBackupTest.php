<?php

namespace Tests\Integration;

use App\Models\Tenant;
use App\Services\Operations\DatabaseBackup;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Group;
use RuntimeException;
use Tests\TestCase;

#[Group('backup')]
class DatabaseBackupTest extends TestCase
{
    public function test_dump_restores_core_tables_and_rows_on_separate_server(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertStringStartsWith('bridge_integration_', DB::connection()->getDatabaseName());
        Tenant::create(['name' => 'Kampus Backup Fiktif', 'code' => 'BACKUP-TEST', 'status' => 'active']);
        $service = app(DatabaseBackup::class);
        $path = $service->create();
        $this->assertFileExists($path.'.json');
        $result = $service->verify($path);
        $this->assertSame(Tenant::count(), $result['tables']['tenants']);
        $this->assertSame(DB::table('migrations')->count(), $result['tables']['migrations']);
        $this->assertSame(hash_file('sha256', $path), $result['sha256']);
        $this->assertStringContainsString('Kampus Backup Fiktif', file_get_contents($path));
        file_put_contents($path, "\n-- tampered", FILE_APPEND);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Checksum');
        $service->verify($path);
    }

    public function test_restore_refuses_the_application_server(): void
    {
        $service = app(DatabaseBackup::class);
        $path = $service->create();
        config(['maintenance.restore.host' => config('database.connections.mysql.host'),
            'maintenance.restore.port' => config('database.connections.mysql.port')]);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('terpisah');
        $service->verify($path);
    }
}
