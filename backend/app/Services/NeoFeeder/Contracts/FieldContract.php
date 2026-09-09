<?php

namespace App\Services\NeoFeeder\Contracts;

use InvalidArgumentException;

final readonly class FieldContract
{
    public function __construct(
        public string $name,
        public string $label,
        public string $type = 'string',
        public bool $required = false,
        public bool $primary = false,
        public bool $nullable = true,
        public ?int $maxLength = null,
        public ?string $reference = null,
        public ?string $format = null,
        public mixed $example = null,
        public string $description = '',
        public array $rules = [],
    ) {
        if ($this->name === '') {
            throw new InvalidArgumentException('Field contract name cannot be empty.');
        }

        if ($this->label === '') {
            throw new InvalidArgumentException('Field contract label cannot be empty.');
        }
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            name: (string) ($payload['name'] ?? ''),
            label: (string) ($payload['label'] ?? ''),
            type: (string) ($payload['type'] ?? 'string'),
            required: (bool) ($payload['required'] ?? false),
            primary: (bool) ($payload['primary'] ?? false),
            nullable: (bool) ($payload['nullable'] ?? true),
            maxLength: isset($payload['max_length']) ? (int) $payload['max_length'] : null,
            reference: isset($payload['reference']) ? (string) $payload['reference'] : null,
            format: isset($payload['format']) ? (string) $payload['format'] : null,
            example: $payload['example'] ?? null,
            description: (string) ($payload['description'] ?? ''),
            rules: $payload['rules'] ?? [],
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'label' => $this->label,
            'type' => $this->type,
            'required' => $this->required,
            'primary' => $this->primary,
            'nullable' => $this->nullable,
            'max_length' => $this->maxLength,
            'reference' => $this->reference,
            'format' => $this->format,
            'example' => $this->example,
            'description' => $this->description,
            'rules' => $this->rules,
        ];
    }
}
