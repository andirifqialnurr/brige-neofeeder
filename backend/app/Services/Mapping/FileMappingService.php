<?php

namespace App\Services\Mapping;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\MappingProfile;
use App\Models\SourceConnection;
use App\Models\StagingRecord;
use App\Models\User;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\Operations\SensitiveData;
use App\Services\Templates\NeoFeederTemplateWorkbookService;
use App\Services\Validation\ImportBatchValidationService;
use App\Services\Validation\StagingRecordValidator;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class FileMappingService
{
    public const CHANNELS = ['mahasiswa_biodata', 'mahasiswa_riwayat_pendidikan', 'mata_kuliah', 'kelas_kuliah', 'peserta_kelas', 'nilai_perkuliahan'];

    public function __construct(private NeoFeederContractRegistry $registry, private StagingRecordValidator $validator) {}

    public function preview(
        MappingProfile $profile,
        SourceConnection $source,
        int $version,
        int $page = 1,
        int $perPage = 25,
        ?string $status = null,
        ?string $search = null,
    ): array {
        $records = $this->records($profile, $source, $version);
        $valid = collect($records)->where('status', 'valid')->count();
        $filtered = array_values(array_filter($records, function (array $record) use ($status, $search): bool {
            if ($status !== null && $record['status'] !== $status) {
                return false;
            }
            if ($search === null || trim($search) === '') {
                return true;
            }

            $haystack = implode(' ', [
                $record['row_number'],
                $record['status'],
                json_encode($record['normalized_row'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                json_encode($record['validation_result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);

            return str_contains(mb_strtolower($haystack), mb_strtolower(trim($search)));
        }));
        $total = count($filtered);
        $page = max(1, $page);
        $perPage = max(1, $perPage);
        $rows = array_slice($filtered, ($page - 1) * $perPage, $perPage);

        return ['preview_hash' => $this->hash($profile, $source, $records), 'version' => $profile->version,
            'summary' => ['total_rows' => count($records), 'valid_rows' => $valid, 'invalid_rows' => count($records) - $valid],
            'rows' => app(SensitiveData::class)->present(array_map(fn ($record) => ['row_number' => $record['row_number'],
                'normalized_row' => $record['normalized_row'], 'status' => $record['status'], 'validation_result' => $record['validation_result']], $rows)),
            'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($total / $perPage)), 'total' => $total, 'per_page' => $perPage],
            'filters' => ['status' => $status, 'search' => $search]];
    }

    public function inspectStructure(SourceConnection $source, MappingProfile $profile, int $version): array
    {
        abort_unless($source->tenant_id === $profile->tenant_id, 403);
        abort_unless($profile->version === $version, 409, 'Versi mapping berubah. Muat ulang profil.');
        $rules = $profile->versions()->where('version', $version)->firstOrFail()->rules;
        $ruleByTarget = collect($rules)->keyBy('target');
        $headers = array_values($source->headers);
        $usedColumns = [];
        foreach ($rules as $rule) {
            if (($rule['kind'] ?? null) === 'source' && isset($rule['source'])) {
                $usedColumns[] = $rule['source'];
            }
            foreach ($rule['transform'] === 'concat' ? ($rule['append_sources'] ?? []) : [] as $column) {
                $usedColumns[] = $column;
            }
        }
        $missingSourceColumns = array_values(array_diff(array_unique($usedColumns), $headers));
        $requiredFields = [];
        foreach ($this->registry->channel($profile->channel)->fields as $field) {
            if (! ($field['required'] ?? false)) {
                continue;
            }
            $rule = $ruleByTarget->get($field['name']);
            $sourceColumn = ($rule['kind'] ?? null) === 'source' ? ($rule['source'] ?? null) : null;
            $sourceMissing = $sourceColumn !== null && in_array($sourceColumn, $missingSourceColumns, true);
            $emptyRows = 0;
            if (! $rule || $sourceMissing) {
                $emptyRows = count($source->snapshot);
            } else {
                foreach ($source->snapshot as $row) {
                    $value = ($rule['kind'] ?? null) === 'constant' ? ($rule['constant'] ?? null) : ($row['values'][$sourceColumn] ?? null);
                    if ($value === null || trim((string) $value) === '') {
                        $emptyRows++;
                    }
                }
            }
            $requiredFields[] = [
                'target' => $field['name'],
                'label' => $field['label'],
                'mapped' => (bool) $rule && ! $sourceMissing,
                'source' => $sourceColumn,
                'empty_rows' => $emptyRows,
            ];
        }

        $duplicateGroups = [];
        $naturalKeyFields = $this->registry->channel($profile->channel)->naturalKey;
        foreach ($source->snapshot as $row) {
            $parts = [];
            $complete = true;
            foreach ($naturalKeyFields as $field) {
                $rule = $ruleByTarget->get($field);
                if (! $rule) {
                    $complete = false;
                    break;
                }
                $value = ($rule['kind'] ?? null) === 'constant' ? ($rule['constant'] ?? null) : ($row['values'][$rule['source'] ?? ''] ?? null);
                if ($value === null || trim((string) $value) === '') {
                    $complete = false;
                    break;
                }
                $parts[] = mb_strtolower(trim((string) $value));
            }
            if ($complete) {
                $duplicateGroups[implode('|', $parts)][] = $row['row_number'];
            }
        }
        $duplicates = [];
        foreach ($duplicateGroups as $key => $rowNumbers) {
            if (count($rowNumbers) > 1) {
                $duplicates[] = ['natural_key' => $key, 'rows' => $rowNumbers, 'count' => count($rowNumbers)];
            }
            if (count($duplicates) >= 100) {
                break;
            }
        }

        return [
            'source' => $source->only(['id', 'name', 'headers', 'sheet_name', 'row_count', 'sha256']),
            'profile' => ['id' => $profile->id, 'name' => $profile->name, 'channel' => $profile->channel, 'version' => $version],
            'required_fields' => $requiredFields,
            'missing_source_columns' => $missingSourceColumns,
            'unmapped_source_columns' => array_values(array_diff($headers, array_unique($usedColumns))),
            'natural_key_fields' => $naturalKeyFields,
            'duplicate_candidates' => $duplicates,
            'summary' => [
                'source_rows' => count($source->snapshot),
                'required_fields' => count($requiredFields),
                'missing_mappings' => count(array_filter($requiredFields, fn ($field) => ! $field['mapped'])),
                'missing_source_columns' => count($missingSourceColumns),
                'empty_required_cells' => array_sum(array_column($requiredFields, 'empty_rows')),
                'duplicate_groups' => count($duplicates),
            ],
        ];
    }

    public function stage(MappingProfile $profile, SourceConnection $source, int $version, string $hash, User $actor): ImportBatch
    {
        return DB::transaction(function () use ($profile, $source, $version, $hash, $actor) {
            $profile = MappingProfile::query()->lockForUpdate()->findOrFail($profile->id);
            $source = SourceConnection::query()->lockForUpdate()->findOrFail($source->id);
            $records = $this->records($profile, $source, $version);
            abort_unless(hash_equals($this->hash($profile, $source, $records), $hash), 409, 'Mapping, referensi, atau contract berubah. Tinjau preview ulang.');
            $mappingVersion = $profile->versions()->where('version', $version)->firstOrFail();
            $snapshotVersion = $source->snapshot_version ?? 1;
            $existing = DB::table('mapping_runs')->where('source_connection_id', $source->id)
                ->where('mapping_profile_version_id', $mappingVersion->id)
                ->where('source_snapshot_version', $snapshotVersion)->first();
            if ($existing) {
                abort_unless($existing->preview_hash === $hash, 409, 'Sumber/versi ini sudah diproses dengan contract lain. Simpan versi mapping baru.');

                return ImportBatch::findOrFail($existing->import_batch_id);
            }
            $batch = ImportBatch::create(['tenant_id' => $source->tenant_id, 'source_type' => 'mapping', 'status' => 'ready',
                'template_version' => NeoFeederTemplateWorkbookService::TEMPLATE_VERSION,
                'summary' => ['original_name' => $source->name, 'source_id' => $source->id, 'mapping_profile' => $profile->name,
                    'mapping_version' => $version, 'source_sha256' => $source->sha256, 'missing_sheets' => []]]);
            foreach ($records as $record) {
                $row = new StagingRecord;
                $row->forceFill([...$record, 'tenant_id' => $source->tenant_id, 'import_batch_id' => $batch->id,
                    'source_lineage' => [...($record['source_lineage'] ?? []), 'source_id' => $source->id, 'source_name' => $source->name, 'source_sheet' => $source->sheet_name,
                        'source_row' => $record['row_number'], 'mapping_profile_id' => $profile->id, 'mapping_version' => $version]])->save();
            }
            app(ImportBatchValidationService::class)->validate($batch);
            DB::table('mapping_runs')->insert(['id' => (string) Str::uuid(), 'source_connection_id' => $source->id,
                'mapping_profile_version_id' => $mappingVersion->id, 'source_snapshot_version' => $snapshotVersion,
                'import_batch_id' => $batch->id, 'preview_hash' => $hash, 'created_at' => now()]);
            AuditLog::create(['tenant_id' => $source->tenant_id, 'actor_id' => $actor->id, 'event' => 'mapping.staged',
                'subject_type' => ImportBatch::class, 'subject_id' => $batch->id, 'metadata' => ['records' => count($records)]]);

            return $batch->refresh();
        });
    }

    private function records(MappingProfile $profile, SourceConnection $source, int $version): array
    {
        abort_unless($profile->tenant_id === $source->tenant_id, 403);
        abort_unless($profile->version === $version, 409, 'Versi mapping berubah. Muat ulang profil.');
        $rules = $profile->versions()->where('version', $version)->firstOrFail()->rules;
        foreach ($rules as $rule) {
            if ($rule['kind'] === 'source' && ! in_array($rule['source'], $source->headers, true)) {
                throw ValidationException::withMessages(['source_id' => 'Kolom sumber tidak tersedia: '.$rule['source']]);
            }
        }
        $channel = $this->registry->channel($profile->channel);
        foreach ($rules as $rule) {
            foreach ($rule['transform'] === 'concat' ? $rule['append_sources'] : [] as $header) {
                if (! in_array($header, $source->headers, true)) {
                    throw ValidationException::withMessages(['source_id' => 'Kolom gabungan tidak tersedia: '.$header]);
                }
            }
        }
        $resolver = new MappingReferenceResolver;
        $records = [];
        foreach ($source->snapshot as $sourceRow) {
            $normalized = array_fill_keys(array_column($channel->fields, 'name'), null);
            $mappingErrors = [];
            foreach ($rules as $rule) {
                $value = $rule['kind'] === 'constant' ? ($rule['constant'] ?? null) : ($sourceRow['values'][$rule['source']] ?? null);
                $normalized[$rule['target']] = $this->transform($value, $rule['transform']);
                $transformed = app(MappingValueTransformer::class)->apply($normalized[$rule['target']], $sourceRow['values'], $rule);
                $normalized[$rule['target']] = $transformed['value'];
                if ($transformed['error']) {
                    $mappingErrors[] = $transformed['error'];
                }
                if (str_starts_with($rule['transform'], 'reference_') && $normalized[$rule['target']] !== null) {
                    $field = collect($channel->fields)->firstWhere('name', $rule['target']);
                    $resolved = $resolver->resolve($source->tenant_id, $field['reference'], $normalized[$rule['target']], $rule);
                    $normalized[$rule['target']] = $resolved['value'];
                    if ($resolved['error']) {
                        $mappingErrors[] = $resolved['error'];
                    }
                }
            }
            $key = implode('|', array_map(fn ($field) => $field.'='.($normalized[$field] ?? ''), $channel->naturalKey));
            $record = ['channel' => $profile->channel, 'sheet_name' => $channel->sheetName, 'row_number' => $sourceRow['row_number'],
                'operation' => 'insert', 'natural_key' => $key ?: null, 'raw_row' => $sourceRow['values'], 'normalized_row' => $normalized,
                'source_lineage' => ['mapping_errors' => $mappingErrors]];
            $result = $this->validator->validate(new StagingRecord([...$record, 'tenant_id' => $source->tenant_id]));
            $records[] = [...$record, 'validation_result' => $result, 'status' => $result['errors'] === [] ? 'valid' : 'invalid'];
        }
        $counts = array_count_values(array_filter(array_column($records, 'natural_key')));
        foreach ($records as &$record) {
            if ($record['natural_key'] && ($counts[$record['natural_key']] ?? 0) > 1) {
                $record['validation_result']['errors'][] = ['field' => null, 'rule' => 'duplicate_row', 'message' => 'Natural key duplikat dalam batch.'];
                $record['status'] = 'invalid';
            }
        }

        return $records;
    }

    private function hash(MappingProfile $profile, SourceConnection $source, array $records): string
    {
        return hash('sha256', json_encode([$profile->id, $profile->version, $source->id, $source->sha256,
            $profile->versions()->where('version', $profile->version)->value('rules'), config('neofeeder-contracts'), $records], JSON_THROW_ON_ERROR));
    }

    private function transform(mixed $value, string $transform): mixed
    {
        if ($value === null || trim((string) $value) === '') {
            return null;
        }
        $text = trim((string) $value);
        if ($transform === 'gender') {
            return match (mb_strtolower($text)) {
                'l', 'laki-laki', 'laki laki', 'pria', 'male' => 'L',
                'p', 'perempuan', 'wanita', 'female' => 'P',
                default => $text,
            };
        }
        if ($transform === 'date_dmy') {
            $date = DateTimeImmutable::createFromFormat('!d/m/Y', $text);

            return $date && $date->format('d/m/Y') === $text ? $date->format('Y-m-d') : $text;
        }
        if ($transform === 'excel_date' && is_numeric($value) && (float) $value >= 1 && (float) $value < 2958466) {
            return Date::excelToDateTimeObject((float) $value)->format('Y-m-d');
        }

        return $text;
    }
}
