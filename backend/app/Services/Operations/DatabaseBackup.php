<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PDO;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackup
{
    public function create(): string
    {
        if (config('database.default') !== 'mysql') {
            throw new RuntimeException('Backup ini memerlukan koneksi MySQL.');
        }
        $connection = DB::connection()->getConfig();
        $directory = storage_path('app/private/backups');
        if (! is_dir($directory) && ! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori backup tidak dapat dibuat.');
        }
        $path = $directory.'/backup-'.now()->format('Ymd-His').'-'.Str::uuid().'.sql';
        $handle = fopen($path.'.partial', 'xb');
        if (! $handle) {
            throw new RuntimeException('File backup tidak dapat dibuat.');
        }
        chmod($path.'.partial', 0600);
        try {
            $process = new Process([
                config('maintenance.mysqldump'), '--host='.$connection['host'], '--port='.$connection['port'],
                '--user='.$connection['username'], '--single-transaction', '--quick', '--skip-lock-tables',
                '--no-tablespaces', '--hex-blob', '--result-file='.$path.'.partial', $connection['database'],
            ], null, ['MYSQL_PWD' => $connection['password']], null, 600);
            // Close before mysqldump writes: Windows does not allow another process to overwrite an open file.
            fclose($handle);
            $handle = null;
            $process->run();
            if (! $process->isSuccessful() || filesize($path.'.partial') === 0) {
                throw new RuntimeException('Backup gagal. Periksa akses MySQL dan ketersediaan mysqldump.');
            }
            if (! rename($path.'.partial', $path)) {
                throw new RuntimeException('Backup tidak dapat diselesaikan.');
            }
            $manifest = ['version' => 1, 'database' => $connection['database'], 'created_at' => now()->toIso8601String(),
                'sha256' => hash_file('sha256', $path), 'bytes' => filesize($path)];
            if (file_put_contents($path.'.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
                throw new RuntimeException('Manifest backup tidak dapat disimpan.');
            }
            chmod($path.'.json', 0600);

            return $path;
        } finally {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if (is_file($path.'.partial')) {
                unlink($path.'.partial');
            }
        }
    }

    public function verify(string $filename): array
    {
        $directory = realpath(storage_path('app/private/backups'));
        $path = realpath($filename);
        if (! $directory || ! $path || dirname($path) !== $directory || ! str_ends_with($path, '.sql')) {
            throw new RuntimeException('Pilih file SQL dari direktori backup aplikasi.');
        }
        $manifest = json_decode((string) @file_get_contents($path.'.json'), true);
        if (! is_array($manifest) || ! isset($manifest['sha256']) || ! hash_equals($manifest['sha256'], hash_file('sha256', $path))) {
            throw new RuntimeException('Checksum backup tidak sesuai atau manifest tidak tersedia.');
        }
        $target = config('maintenance.restore');
        $source = DB::connection()->getConfig();
        if (! $target['host'] || ($target['host'] === $source['host'] && (int) $target['port'] === (int) $source['port'])) {
            throw new RuntimeException('Atur server MySQL khusus pemeriksaan restore, terpisah dari koneksi aplikasi.');
        }
        // A fresh, randomly named database is the only database this routine creates/drops.
        $name = 'bridge_restore_'.str_replace('-', '', (string) Str::uuid());
        $pdo = new PDO('mysql:host='.$target['host'].';port='.$target['port'], $target['username'], $target['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
        try {
            $input = fopen($path, 'rb');
            if (! $input) {
                throw new RuntimeException('Backup tidak dapat dibaca.');
            }
            try {
                $process = new Process([config('maintenance.mysql'), '--host='.$target['host'], '--port='.$target['port'],
                    '--user='.$target['username'], '--database='.$name, '--binary-mode'], null,
                    ['MYSQL_PWD' => $target['password']], $input, 600);
                $process->run();
                if (! $process->isSuccessful()) {
                    throw new RuntimeException('Restore uji gagal. Periksa kompatibilitas server dan client MySQL.');
                }
            } finally {
                fclose($input);
            }
            $tables = $pdo->query('SHOW TABLES FROM `'.$name.'`')->fetchAll(PDO::FETCH_COLUMN);
            if (! in_array('migrations', $tables, true) || ! in_array('tenants', $tables, true)) {
                throw new RuntimeException('Backup tidak memuat tabel inti aplikasi.');
            }
            $counts = [];
            foreach ($tables as $table) {
                $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `'.$name.'`.`'.str_replace('`', '``', $table).'`')->fetchColumn();
            }

            return ['sha256' => $manifest['sha256'], 'tables' => $counts, 'verified_at' => now()->toIso8601String()];
        } finally {
            $pdo->exec('DROP DATABASE `'.$name.'`');
        }
    }
}
