<?php

namespace App\Services\Mapping;

use App\Models\SourceConnection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PDO;
use Throwable;

final class DatabaseSchemaDiscoveryService
{
    public const MAX_TABLES = 200;

    public const MAX_COLUMNS_PER_TABLE = 128;

    public const SAMPLE_LIMIT = 5;

    public function __construct(private readonly DatabaseSourceReader $reader) {}

    /**
     * @return array{database:string, tables:list<array<string,mixed>>, table_count:int, column_count:int, discovered_at:string}
     */
    public function discover(SourceConnection $source, ?PDO $pdo = null): array
    {
        if ($source->type !== 'database' || ! is_array($source->connection_config)) {
            throw ValidationException::withMessages(['source' => 'Sumber harus berupa koneksi database.']);
        }

        $config = $source->connection_config;
        $database = trim((string) ($config['database'] ?? ''));
        if ($database === '') {
            throw ValidationException::withMessages(['source' => 'Nama database sumber tidak tersedia.']);
        }

        $pdo ??= $this->reader->connect($config);
        $tables = $this->readTables($pdo, $database);
        if (count($tables) > self::MAX_TABLES) {
            throw ValidationException::withMessages(['source' => 'Schema sumber melebihi batas 200 tabel per discovery.']);
        }

        $catalog = [];
        $columnCount = 0;
        foreach ($tables as $table) {
            $columns = $this->readColumns($pdo, $database, $table['table_name']);
            if (count($columns) > self::MAX_COLUMNS_PER_TABLE) {
                throw ValidationException::withMessages(['source' => "Tabel {$table['table_name']} melebihi batas 128 kolom per discovery."]);
            }

            $samples = $this->readSamples($pdo, $table['table_name'], array_column($columns, 'name'));
            $primary = array_values(array_filter($columns, fn (array $column): bool => $column['is_primary_key']));
            $primaryNames = array_values(array_column($primary, 'name'));
            $candidateNames = $this->candidateKeyNames($columns, $primaryNames);
            $columnCount += count($columns);

            $catalog[] = [
                ...$table,
                'primary_key_columns' => $primaryNames,
                'candidate_key_columns' => $candidateNames,
                'columns' => array_map(function (array $column) use ($samples, $candidateNames): array {
                    $name = $column['name'];
                    $column['is_candidate_primary_key'] = in_array($name, $candidateNames, true);
                    $column['sample_values'] = $samples[$name] ?? [];

                    return $column;
                }, $columns),
            ];
        }

        return [
            'database' => $database,
            'tables' => $catalog,
            'table_count' => count($catalog),
            'column_count' => $columnCount,
            'discovered_at' => now()->toISOString(),
        ];
    }

    public function store(SourceConnection $source, array $catalog): SourceConnection
    {
        return DB::transaction(function () use ($source, $catalog): SourceConnection {
            $source = SourceConnection::query()->lockForUpdate()->findOrFail($source->id);
            $source->schemaTables()->delete();
            foreach ($catalog['tables'] ?? [] as $table) {
                $schemaTable = $source->schemaTables()->create([
                    'table_name' => $table['table_name'],
                    'table_type' => $table['table_type'],
                    'estimated_rows' => $table['estimated_rows'],
                    'primary_key_columns' => $table['primary_key_columns'],
                    'candidate_key_columns' => $table['candidate_key_columns'],
                ]);
                foreach ($table['columns'] as $column) {
                    $schemaTable->columns()->create($column);
                }
            }

            return $source->load('schemaTables.columns');
        });
    }

