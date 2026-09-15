<?php

namespace App\Services\Mapping;

class MappingValueTransformer
{
    public function apply(mixed $value, array $row, array $rule): array
    {
        if ($rule['transform'] === 'concat') {
            $parts = [$value];
            foreach ($rule['append_sources'] as $source) {
                $parts[] = trim((string) ($row[$source] ?? ''));
            }

            return ['value' => implode($rule['separator'], array_filter($parts, fn ($part) => $part !== null && $part !== '')), 'error' => null];
        }
        if ($value === null) {
            return ['value' => null, 'error' => null];
        }
        if ($rule['transform'] === 'lookup') {
            $pair = collect($rule['pairs'])->first(fn ($pair) => trim($pair['from']) === $value);
            if ($pair) {
                return ['value' => $pair['to'], 'error' => null];
            }
            $message = 'Nilai tidak mempunyai padanan. Tambahkan pada tabel padanan mapping.';
        } elseif ($rule['transform'] === 'split') {
            $parts = explode($rule['separator'], $value);
            if (array_key_exists($rule['part'] - 1, $parts) && trim($parts[$rule['part'] - 1]) !== '') {
                return ['value' => trim($parts[$rule['part'] - 1]), 'error' => null];
            }
            $message = 'Bagian yang dipilih tidak tersedia pada nilai sumber.';
        } else {
            return ['value' => $value, 'error' => null];
        }

        return ['value' => null, 'error' => ['field' => $rule['target'], 'rule' => 'mapping_transform', 'message' => $message]];
    }
}
