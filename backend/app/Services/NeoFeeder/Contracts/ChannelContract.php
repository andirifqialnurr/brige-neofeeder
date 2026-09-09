<?php

namespace App\Services\NeoFeeder\Contracts;

use InvalidArgumentException;

final readonly class ChannelContract
{
    public function __construct(
        public string $key,
        public string $label,
        public string $description = '',
        public array $fields = [],
        public array $operations = [],
        public array $dependsOn = [],
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
            fields: $payload['fields'] ?? [],
            operations: $payload['operations'] ?? [],
            dependsOn: $payload['depends_on'] ?? [],
            sortOrder: (int) ($payload['sort_order'] ?? 0),
        );
    }

    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'label' => $this->label,
            'description' => $this->description,
            'fields' => $this->fields,
            'operations' => $this->operations,
            'depends_on' => $this->dependsOn,
            'sort_order' => $this->sortOrder,
        ];
    }
}
