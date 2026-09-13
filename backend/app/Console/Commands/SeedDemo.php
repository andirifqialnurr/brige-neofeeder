<?php

namespace App\Console\Commands;

use Database\Seeders\DemoDataSeeder;
use Illuminate\Console\Command;

class SeedDemo extends Command
{
    protected $signature = 'bridge:seed-demo {--allow-production : Explicitly allow isolated demo tenants in production}';

    protected $description = 'Create a small synthetic dataset without contacting Neo Feeder';

    public function handle(): int
    {
        if (app()->isProduction() && ! $this->option('allow-production')) {
            $this->error('Use a local database, or explicitly pass --allow-production.');

            return self::FAILURE;
        }
        $password = $this->secret('Password akun demo baru (minimal 12 karakter; akun lama tidak diubah)');
        if (! is_string($password) || strlen($password) < 12) {
            $this->error('Password minimal 12 karakter.');

            return self::FAILURE;
        }
        config(['demo.password' => $password, 'demo.allow_production' => (bool) $this->option('allow-production')]);
        try {
            app(DemoDataSeeder::class)->run();
            $this->info('Demo tersedia: demo-operator@example.test dan demo-empty@example.test.');
            $this->info('Workbook: disk uploads / demo-v1/. Lihat cookbook/demo-data.md.');

            return self::SUCCESS;
        } finally {
            config(['demo.password' => null, 'demo.allow_production' => false]);
        }
    }
}
