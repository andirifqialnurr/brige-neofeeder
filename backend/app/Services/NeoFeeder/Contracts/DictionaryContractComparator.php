<?php

namespace App\Services\NeoFeeder\Contracts;

final class DictionaryContractComparator
{
    private const ACTION_KEYS = ['action', 'act', 'nama_action', 'nama_fungsi', 'nama_operasi', 'nama_ws'];

    private const FIELD_KEYS = ['name', 'field', 'field_name', 'nama_field', 'column', 'kolom', 'parameter'];

    private const FIELD_LIST_KEYS = ['fields', 'field', 'columns', 'kolom', 'request', 'request_fields', 'input', 'params', 'parameter', 'record'];

    public function compare(ChannelContract $channel, array $dictionary): array
    {
        $expectedOperations = $this->expectedOperations($channel);
        $actualOperations = $this->normalizeActions($dictionary);

        $expectedFields = $this->expectedFields($channel);
        $actualFields = $this->normalizeFields($dictionary);

        return [
            'channel' => $channel->key,
            'missing_operations' => array_values(array_diff($expectedOperations, $actualOperations)),
            'extra_operations' => array_values(array_diff($actualOperations, $expectedOperations)),
            'missing_fields' => array_values(array_diff($expectedFields, $actualFields)),
            'extra_fields' => array_values(array_diff($actualFields, $expectedFields)),
            'expected_operations' => $expectedOperations,
            'actual_operations' => $actualOperations,
            'expected_fields' => $expectedFields,
            'actual_fields' => $actualFields,
        ];
    }

    /**
     * @return list<string>
     */
    private function expectedOperations(ChannelContract $channel): array
    {
        return $this->uniqueSorted(array_map(
            fn (array $payload): string => OperationContract::fromArray($payload)->action,
            $channel->operations,
        ));
    }

    /**
     * @return list<string>
     */
    private function expectedFields(ChannelContract $channel): array
    {
        return $this->uniqueSorted(array_map(
            fn (array $payload): string => FieldContract::fromArray($payload)->name,
            $channel->fields,
        ));
    }

    /**
     * @return list<string>
     */
    private function normalizeActions(array $payload): array
    {
        $actions = [];

        array_walk_recursive($payload, function (mixed $value, string|int $key) use (&$actions): void {
            if (in_array((string) $key, self::ACTION_KEYS, true) && is_scalar($value) && (string) $value !== '') {
                $actions[] = (string) $value;
            }
        });

        return $this->uniqueSorted($actions);
    }

    /**
     * @return list<string>
     */
    private function normalizeFields(array $payload): array
    {
        $fields = [];
        $this->collectFields($payload, $fields);

        return $this->uniqueSorted($fields);
    }

    private function collectFields(array $payload, array &$fields): void
    {
        foreach ($payload as $key => $value) {
            if (in_array((string) $key, self::FIELD_KEYS, true) && is_scalar($value) && (string) $value !== '') {
                $fields[] = (string) $value;
                continue;
            }

            if (is_array($value)) {
                $isFieldList = in_array((string) $key, self::FIELD_LIST_KEYS, true);

                foreach ($value as $item) {
                    if ($isFieldList && is_string($item) && $item !== '') {
                        $fields[] = $item;
                        continue;
                    }

                    if (is_array($item)) {
                        $this->collectFields($item, $fields);
                    }
                }
            }
        }
    }

    /**
     * @param  array<int, string>  $items
     * @return list<string>
     */
    private function uniqueSorted(array $items): array
    {
        $items = array_values(array_unique(array_filter($items, fn (string $item): bool => $item !== '')));
        sort($items);

        return $items;
    }
}
