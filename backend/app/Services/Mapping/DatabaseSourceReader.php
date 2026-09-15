<?php

namespace App\Services\Mapping;

use Illuminate\Validation\ValidationException;
use PDO;

final class DatabaseSourceReader
{
    public const MAX_ROWS = 2000;

    /**
     * @param  array{host:string,port:int,database:string,username:string,password:string,table:string,columns:list<string>}  $config
     * @return array{headers:list<string>,snapshot:list<array{row_number:int,values:array<string,mixed>}>,row_count:int}
     */
    public function read(array $config, ?PDO $pdo = null): array
    {
        $this->validateIdentifiers($config['table'], $config['columns']);
        $pdo ??= $this->connect($config);
        $columns = implode(',', array_map(fn (string $column): string => $this->quoteIdentifier($column), $config['columns']));
        $table = $this->quoteIdentifier($config['table']);
        $statement = $pdo->query("SELECT {$columns} FROM {$table} LIMIT ".(self::MAX_ROWS + 1));
        $rows = $statement?->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if (count($rows) > self::MAX_ROWS) {
            throw ValidationException::withMessages(['connection' => 'Tabel sumber melebihi batas 2.000 baris per snapshot.']);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['connection' => 'Tabel sumber tidak memiliki baris data.']);
        }

        return [
            'headers' => array_values($config['columns']),
            'snapshot' => array_map(fn (array $row, int $index): array => ['row_number' => $index + 1, 'values' => array_map(fn ($value) => $value === null ? null : (string) $value, $row)], $rows, array_keys($rows)),
            'row_count' => count($rows),
        ];
    }

    /** @param list<string> $columns */
    private function validateIdentifiers(string $table, array $columns): void
    {
        if (! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table) || $columns === [] || count($columns) > 64) {
            throw ValidationException::withMessages(['connection' => 'Nama tabel atau kolom tidak valid.']);
        }
        foreach ($columns as $column) {
            if (! is_string($column) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $column)) {
                throw ValidationException::withMessages(['connection' => 'Nama tabel atau kolom tidak valid.']);
            }
        }
        if (count(array_unique($columns)) !== count($columns)) {
            throw ValidationException::withMessages(['connection' => 'Kolom sumber harus unik.']);
        }
    }

    /** @param array{host:string,port:int,database:string,username:string,password:string} $config */
    private function connect(array $config): PDO
    {
        $host = trim($config['host']);
        if ($host === '' || preg_match('/[\r\n]/', $host)) {
            throw ValidationException::withMessages(['connection' => 'Host database tidak valid.']);
        }
        $port = (int) $config['port'];
        if ($port < 1 || $port > 65535) {
            throw ValidationException::withMessages(['connection' => 'Port database tidak valid.']);
        }

        try {
            return new PDO(
                "mysql:host={$host};port={$port};dbname=".$this->quoteDsnValue($config['database']),
                $config['username'],
                $config['password'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_TIMEOUT => 5, PDO::MYSQL_ATTR_INIT_COMMAND => 'SET SESSION TRANSACTION READ ONLY'],
            );
        } catch (\Throwable) {
            throw ValidationException::withMessages(['connection' => 'Database SIAKAD tidak dapat dihubungi atau credential ditolak.']);
        }
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '`'.$identifier.'`';
    }

    private function quoteDsnValue(string $value): string
    {
        if ($value === '' || preg_match('/[;\x00-\x1F]/', $value)) {
            throw ValidationException::withMessages(['connection' => 'Nama database tidak valid.']);
        }

        return $value;
    }
}
