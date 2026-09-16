<?php

namespace App\Services\Mapping;

use App\Models\SourceConnection;

interface DatabaseSchemaDiscoveryContract
{
    /** @return array<string, mixed> */
    public function discover(SourceConnection $source): array;

    /** @param array<string, mixed> $catalog */
    public function store(SourceConnection $source, array $catalog): SourceConnection;
}
