<?php

namespace Tests\Feature;

use App\Services\Mapping\DatabaseSourceReader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class DatabaseSourceTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_read_only_reader_snapshots_selected_columns_as_strings(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE students (NIM TEXT, Nama TEXT, Tahun INTEGER)');
        $pdo->exec("INSERT INTO students VALUES ('0001', 'Andi Fiktif', 2026)");
        $data = (new DatabaseSourceReader)->read([
            'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'secret',
            'table' => 'students', 'columns' => ['NIM', 'Nama', 'Tahun'],
        ], $pdo);

        $this->assertSame(['NIM', 'Nama', 'Tahun'], $data['headers']);
        $this->assertSame(1, $data['row_count']);
        $this->assertSame(['NIM' => '0001', 'Nama' => 'Andi Fiktif', 'Tahun' => '2026'], $data['snapshot'][0]['values']);
        $this->assertSame(1, $data['snapshot'][0]['row_number']);
    }

    public function test_database_source_rejects_identifier_injection_before_connecting(): void
    {
        [, , , $token] = $this->syncWorkspace();
        Queue::fake();
        $this->withToken($token)->postJson('/api/mapping/sources/database', [
            'connection' => [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'secret',
                'table' => 'students;drop', 'columns' => ['NIM'],
            ],
        ])->assertUnprocessable();
        $this->assertDatabaseCount('source_connections', 0);
    }

    public function test_database_source_rejects_empty_snapshot_and_duplicate_columns(): void
    {
        $reader = new DatabaseSourceReader;
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE students (NIM TEXT)');
        try {
            $reader->read([
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => '',
                'table' => 'students', 'columns' => ['NIM', 'NIM'],
            ], $pdo);
            $this->fail('Expected duplicate column validation.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('connection', $exception->errors());
        }
    }
}
