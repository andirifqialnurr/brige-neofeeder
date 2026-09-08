<?php

namespace App\Services\NeoFeeder;

final readonly class NeoFeederResponse
{
    public function __construct(
        public string $errorCode,
        public string $errorDesc,
        public mixed $data,
        public array $raw,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            errorCode: (string) ($payload['error_code'] ?? ''),
            errorDesc: (string) ($payload['error_desc'] ?? ''),
            data: $payload['data'] ?? null,
            raw: $payload,
        );
    }

    public function successful(): bool
    {
        return $this->errorCode === '0';
    }
}
