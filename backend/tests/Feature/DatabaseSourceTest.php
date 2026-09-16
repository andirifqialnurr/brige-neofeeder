<?php

namespace Tests\Feature;

use App\Models\SourceConnection;
use App\Models\SourceSchemaTable;
use App\Services\Mapping\DatabaseSourceReader;
use App\Services\Mapping\DatabaseSourceReaderContract;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Mockery;
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

    public function test_database_source_can_register_connection_before_schema_discovery(): void
    {
        [, , , $token] = $this->syncWorkspace();
        $response = $this->withToken($token)->postJson('/api/mapping/sources/database', [
            'connection' => [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'secret',
                'table' => '', 'columns' => [],
            ],
        ])->assertCreated();

        $response->assertJsonPath('data.type', 'database')
            ->assertJsonPath('data.name', 'siakad')
            ->assertJsonPath('data.row_count', 0)
            ->assertJsonPath('data.schema_discovery_status', 'idle');
        $this->assertDatabaseHas('source_connections', ['name' => 'siakad', 'row_count' => 0]);
        $this->assertStringNotContainsString('connection_config', $response->getContent());
    }

    public function test_database_source_rejects_only_one_of_table_and_columns(): void
    {
        [, , , $token] = $this->syncWorkspace();
        $this->withToken($token)->postJson('/api/mapping/sources/database', [
            'connection' => [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'secret',
                'table' => 'students', 'columns' => [],
            ],
        ])->assertUnprocessable();
    }

    public function test_existing_database_source_can_snapshot_selected_schema_table(): void
    {
        [, , $user, $token] = $this->syncWorkspace();
        $source = SourceConnection::create([
            'tenant_id' => $user->tenant_id, 'type' => 'database', 'name' => 'siakad',
            'sha256' => str_repeat('e', 64), 'headers' => [], 'snapshot' => [], 'row_count' => 0,
            'schema_discovery_status' => 'ready',
            'connection_config' => [
                'host' => '127.0.0.1', 'port' => 3306, 'database' => 'siakad', 'username' => 'readonly', 'password' => 'secret',
                'table' => '', 'columns' => [],
            ],
        ]);
        $reader = Mockery::mock(DatabaseSourceReaderContract::class);
        $reader->shouldReceive('read')->once()->withArgs(fn (array $config): bool => $config['table'] === 'mahasiswa'
            && $config['columns'] === ['nim', 'nama'])->andReturn([
                'headers' => ['nim', 'nama'],
                'snapshot' => [['row_number' => 1, 'values' => ['nim' => '0001', 'nama' => 'Mahasiswa Fiktif']]],
                'row_count' => 1,
            ]);
        $this->app->instance(DatabaseSourceReaderContract::class, $reader);

        $response = $this->withToken($token)->postJson("/api/mapping/sources/{$source->id}/snapshot", [
            'table' => 'mahasiswa', 'columns' => ['nim', 'nama'],
        ])->assertOk();

        $response->assertJsonPath('data.id', $source->id)->assertJsonPath('data.row_count', 1);
        $this->assertDatabaseHas('source_connections', ['id' => $source->id, 'name' => 'siakad.mahasiswa', 'row_count' => 1]);
        $this->assertStringNotContainsString('secret', $response->getContent());
    }

    public function test_schema_endpoint_exposes_incremental_readiness_without_connection_config(): void
    {
        [, , $user, $token] = $this->syncWorkspace();
        $source = SourceConnection::create([
            'tenant_id' => $user->tenant_id, 'type' => 'database', 'name' => 'siakad',
            'sha256' => str_repeat('f', 64), 'headers' => [], 'snapshot' => [], 'row_count' => 0,
            'schema_discovery_status' => 'ready',
            'connection_config' => ['database' => 'siakad'],
        ]);
        $table = SourceSchemaTable::create([
            'source_connection_id' => $source->id, 'table_name' => 'mahasiswa', 'table_type' => 'BASE TABLE',
            'estimated_rows' => 1, 'primary_key_columns' => ['id'], 'candidate_key_columns' => ['id'],
        ]);
        $table->columns()->createMany([
            ['name' => 'id', 'ordinal_position' => 1, 'data_type' => 'int', 'is_nullable' => false, 'is_primary_key' => true],
            ['name' => 'updated_at', 'ordinal_position' => 2, 'data_type' => 'datetime', 'is_nullable' => true],
        ]);

        $response = $this->withToken($token)->getJson("/api/mapping/sources/{$source->id}/schema")->assertOk();

        $response->assertJsonPath('data.tables.0.incremental_readiness.status', 'ready')
            ->assertJsonPath('data.tables.0.incremental_readiness.activation_allowed', false)
            ->assertJsonPath('data.tables.0.incremental_readiness.timestamp_columns.0.name', 'updated_at');
        $this->assertStringNotContainsString('connection_config', $response->getContent());
    }
}
