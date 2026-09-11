<?php

namespace App\Services\Imports;

use App\Models\ImportBatch;
use App\Models\StagingRecord;
use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\FieldContract;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\Validation\ImportBatchValidationService;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class ImportWorkbookParser
{
    public const ROW_STATUSES = ['pending', 'valid', 'invalid', 'ready', 'syncing', 'success', 'failed', 'skipped'];

    public function __construct(
        private readonly NeoFeederContractRegistry $registry,
        private readonly ImportBatchValidationService $validationService,
    ) {
    }

    public function parse(ImportBatch $batch): void
    {
        $batch->forceFill(['status' => 'parsing'])->save();

        $path = Storage::disk('uploads')->path((string) $batch->file_path);
        $workbook = IOFactory::load($path);
        $channels = $this->requiredChannels();
        $missingSheets = [];
        $totalRows = 0;

        $batch->stagingRecords()->delete();

        foreach ($channels as $channel) {
            $sheet = $workbook->getSheetByName($channel->sheetName);

            if (! $sheet instanceof Worksheet) {
                $missingSheets[] = $channel->sheetName;
                continue;
            }

            $totalRows += $this->parseSheet($batch, $channel, $sheet);
        }

        $status = $missingSheets === [] ? 'ready' : 'invalid';

        $batch->forceFill([
            'status' => $status,
            'summary' => [
                ...($batch->summary ?? []),
                'total_rows' => $totalRows,
                'missing_sheets' => $missingSheets,
                'available_row_statuses' => self::ROW_STATUSES,
            ],
        ])->save();

        if ($missingSheets === []) {
            $this->validationService->validate($batch->refresh());
        }
    }

    /**
     * @return list<ChannelContract>
     */
    private function requiredChannels(): array
    {
        $channels = [];

        foreach ($this->registry->dependencyOrder() as $channelKey) {
            if ($channelKey === 'references') {
                continue;
            }

            $channel = $this->registry->channel($channelKey);

            if ($channel instanceof ChannelContract) {
                $channels[] = $channel;
            }
        }

        return $channels;
    }

    private function parseSheet(ImportBatch $batch, ChannelContract $channel, Worksheet $sheet): int
    {
        $headers = $this->headers($sheet);
        $fields = array_map(fn (array $payload): FieldContract => FieldContract::fromArray($payload), $channel->fields);
        $count = 0;

        for ($rowNumber = 2; $rowNumber <= $sheet->getHighestDataRow(); $rowNumber++) {
            $rawRow = $this->row($sheet, $headers, $rowNumber);

            if ($this->isEmptyRow($rawRow)) {
                continue;
            }

            $normalizedRow = $this->normalizeRow($rawRow, $fields);

            StagingRecord::query()->create([
                'tenant_id' => $batch->tenant_id,
                'import_batch_id' => $batch->id,
                'channel' => $channel->key,
                'sheet_name' => $channel->sheetName,
                'row_number' => $rowNumber,
                'operation' => 'insert',
                'natural_key' => $this->naturalKey($channel, $normalizedRow),
                'raw_row' => $rawRow,
                'normalized_row' => $normalizedRow,
                'validation_result' => ['errors' => [], 'warnings' => [], 'info' => []],
                'status' => 'ready',
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * @return list<string>
     */
    private function headers(Worksheet $sheet): array
    {
        $headers = [];

        $highestColumn = Coordinate::columnIndexFromString($sheet->getHighestDataColumn(1));

        for ($column = 1; $column <= $highestColumn; $column++) {
            $value = trim((string) $sheet->getCell([$column, 1])->getValue());

            if ($value !== '') {
                $headers[$column] = $value;
            }
        }

        return $headers;
    }

    /**
     * @param  array<int, string>  $headers
     * @return array<string, mixed>
     */
    private function row(Worksheet $sheet, array $headers, int $rowNumber): array
    {
        $row = [];

        foreach ($headers as $column => $header) {
            $row[$header] = $sheet->getCell([$column, $rowNumber])->getValue();
        }

        return $row;
    }

    /**
     * @param  list<FieldContract>  $fields
     * @return array<string, mixed>
     */
    private function normalizeRow(array $rawRow, array $fields): array
    {
        $normalized = [];

        foreach ($fields as $field) {
            $value = $rawRow[$field->name] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value !== null && $value !== '' && in_array($field->type, ['string', 'char', 'uuid', 'date', 'boolean01'], true)) {
                $value = (string) $value;
            }

            $normalized[$field->name] = $value === '' ? null : $value;
        }

        return $normalized;
    }

    private function naturalKey(ChannelContract $channel, array $normalizedRow): ?string
    {
        if ($channel->naturalKey === []) {
            return null;
        }

        $parts = [];

        foreach ($channel->naturalKey as $fieldName) {
            $parts[] = $fieldName.'='.($normalizedRow[$fieldName] ?? '');
        }

        return implode('|', $parts);
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if ($value !== null && trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
