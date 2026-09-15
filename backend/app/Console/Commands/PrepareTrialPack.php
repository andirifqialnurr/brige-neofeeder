<?php

namespace App\Console\Commands;

use App\Services\Templates\TrialPackService;
use Illuminate\Console\Command;

class PrepareTrialPack extends Command
{
    protected $signature = 'bridge:trial-pack';

    protected $description = 'Generate synthetic trial workbooks, mapping CSVs, contract baseline and response log without outbound requests';

    public function handle(TrialPackService $service): int
    {
        $this->line($service->generate());
        $this->info('Paket fiktif siap. Isi referensi trial dan tinjau dry-run sebelum meminta persetujuan pengiriman.');

        return self::SUCCESS;
    }
}
