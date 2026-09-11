<?php

namespace App\Services\Validation;

use App\Models\ReferenceRecord;
use App\Models\StagingRecord;
use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\FieldContract;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;

final class StagingRecordValidator
{
    public function __construct(
        private readonly NeoFeederContractRegistry $registry,
    ) {
    }

    public function validate(StagingRecord $record): array
    {
        $channel = $this->registry->channel($record->channel);
        $row = $this->normalizeEmptyValues($record->normalized_row ?? []);
        $result = ['errors' => [], 'warnings' => [], 'info' => []];

        if (! $channel instanceof ChannelContract) {
            $result['errors'][] = $this->issue(null, 'unknown_channel', "Channel [{$record->channel}] tidak dikenal.");

            return $result;
        }

        foreach ($channel->fields as $fieldPayload) {
            $field = FieldContract::fromArray($fieldPayload);
            $value = $row[$field->name] ?? null;

            $this->validateRequired($field, $value, $result);
            $this->validateDate($field, $value, $result);
            $this->validateNumeric($field, $value, $result);
            $this->validateLength($field, $value, $result);
            $this->validateEnum($field, $value, $result);
            $this->validateReference($record, $field, $value, $result);
        }

        return $result;
    }

    public function normalizeEmptyValues(array $row): array
    {
        foreach ($row as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            $row[$key] = $value === '' ? null : $value;
        }

        return $row;
    }

    private function validateRequired(FieldContract $field, mixed $value, array &$result): void
    {
        if ($field->required && ($value === null || $value === '')) {
            $result['errors'][] = $this->issue($field->name, 'required', 'Field wajib diisi.');
        }
    }

    private function validateDate(FieldContract $field, mixed $value, array &$result): void
    {
        if ($value === null || $value === '' || $field->type !== 'date') {
            return;
        }

        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            $result['errors'][] = $this->issue($field->name, 'date_format', 'Format tanggal harus yyyy-mm-dd.');
        }
    }

    private function validateNumeric(FieldContract $field, mixed $value, array &$result): void
    {
        if ($value === null || $value === '' || ! in_array($field->type, ['numeric', 'integer', 'double'], true)) {
            return;
        }

        if (! is_numeric($value)) {
            $result['errors'][] = $this->issue($field->name, 'numeric', 'Field harus berisi angka.');
        }
    }

    private function validateLength(FieldContract $field, mixed $value, array &$result): void
    {
        if ($value === null || $value === '' || $field->maxLength === null) {
            return;
        }

        if (strlen((string) $value) > $field->maxLength) {
            $result['errors'][] = $this->issue($field->name, 'max_length', "Maksimal {$field->maxLength} karakter.");
        }
    }

    private function validateEnum(FieldContract $field, mixed $value, array &$result): void
    {
        if ($value === null || $value === '') {
            return;
        }

        foreach ($field->rules as $rule) {
            if (! is_string($rule) || ! str_starts_with($rule, 'enum:')) {
                continue;
            }

            $allowed = explode(',', substr($rule, 5));

            if (! in_array((string) $value, $allowed, true)) {
                $result['errors'][] = $this->issue($field->name, 'enum', 'Nilai tidak ada dalam daftar pilihan.');
            }
        }
    }

    private function validateReference(StagingRecord $record, FieldContract $field, mixed $value, array &$result): void
    {
        if ($value === null || $value === '' || $field->reference === null || ! $this->isReferenceEndpoint($field->reference)) {
            return;
        }

        $exists = ReferenceRecord::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('endpoint', $field->reference)
            ->where('value', (string) $value)
            ->exists();

        if ($exists) {
            return;
        }

        $labelMatches = ReferenceRecord::query()
            ->where('tenant_id', $record->tenant_id)
            ->where('endpoint', $field->reference)
            ->where('label', (string) $value)
            ->count();

        if ($labelMatches > 1) {
            $result['warnings'][] = $this->issue($field->name, 'ambiguous_reference', 'Label referensi ambigu, gunakan value/id referensi.');

            return;
        }

        $result['errors'][] = $this->issue($field->name, 'reference_exists', 'Value referensi tidak ditemukan.');
    }

    private function isReferenceEndpoint(string $endpoint): bool
    {
        foreach (config('neofeeder-contracts.channels.references.operations', []) as $operation) {
            if (($operation['action'] ?? null) === $endpoint) {
                return true;
            }
        }

        return false;
    }

    private function issue(?string $field, string $rule, string $message): array
    {
        return [
            'field' => $field,
            'rule' => $rule,
            'message' => $message,
        ];
    }
}
