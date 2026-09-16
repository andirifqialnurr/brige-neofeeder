<?php

namespace App\Services\Mapping;

use PDO;

interface DatabaseSourceReaderContract
{
    /** @param array<string, mixed> $config @return array<string, mixed> */
    public function read(array $config, ?PDO $pdo = null): array;

    /** @param array<string, mixed> $config */
    public function connect(array $config): PDO;
}
