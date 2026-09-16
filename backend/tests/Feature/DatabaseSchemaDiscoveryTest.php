<?php

namespace Tests\Feature;

use App\Models\SourceConnection;
use App\Models\Tenant;
use App\Services\Mapping\DatabaseSchemaDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PDO;
use Tests\TestCase;

class DatabaseSchemaDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_discovery_detects_types_keys_relations_and_masked_samples(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('CREATE TABLE programs (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE)');
        $pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY, nim TEXT NOT NULL, name TEXT, program_id INTEGER, FOREIGN KEY(program_id) REFERENCES programs(id))');
        $pdo->exec("INSERT INTO programs VALUES (1, 'IF')");
        $pdo->exec("INSERT INTO students VALUES (1, '0001', 'Nama Fiktif', 1)");
        $source = SourceConnection::create([
            'tenant_id' => $this->tenantId(), 'type' => 'database', 'name' => 'siakad.students',
            'sha256' => str_repeat('a', 64), 'headers' => [], 'snapshot' => [], 'row_count' => 0,
            'connection_config' => ['database' => 'siakad'],
        ]);

        $catalog = app(DatabaseSchemaDiscoveryService::class)->discover($source, $pdo);

        $this->assertSame(2, $catalog['table_count']);
        $students = collect($catalog['tables'])->firstWhere('table_name', 'students');
        $this->assertSame(['id'], $students['primary_key_columns']);
        $this->assertSame(['id'], $students['candidate_key_columns']);
        $program = collect($students['columns'])->firstWhere('name', 'program_id');
        $this->assertTrue($program['is_foreign_key']);
        $this->assertSame('programs', $program['referenced_table']);
        $this->assertSame('id', $program['referenced_column']);
        $name = collect($students['columns'])->firstWhere('name', 'name');
        $this->assertSame(['[masked]'], $name['sample_values']);
        $nim = collect($students['columns'])->firstWhere('name', 'nim');
        $this->assertSame(['[masked]'], $nim['sample_values']);
        $code = collect($catalog['tables'])->firstWhere('table_name', 'programs')['columns'];
        $this->assertTrue(collect($code)->firstWhere('name', 'code')['is_unique_key']);
    }

    public function test_discovery_persists_catalog_and_replaces_old_snapshot(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE students (id INTEGER PRIMARY KEY, code TEXT NOT NULL UNIQUE)');
        $source = SourceConnection::create([
            'tenant_id' => $this->tenantId(), 'type' => 'database', 'name' => 'siakad.students',
            'sha256' => str_repeat('b', 64), 'headers' => [], 'snapshot' => [], 'row_count' => 0,
            'connection_config' => ['database' => 'siakad'],
        ]);
        $service = app(DatabaseSchemaDiscoveryService::class);
        $catalog = $service->discover($source, $pdo);
        $service->store($source, $catalog);
        $service->store($source, $catalog);

        $this->assertDatabaseCount('source_schema_tables', 1);
        $this->assertDatabaseCount('source_schema_columns', 2);
        $this->assertCount(1, $source->refresh()->schemaTables);
        $this->assertSame(['id'], $source->schemaTables()->firstOrFail()->primary_key_columns);
    }

    public function test_file_source_cannot_be_discovered_as_a_database(): void
    {
        $source = SourceConnection::create([
            'tenant_id' => $this->tenantId(), 'type' => 'file', 'name' => 'sample.csv',
            'sha256' => str_repeat('c', 64), 'headers' => ['name'], 'snapshot' => [], 'row_count' => 0,
        ]);

        $this->expectException(ValidationException::class);
        app(DatabaseSchemaDiscoveryService::class)->discover($source, new PDO('sqlite::memory:'));
    }

    private function tenantId(): string
    {
        return Tenant::create(['name' => 'Schema Test', 'code' => 'SCH'.random_int(1000, 9999), 'status' => 'active'])->id;
    }
}
