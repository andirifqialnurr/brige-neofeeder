<?php

namespace App\Services\NeoFeeder\Contracts;

use InvalidArgumentException;

final readonly class ChannelContract
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description = '',
        public string $sheetName = '',
        public array $fields = [],
        public array $operations = [],
        public array $dependsOn = [],
        public array $naturalKey = [],
        public array $identityFields = [],
        public int $sortOrder = 0,
    ) {
        if ($this->key === '') {
            throw new InvalidArgumentException('Channel contract key cannot be empty.');
        }

        if ($this->label === '') {
            throw new InvalidArgumentException('Channel contract label cannot be empty.');
        }
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            key: (string) ($payload['key'] ?? ''),
            label: (string) ($payload['label'] ?? ''),
            description: (string) ($payload['description'] ?? ''),
            sheetName: (string) ($payload['sheet_name'] ?? $payload['key'] ?? ''),
            fields: $payload['fields'] ?? [],
            operations: $payload['operations'] ?? [],
            dependsOn: $payload['depends_on'] ?? [],
            naturalKey: $payload['natural_key'] ?? [],
            identityFields: $payload['identity_fields'] ?? [],
            sortOrder: (int) ($payload['sort_order'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'sheet_name' => $this->sheetName,
            'fields' => $this->fields,
            'operations' => $this->operations,
            'depends_on' => $this->dependsOn,
            'natural_key' => $this->naturalKey,
            'identity_fields' => $this->identityFields,
            'sort_order' => $this->sortOrder,
        ];
    }
}
