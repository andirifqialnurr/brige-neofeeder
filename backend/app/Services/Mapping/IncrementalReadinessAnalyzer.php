<?php

namespace App\Services\Mapping;

use App\Models\SourceSchemaColumn;
use App\Models\SourceSchemaTable;

final class IncrementalReadinessAnalyzer
{
    /** @var list<string> */
    private const HIGH_CONFIDENCE_NAMES = [
        'updated_at',
        'last_updated_at',
        'modified_at',
        'last_modified_at',
        'changed_at',
    ];

    /**
     * @return array{
     *     status:'ready'|'needs_review'|'not_ready',
     *     key_columns:list<string>,
     *     timestamp_columns:list<array{name:string,data_type:string,confidence:'high'|'medium'}>,
     *     reasons:list<string>,
     *     activation_allowed:false,
     *     blocking_reasons:list<string>
     * }
     */
    public function analyze(SourceSchemaTable $table): array
    {
        $keyColumns = array_values(array_unique(array_filter([
            ...($table->primary_key_columns ?? []),
            ...($table->candidate_key_columns ?? []),
        ], fn (mixed $value): bool => is_string($value) && $value !== '')));
        $timestampColumns = [];

        foreach ($table->columns as $column) {
            $confidence = $this->timestampConfidence($column);
            if ($confidence === null) {
                continue;
            }
            $timestampColumns[] = [
                'name' => $column->name,
                'data_type' => $column->data_type,
                'confidence' => $confidence,
            ];
        }

        $reasons = [];
        if ($keyColumns === []) {
            $reasons[] = 'Tidak ada primary key atau unique key non-null.';
        }
        if ($timestampColumns === []) {
            $reasons[] = 'Tidak ditemukan kolom datetime/timestamp untuk watermark.';
        } elseif (count($timestampColumns) > 1) {
            $reasons[] = 'Ditemukan beberapa kandidat timestamp; pilih satu saat review pilot.';
        } else {
            $reasons[] = 'Ditemukan satu kandidat timestamp untuk watermark.';
        }

        $status = 'not_ready';
        if ($keyColumns !== [] && $timestampColumns !== []) {
            $status = count($timestampColumns) > 1 ? 'needs_review' : 'ready';
        }

        return [
            'status' => $status,
            'key_columns' => $keyColumns,
            'timestamp_columns' => $timestampColumns,
            'reasons' => $reasons,
            'activation_allowed' => false,
            'blocking_reasons' => [
                'Aturan insert/update/delete belum dikonfirmasi pada SIAKAD pilot.',
                'Watermark checkpoint belum disimpan dan diuji.',
            ],
        ];
    }

    private function timestampConfidence(SourceSchemaColumn $column): ?string
    {
        if (! in_array(strtolower($column->data_type), ['datetime', 'timestamp'], true)) {
            return null;
        }

        $name = strtolower((string) preg_replace('/[^a-z0-9]+/i', '_', $column->name));
        $name = trim($name, '_');
        if (in_array($name, self::HIGH_CONFIDENCE_NAMES, true)) {
            return 'high';
        }
        if (preg_match('/(^|_)(update|updated|modified|changed|sync|synced|last_seen|ubah|perubahan)(_|$)/', $name)) {
            return 'medium';
        }

        return null;
    }
}
