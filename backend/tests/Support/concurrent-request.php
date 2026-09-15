<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('testing') || ! str_starts_with(config('database.connections.mysql.database'), 'bridge_integration_')) {
    exit(2);
}
$barrier = getenv('INTEGRATION_BARRIER');
Cache::increment($barrier.':ready');
$deadline = microtime(true) + 15;
while (! Cache::get($barrier.':go')) {
    if (microtime(true) > $deadline) {
        exit(3);
    }
    usleep(10000);
}
$request = Request::create(getenv('INTEGRATION_PATH'), 'POST', [], [], [], [
    'HTTP_AUTHORIZATION' => 'Bearer '.getenv('INTEGRATION_TOKEN'),
    'HTTP_ACCEPT' => 'application/json',
]);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$response = $kernel->handle($request);
echo $response->getStatusCode();
$kernel->terminate($request, $response);
exit($response->isSuccessful() ? 0 : 1);
