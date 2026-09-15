<?php

namespace App\Services\NeoFeeder;

use App\Models\NeoFeederConnection;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

class NeoFeederClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {}

    /**
     * @throws RequestException
     */
    public function post(string $baseUrl, string $action, array $payload = [], ?NeoFeederConnection $connection = null): NeoFeederResponse
    {
        if ($connection) {
            return app(ConnectionGuard::class)->run($connection, $action, fn () => $this->send($baseUrl, $action, $payload, $connection->timeout_ms));
        }

        return $this->send($baseUrl, $action, $payload);
    }

    private function send(string $baseUrl, string $action, array $payload, ?int $timeout = null): NeoFeederResponse
    {
        $timeoutSeconds = max(1, min(30, (int) ceil(($timeout ?? config('services.neofeeder.timeout_ms', 30000)) / 1000)));

        $response = $this->http
            ->timeout($timeoutSeconds)
            ->connectTimeout(min(5, $timeoutSeconds))
            ->asJson()
            ->post($baseUrl, [
                'act' => $action,
                ...$payload,
            ])
            ->throw()
            ->json();

        return NeoFeederResponse::fromArray(is_array($response) ? $response : []);
    }

    /**
     * @throws RequestException
     */
    public function getToken(string $baseUrl, string $username, string $password, ?NeoFeederConnection $connection = null): NeoFeederResponse
    {
        return $this->post($baseUrl, 'GetToken', [
            'username' => $username,
            'password' => $password,
        ], $connection);
    }
}
