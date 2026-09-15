<?php

namespace App\Console\Commands;

use App\Services\Operations\DatabaseBackup;
use Illuminate\Console\Command;
use Throwable;

class BackupDatabase extends Command
{
    protected $signature = 'bridge:backup {--verify= : Verify a backup on the configured isolated restore server}';

    protected $description = 'Create a private MySQL backup or verify its restore in an isolated database';

    public function handle(DatabaseBackup $backup): int
    {
        try {
            if ($path = $this->option('verify')) {
                $this->line(json_encode($backup->verify($path), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            } else {
                $this->line($backup->create());
            }

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Backup/pemeriksaan gagal. Periksa konfigurasi, akses file, client MySQL, dan server restore terpisah.');

            return self::FAILURE;
        }
    }
}
