<?php

namespace App\Services\NeoFeeder\Payloads;

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
}
