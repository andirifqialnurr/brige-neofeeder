<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Redis;

require __DIR__.'/../../vendor/autoload.php';
$name = getenv('DB_DATABASE');
if (getenv('APP_ENV') !== 'testing' || ! preg_match('/^bridge_integration_[a-f0-9]{32}$/', $name)) {
    exit(2);
}
$pdo = new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
if (($argv[1] ?? '') === 'create') {
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
} elseif (($argv[1] ?? '') === 'drop') {
    $pdo->exec('DROP DATABASE `'.$name.'`');
    $app = require __DIR__.'/../../bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    // Remove only this run's keys. Never FLUSHDB a shared Redis instance.
    $client = Redis::connection()->client();
    $cursor = 0;
    do {
        [$cursor, $keys] = $client->executeRaw(['SCAN', (string) $cursor, 'MATCH', $name.':*', 'COUNT', '100']);
        if ($keys !== []) {
            $client->executeRaw(['DEL', ...$keys]);
        }
    } while ((int) $cursor !== 0);
} else {
    exit(2);
}