    /** @return list<array{table_name:string,table_type:string,estimated_rows:int|null}> */
    private function readTables(PDO $pdo, string $database): array
    {
        if ($this->driver($pdo) === 'sqlite') {
            $rows = $pdo->query("SELECT name AS table_name, 'BASE TABLE' AS table_type, NULL AS estimated_rows FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } else {
            $statement = $pdo->prepare("SELECT TABLE_NAME AS table_name, TABLE_TYPE AS table_type, TABLE_ROWS AS estimated_rows FROM information_schema.TABLES WHERE TABLE_SCHEMA = :schema AND TABLE_TYPE IN ('BASE TABLE', 'VIEW') ORDER BY TABLE_NAME LIMIT ".(self::MAX_TABLES + 1));
            $statement->execute(['schema' => $database]);
            $rows = $statement->fetchAll(PDO::FETCH_ASSOC);
        }

        return array_map(fn (array $row): array => [
            'table_name' => (string) $row['table_name'],
            'table_type' => (string) ($row['table_type'] ?? 'BASE TABLE'),
            'estimated_rows' => is_numeric($row['estimated_rows'] ?? null) ? (int) $row['estimated_rows'] : null,
        ], $rows);
    }

    /** @return list<array<string,mixed>> */
    private function readColumns(PDO $pdo, string $database, string $table): array
    {
        if ($this->driver($pdo) === 'sqlite') {
            return $this->readSqliteColumns($pdo, $table);
        }

        $statement = $pdo->prepare('SELECT c.COLUMN_NAME AS name, c.ORDINAL_POSITION AS ordinal_position, c.DATA_TYPE AS data_type, c.COLUMN_TYPE AS column_type, c.IS_NULLABLE AS is_nullable, c.COLUMN_KEY AS column_key, k.REFERENCED_TABLE_NAME AS referenced_table, k.REFERENCED_COLUMN_NAME AS referenced_column FROM information_schema.COLUMNS c LEFT JOIN information_schema.KEY_COLUMN_USAGE k ON k.CONSTRAINT_SCHEMA = c.TABLE_SCHEMA AND k.TABLE_NAME = c.TABLE_NAME AND k.COLUMN_NAME = c.COLUMN_NAME AND k.REFERENCED_TABLE_NAME IS NOT NULL WHERE c.TABLE_SCHEMA = :schema AND c.TABLE_NAME = :table ORDER BY c.ORDINAL_POSITION');
        $statement->execute(['schema' => $database, 'table' => $table]);

        return array_map(fn (array $row): array => $this->normalizeColumn($row), $statement->fetchAll(PDO::FETCH_ASSOC));
    }

    /** @return list<array<string,mixed>> */
    private function readSqliteColumns(PDO $pdo, string $table): array
    {
        $foreignKeys = [];
        try {
            $foreignStatement = $pdo->query('PRAGMA foreign_key_list('.$this->quoteIdentifier($table).')');
            foreach ($foreignStatement?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $foreign) {
                $foreignKeys[(string) $foreign['from']] = ['table' => (string) $foreign['table'], 'column' => (string) $foreign['to']];
            }
        } catch (Throwable) {
            // Some SQLite test doubles do not expose foreign key metadata.
        }
        $uniqueColumns = $this->sqliteUniqueColumns($pdo, $table);
        $statement = $pdo->query('PRAGMA table_info('.$this->quoteIdentifier($table).')');
        $rows = $statement?->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row) use ($foreignKeys, $uniqueColumns): array {
            $name = (string) $row['name'];
            $foreign = $foreignKeys[$name] ?? null;
            $primary = (int) ($row['pk'] ?? 0) > 0;

            return $this->normalizeColumn([
                'name' => $name,
                'ordinal_position' => ((int) ($row['cid'] ?? 0)) + 1,
                'data_type' => strtolower((string) ($row['type'] ?? 'text')),
                'column_type' => (string) ($row['type'] ?? 'text'),
                'is_nullable' => ((int) ($row['notnull'] ?? 0)) === 0,
                'column_key' => $primary ? 'PRI' : (in_array($name, $uniqueColumns, true) ? 'UNI' : ''),
                'referenced_table' => $foreign['table'] ?? null,
                'referenced_column' => $foreign['column'] ?? null,
            ]);
        }, $rows);
    }

    /** @return list<string> */
    private function sqliteUniqueColumns(PDO $pdo, string $table): array
    {
        $columns = [];
        $statement = $pdo->query('PRAGMA index_list('.$this->quoteIdentifier($table).')');
        foreach ($statement?->fetchAll(PDO::FETCH_ASSOC) ?: [] as $index) {
            if ((int) ($index['unique'] ?? 0) !== 1) {
                continue;
            }
            $info = $pdo->query('PRAGMA index_info('.$this->quoteIdentifier((string) $index['name']).')');
            $indexColumns = $info?->fetchAll(PDO::FETCH_ASSOC) ?: [];
            if (count($indexColumns) === 1) {
                $columns[] = (string) $indexColumns[0]['name'];
            }
        }

        return $columns;
    }

    /** @param list<string> $columns */
    /** @return array<string,list<string>> */
    private function readSamples(PDO $pdo, string $table, array $columns): array
    {
        if ($columns === []) {
            return [];
        }
        $quoted = implode(',', array_map(fn (string $column): string => $this->quoteIdentifier($column), $columns));
        try {
            $statement = $pdo->query('SELECT '.$quoted.' FROM '.$this->quoteIdentifier($table).' LIMIT '.self::SAMPLE_LIMIT);
            $rows = $statement?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $samples = array_fill_keys($columns, []);
        foreach ($rows as $row) {
            foreach ($columns as $column) {
                $value = $this->maskSample($column, $row[$column] ?? null);
                if ($value !== null && ! in_array($value, $samples[$column], true)) {
                    $samples[$column][] = $value;
                }
            }
        }

        return $samples;
    }

    /** @param array<string,mixed> $row */
    private function normalizeColumn(array $row): array
    {
        $name = (string) $row['name'];
        $referencedTable = $row['referenced_table'] ?: null;
        $foreignKey = $referencedTable !== null;
        $primary = strtoupper((string) ($row['column_key'] ?? '')) === 'PRI';
        $unique = strtoupper((string) ($row['column_key'] ?? '')) === 'UNI';
        $candidateRelation = $foreignKey || (! $primary && (bool) preg_match('/(^id_|_id$|^kode_|_kode$|^code_|_code$|^(nim|nip|nidn|nup)$)/i', $name));

        return [
            'name' => $name,
            'ordinal_position' => (int) $row['ordinal_position'],
            'data_type' => strtolower((string) ($row['data_type'] ?? 'unknown')),
            'column_type' => ($row['column_type'] ?? null) ?: null,
            'is_nullable' => strtoupper((string) ($row['is_nullable'] ?? '')) === 'YES' || (bool) $row['is_nullable'] === true,
            'is_primary_key' => $primary,
            'is_unique_key' => $unique,
            'is_candidate_primary_key' => false,
            'is_foreign_key' => $foreignKey,
            'is_candidate_relation' => $candidateRelation,
            'relation_confidence' => $foreignKey ? 'foreign_key' : ($candidateRelation ? 'name_pattern' : null),
            'referenced_table' => $referencedTable,
            'referenced_column' => $row['referenced_column'] ?: null,
        ];
    }

    /** @param list<array<string,mixed>> $columns @param list<string> $primaryNames @return list<string> */
    private function candidateKeyNames(array $columns, array $primaryNames): array
    {
        if (count($primaryNames) === 1) {
            $primary = collect($columns)->firstWhere('name', $primaryNames[0]);
            if ($primary && (! $primary['is_nullable'] || $primary['is_primary_key'])) {
                return $primaryNames;
            }
        }

        return array_values(array_map(fn (array $column): string => $column['name'], array_filter($columns, fn (array $column): bool => ! $column['is_nullable'] && ! $column['is_primary_key'] && $column['is_unique_key'])));
    }

    private function driver(PDO $pdo): string
    {
        return strtolower((string) $pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
    }

    private function quoteIdentifier(string $identifier): string
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw ValidationException::withMessages(['source' => 'Identifier schema tidak valid.']);
        }

        return '`'.$identifier.'`';
    }

    private function maskSample(string $column, mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $key = strtolower(preg_replace('/[^a-z0-9]+/i', '_', $column));
        if (preg_match('/(^|_)(address|alamat|birth|credential|email|handphone|ibu|id|lahir|name|nama|nidn|nik|nim|nip|npwp|nup|password|phone|secret|telepon|token|uuid)(_|$)/', $key)) {
            return '[masked]';
        }

        return mb_substr((string) $value, 0, 80);
    }
}
