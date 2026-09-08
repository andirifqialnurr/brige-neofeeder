<?php

namespace App\Services\NeoFeeder;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

class NeoFeederClient
{
    public function __construct(
        private readonly HttpFactory $http,
    ) {
    }

    /**
     * @throws RequestException
     */
    public function post(string $baseUrl, string $action, array $payload = []): NeoFeederResponse
    {
        $timeoutSeconds = max(1, (int) ceil(config('services.neofeeder.timeout_ms', 30000) / 1000));

        $response = $this->http
            ->timeout($timeoutSeconds)
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
    public function getToken(string $baseUrl, string $username, string $password): NeoFeederResponse
    {
        return $this->post($baseUrl, 'GetToken', [
            'username' => $username,
            'password' => $password,
        ]);
    }
}
