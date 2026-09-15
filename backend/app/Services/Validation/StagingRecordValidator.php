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
    ) {}

    public function validate(StagingRecord $record): array
    {
        $channel = $this->registry->channel($record->channel);
        $row = $this->normalizeEmptyValues($record->normalized_row ?? []);
        $result = ['errors' => $record->source_lineage['mapping_errors'] ?? [], 'warnings' => [], 'info' => []];

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

        $this->validateAcademicMapping($record, $row, $result);

        return $result;
    }

    private function validateAcademicMapping(StagingRecord $record, array $row, array &$result): void
    {
        if ($record->source_lineage === null || ! in_array($record->channel, ['mata_kuliah', 'kelas_kuliah'], true)) {
            if ($record->source_lineage === null || $record->channel !== 'nilai_perkuliahan') {
                return;
            }
        }
        if ($record->channel === 'nilai_perkuliahan') {
            $hasGrade = collect(['nilai_angka', 'nilai_indeks', 'nilai_huruf'])->contains(fn (string $field): bool => filled($row[$field] ?? null));
            if (! $hasGrade) {
                $result['errors'][] = $this->issue(null, 'grade_value', 'Isi setidaknya satu nilai angka, indeks, atau huruf.');
            }
            if (filled($row['nilai_angka'] ?? null) && (! is_numeric($row['nilai_angka']) || (float) $row['nilai_angka'] < 0 || (float) $row['nilai_angka'] > 100 || ! preg_match('/^\d{1,3}(\.\d)?$/', (string) $row['nilai_angka']))) {
                $result['errors'][] = $this->issue('nilai_angka', 'grade_range', 'Nilai angka harus 0 sampai 100 dengan maksimal satu desimal.');
            }
            if (filled($row['nilai_indeks'] ?? null) && (! is_numeric($row['nilai_indeks']) || (float) $row['nilai_indeks'] < 0 || (float) $row['nilai_indeks'] > 4 || ! preg_match('/^\d(\.\d{1,2})?$/', (string) $row['nilai_indeks']))) {
                $result['errors'][] = $this->issue('nilai_indeks', 'grade_range', 'Nilai indeks harus 0 sampai 4 dengan maksimal dua desimal.');
            }

            return;
        }
        if ($record->channel === 'mata_kuliah' && isset($row['sks_mata_kuliah'])) {
            $sks = (string) $row['sks_mata_kuliah'];
            if (! preg_match('/^\d{1,3}(\.\d{1,2})?$/', $sks) || (float) $sks < 1) {
                $result['errors'][] = $this->issue('sks_mata_kuliah', 'academic_sks', 'SKS minimal 1, maksimal 999.99 dengan dua angka desimal.');
            }
        }
        if ($record->channel !== 'kelas_kuliah') {
            return;
        }
        if (isset($row['kapasitas']) && ! preg_match('/^\d{1,5}$/', (string) $row['kapasitas'])) {
            $result['errors'][] = $this->issue('kapasitas', 'academic_capacity', 'Kapasitas harus bilangan bulat 0 sampai 99999.');
        }
        $course = ReferenceRecord::where('tenant_id', $record->tenant_id)->where('endpoint', 'GetListMataKuliah')->where('value', $row['id_matkul'] ?? null)->first();
        if (! $course) {
            return;
        } // Normal reference validation supplies the missing-ID error.
        $program = $course->raw_payload['id_prodi'] ?? null;
        if ($program !== null && (string) $program !== (string) ($row['id_prodi'] ?? '')) {
            $result['errors'][] = $this->issue('id_matkul', 'course_program', 'Mata kuliah berada pada prodi lain. Periksa pilihan mata kuliah dan prodi.');
        } elseif ($program === null) {
            $result['warnings'][] = $this->issue('id_matkul', 'course_program_unverified', 'Referensi mata kuliah belum menyertakan prodi. Verifikasi hubungan prodi sebelum pengiriman.');
        }
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
        } elseif (! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            $result['errors'][] = $this->issue($field->name, 'date_format', 'Tanggal tidak valid.');
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
            $severity = $record->source_lineage !== null ? 'errors' : 'warnings';
            $result[$severity][] = $this->issue($field->name, 'ambiguous_reference', 'Label referensi ambigu, gunakan value/id referensi.');

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
