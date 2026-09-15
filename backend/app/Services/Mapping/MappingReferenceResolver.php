<?php

namespace App\Services\Mapping;

use App\Models\ReferenceRecord;

class MappingReferenceResolver
{
    private array $snapshots = [];

    public const CODE_FIELDS = ['GetProdi' => 'kode_program_studi', 'GetListMataKuliah' => 'kode_mata_kuliah'];

    public function resolve(string $tenant, string $endpoint, string $input, array $rule): array
    {
        $key = $tenant.':'.$endpoint;
        if (! isset($this->snapshots[$key])) {
            $index = ['ids' => [], 'reference_label' => [], 'reference_code' => []];
            foreach (ReferenceRecord::where('tenant_id', $tenant)->where('endpoint', $endpoint)->cursor() as $record) {
                $id = (string) $record->value;
                $index['ids']['v:'.$id] = true;
                foreach (['reference_label' => $record->label, 'reference_code' => $record->raw_payload[self::CODE_FIELDS[$endpoint] ?? ''] ?? null] as $kind => $value) {
                    if ($value !== null) {
                        $index[$kind]['v:'.mb_strtolower(trim((string) $value))][$id] = $id;
                    }
                }
            }
            $this->snapshots[$key] = $index;
        }
        $index = $this->snapshots[$key];
        $override = collect($rule['overrides'] ?? [])->first(fn ($pair) => trim($pair['from']) === $input);
        if ($override) {
            $values = isset($index['ids']['v:'.$override['to']]) ? [$override['to']] : [];
        } else {
            $values = array_values($index[$rule['transform']]['v:'.mb_strtolower($input)] ?? []);
        }

        return count($values) === 1 ? ['value' => $values[0], 'error' => null] : [
            'value' => null,
            'error' => ['field' => $rule['target'], 'rule' => 'mapping_reference',
                'message' => count($values) > 1 ? 'Referensi ambigu. Tentukan padanan ID pada aturan mapping.' : 'Referensi tidak ditemukan. Periksa referensi atau padanan ID.'],
        ];
    }
}
