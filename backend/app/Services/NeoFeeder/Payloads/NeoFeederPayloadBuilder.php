<?php

namespace App\Services\NeoFeeder\Payloads;

use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\FieldContract;
use App\Services\NeoFeeder\Contracts\OperationContract;
use InvalidArgumentException;

final class NeoFeederPayloadBuilder
{
    /**
     * Build Neo Feeder read/list payload fragment. The client still owns `act` and `token`.
     */
    public function buildReadList(OperationContract $operation, array $input = []): array
    {
        if (! in_array($operation->type, ['read', 'list'], true)) {
            throw new InvalidArgumentException("Operation [{$operation->name}] is not a read/list operation.");
        }

        if ($operation->payloadMode !== 'filter') {
            throw new InvalidArgumentException("Operation [{$operation->name}] does not use filter payload mode.");
        }

        $payload = [];
        $filter = $this->resolveFilter($operation, $input);

        if ($filter !== null) {
            $payload['filter'] = $filter;
        }

        foreach (['order', 'limit', 'offset'] as $option) {
            if (array_key_exists($option, $input) && $input[$option] !== null && $input[$option] !== '') {
                $payload[$option] = $input[$option];
            }
        }

        return $payload;
    }

    /**
     * Build Neo Feeder insert payload fragment for standard `record` operations.
     */
    public function buildInsert(ChannelContract $channel, OperationContract $operation, array $input): array
    {
        if ($operation->type !== 'insert') {
            throw new InvalidArgumentException("Operation [{$operation->name}] is not an insert operation.");
        }

        if ($operation->payloadMode !== 'record') {
            throw new InvalidArgumentException("Operation [{$operation->name}] does not use record payload mode.");
        }

        return [
            'record' => $this->buildRecord($channel, $input, skipPrimary: true, enforceRequired: true),
        ];
    }

    /**
     * Build Neo Feeder update payload fragment for standard `key` + `record` operations.
     */
    public function buildUpdate(ChannelContract $channel, OperationContract $operation, array $input): array
    {
        if ($operation->type !== 'update') {
            throw new InvalidArgumentException("Operation [{$operation->name}] is not an update operation.");
        }

        if ($operation->payloadMode !== 'key_record') {
            throw new InvalidArgumentException("Operation [{$operation->name}] does not use key_record payload mode.");
        }

        $key = $this->buildKey($operation, $input);
        $record = $this->buildRecord(
            $channel,
            $input,
            skipPrimary: true,
            enforceRequired: false,
            excludeFields: array_keys($key),
        );

        if ($record === []) {
            throw new InvalidArgumentException("Update record is empty for operation [{$operation->name}].");
        }

        return [
            'key' => $key,
            'record' => $record,
        ];
    }

    private function resolveFilter(OperationContract $operation, array $input): ?string
    {
        if (array_key_exists('filter', $input)) {
            return $input['filter'] === null ? null : trim((string) $input['filter']);
        }

        $clauses = [];

        foreach ($operation->keyFields as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                continue;
            }

            $clauses[] = sprintf("%s='%s'", $field, $this->escapeFilterValue($input[$field]));
        }

        if ($clauses === []) {
            return null;
        }

        return implode(' and ', $clauses);
    }

    private function escapeFilterValue(mixed $value): string
    {
        return str_replace("'", "''", (string) $value);
    }

    private function buildKey(OperationContract $operation, array $input): array
    {
        $key = [];

        foreach ($operation->keyFields as $field) {
            if (! array_key_exists($field, $input) || $input[$field] === null || $input[$field] === '') {
                throw new InvalidArgumentException("Required key field [{$field}] is missing for operation [{$operation->name}].");
            }

            $key[$field] = $input[$field];
        }

        return $key;
    }

    private function buildRecord(
        ChannelContract $channel,
        array $input,
        bool $skipPrimary,
        bool $enforceRequired,
        array $excludeFields = [],
    ): array
    {
        $record = [];

        foreach ($channel->fields as $fieldPayload) {
            $field = FieldContract::fromArray($fieldPayload);

            if (($skipPrimary && $field->primary) || in_array($field->name, $excludeFields, true)) {
                continue;
            }

            if (! array_key_exists($field->name, $input) || $input[$field->name] === null || $input[$field->name] === '') {
                if ($enforceRequired && $field->required) {
                    throw new InvalidArgumentException("Required field [{$field->name}] is missing for channel [{$channel->key}].");
                }

                continue;
            }

            $record[$field->name] = $input[$field->name];
        }

        return $record;
    }
}
