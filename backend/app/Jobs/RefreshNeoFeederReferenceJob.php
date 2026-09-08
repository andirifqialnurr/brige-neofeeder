<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class RefreshNeoFeederReferenceJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $endpoint,
    ) {
    }

    public function handle(): void
    {
        // Reference sync implementation will use NeoFeederClient and ReferenceRecord.
    }
}
