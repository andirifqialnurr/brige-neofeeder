<?php

namespace App\Services\Validation;

use App\Models\ImportBatch;
use App\Models\StagingRecord;

final class ImportBatchValidationService
{
    public function __construct(
        private readonly StagingRecordValidator $validator,
    ) {
    }

    public function validate(ImportBatch $batch): array
    {
        $records = $batch->stagingRecords()->orderBy('channel')->orderBy('row_number')->get();
        $duplicates = $this->duplicateKeys($records);
        $summary = [
            'total_rows' => $records->count(),
            'valid_rows' => 0,
            'invalid_rows' => 0,
            'warning_rows' => 0,
        ];

        foreach ($records as $record) {
            $result = $this->validator->validate($record);

            if ($record->natural_key !== null && isset($duplicates[$record->channel][$record->natural_key])) {
                $result['errors'][] = [
                    'field' => null,
                    'rule' => 'duplicate_row',
                    'message' => 'Natural key duplikat dalam batch.',
                ];
            }

            $this->validateDependencies($record, $records->all(), $result);

            $status = $result['errors'] === [] ? 'valid' : 'invalid';

            if ($result['warnings'] !== []) {
                $summary['warning_rows']++;
            }

            $summary[$status.'_rows']++;

            $record->forceFill([
                'normalized_row' => $this->validator->normalizeEmptyValues($record->normalized_row ?? []),
                'validation_result' => $result,
                'status' => $status,
            ])->save();
        }

        $batch->forceFill([
            'status' => $summary['invalid_rows'] > 0 ? 'invalid' : 'validated',
            'summary' => [
                ...($batch->summary ?? []),
                ...$summary,
            ],
        ])->save();

        return $summary;
    }

    /**
     * @param  iterable<StagingRecord>  $records
     * @return array<string, array<string, true>>
     */
    private function duplicateKeys(iterable $records): array
    {
        $seen = [];
        $duplicates = [];

        foreach ($records as $record) {
            if ($record->natural_key === null) {
                continue;
            }

            if (isset($seen[$record->channel][$record->natural_key])) {
                $duplicates[$record->channel][$record->natural_key] = true;
            }

            $seen[$record->channel][$record->natural_key] = true;
        }

        return $duplicates;
    }

    /**
     * @param  list<StagingRecord>  $records
     */
    private function validateDependencies(StagingRecord $record, array $records, array &$result): void
    {
        foreach (($record->normalized_row ?? []) as $field => $value) {
            if ($value === null || ! str_starts_with($field, 'id_')) {
                continue;
            }

            $hasUpstreamValue = collect($records)
                ->contains(fn (StagingRecord $candidate): bool => $candidate->id !== $record->id
                    && ($candidate->normalized_row[$field] ?? null) === $value);

            if (! $hasUpstreamValue) {
                $result['info'][] = [
                    'field' => $field,
                    'rule' => 'dependency_check',
                    'message' => 'Dependency akan dicek ulang saat dry-run/sync.',
                ];
            }
        }
    }
}
