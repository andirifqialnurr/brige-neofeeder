<?php

namespace App\Services\NeoFeeder\References;

use App\Models\ReferenceRecord;
use App\Services\NeoFeeder\Contracts\OperationContract;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

final class NeoFeederReferenceSyncService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function normalize(string $endpoint, array $payload): array
    {
        $operation = $this->operation($endpoint);
        $valueKey = $operation->responseFields[0] ?? null;
        $labelKey = $operation->responseFields[2] ?? $operation->responseFields[1] ?? null;

        if (! is_string($valueKey) || $valueKey === '') {
            throw new InvalidArgumentException("Reference endpoint [{$endpoint}] does not define a value response field.");
        }

        return array_values(array_filter(array_map(
            fn (array $record): ?array => $this->normalizeRecord($endpoint, $valueKey, $labelKey, $record),
            $this->extractRecords($payload),
        )));
    }

    public function sync(string $tenantId, string $endpoint, array $payload): int
    {
        $rows = $this->normalize($endpoint, $payload);
        $syncedAt = Carbon::now();

        foreach ($rows as $row) {
            ReferenceRecord::query()->updateOrCreate(
                [
                    'tenant_id' => $tenantId,
                    'endpoint' => $endpoint,
                    'value' => $row['value'],
                ],
                [
                    'value_key' => $row['value_key'],
                    'label' => $row['label'],
                    'raw_payload' => $row['raw_payload'],
                    'synced_at' => $syncedAt,
                ],
            );
        }

        return count($rows);
    }

    private function operation(string $endpoint): OperationContract
    {
        $operations = config('neofeeder-contracts.channels.references.operations', []);

        foreach ($operations as $operation) {
            if (($operation['action'] ?? null) === $endpoint) {
                return OperationContract::fromArray($operation);
            }
        }

        throw new InvalidArgumentException("Unknown reference endpoint [{$endpoint}].");
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractRecords(array $payload): array
    {
        $data = $payload['data'] ?? $payload;

        if ($data === []) {
            return [];
        }

        if (array_is_list($data)) {
            return array_values(array_filter($data, fn (mixed $record): bool => is_array($record)));
        }

        return [$data];
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, mixed>|null
     */
    private function normalizeRecord(string $endpoint, string $valueKey, ?string $labelKey, array $record): ?array
    {
        if (! array_key_exists($valueKey, $record) || $record[$valueKey] === null || $record[$valueKey] === '') {
            return null;
        }

        return [
            'endpoint' => $endpoint,
            'value_key' => $valueKey,
            'value' => (string) $record[$valueKey],
            'label' => $labelKey !== null && array_key_exists($labelKey, $record) ? (string) $record[$labelKey] : null,
            'raw_payload' => $record,
        ];
    }
}
