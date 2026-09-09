<?php

namespace App\Services\NeoFeeder\Contracts;

use InvalidArgumentException;

final readonly class OperationContract
{
    public function __construct(
        public string $name,
        public string $action,
        public string $type,
        public bool $requiresToken = true,
        public string $payloadMode = 'data',
        public array $keyFields = [],
        public array $requestFields = [],
        public array $responseFields = [],
        public string $description = '',
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Operation contract name cannot be empty.');
        }

        if ($this->action === '') {
            throw new InvalidArgumentException('Operation contract action cannot be empty.');
        }

        if ($this->type === '') {
            throw new InvalidArgumentException('Operation contract type cannot be empty.');
        }
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            action: (string) ($payload['action'] ?? ''),
            type: (string) ($payload['type'] ?? ''),
            requiresToken: (bool) ($payload['requires_token'] ?? true),
            payloadMode: (string) ($payload['payload_mode'] ?? 'data'),
            keyFields: $payload['key_fields'] ?? [],
            requestFields: $payload['request_fields'] ?? [],
            responseFields: $payload['response_fields'] ?? [],
            description: (string) ($payload['description'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'action' => $this->action,
            'type' => $this->type,
            'requires_token' => $this->requiresToken,
            'payload_mode' => $this->payloadMode,
            'key_fields' => $this->keyFields,
            'request_fields' => $this->requestFields,
            'response_fields' => $this->responseFields,
            'description' => $this->description,
        ];
    }
}
