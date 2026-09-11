<?php

namespace App\Services\Templates;

use App\Services\NeoFeeder\Contracts\ChannelContract;
use App\Services\NeoFeeder\Contracts\FieldContract;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\NeoFeeder\Contracts\OperationContract;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use PhpOffice\PhpSpreadsheet\RichText\RichText;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

final class NeoFeederTemplateWorkbookService
{
    public const TEMPLATE_VERSION = 'phase-1.0';

    public function __construct(
        private readonly NeoFeederContractRegistry $registry,
    ) {
    }

    public function generate(): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $spreadsheet->getProperties()
            ->setCreator('Bridge Neo Feeder')
            ->setTitle('Bridge Neo Feeder Import Template')
            ->setSubject('Neo Feeder Excel import template')
            ->setDescription('Template upload Excel untuk staging data Neo Feeder.');

        $this->buildReadme($spreadsheet->getActiveSheet());
        $referenceSheets = $this->buildReferenceSheets($spreadsheet);
        $this->buildChannelSheets($spreadsheet, $referenceSheets);

        $spreadsheet->setActiveSheetIndex(0);

        return $spreadsheet;
    }

    private function buildReadme(Worksheet $sheet): void
    {
        $sheet->setTitle('README');
        $sheet->fromArray([
            ['Bridge Neo Feeder Import Template'],
            ['Template Version', self::TEMPLATE_VERSION],
            ['Generated At', now()->toIso8601String()],
            [],
            ['Rules'],
            ['1. Isi sheet data sesuai urutan dependency dari kiri ke kanan.'],
            ['2. Kolom berwarna biru wajib diisi.'],
            ['3. Kolom tanggal memakai format yyyy-mm-dd.'],
            ['4. Kolom referensi memakai value/id dari sheet ref_*.'],
            ['5. Kolom identity Neo Feeder boleh kosong untuk insert baru.'],
        ]);

        $sheet->mergeCells('A1:D1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getStyle('A5')->getFont()->setBold(true);
        $sheet->getColumnDimension('A')->setWidth(26);
        $sheet->getColumnDimension('B')->setWidth(42);
    }

    /**
     * @return array<string, string>
     */
    private function buildReferenceSheets(Spreadsheet $spreadsheet): array
    {
        $referenceChannel = $this->registry->channel('references');

        if (! $referenceChannel instanceof ChannelContract) {
            return [];
        }

        $sheets = [];

        foreach ($referenceChannel->operations as $operationPayload) {
            $operation = OperationContract::fromArray($operationPayload);
            $sheetName = $this->referenceSheetName($operation);
            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($sheetName);
            $sheet->fromArray([
                ['value', 'label', 'raw_json'],
                ['', '', ''],
            ]);
            $sheet->getStyle('A1:C1')->getFont()->setBold(true);
            $sheet->getStyle('A1:C1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');
            $sheet->getColumnDimension('A')->setWidth(32);
            $sheet->getColumnDimension('B')->setWidth(42);
            $sheet->getColumnDimension('C')->setWidth(60);

            $sheets[$operation->action] = $sheetName;
        }

        return $sheets;
    }

    /**
     * @param  array<string, string>  $referenceSheets
     */
    private function buildChannelSheets(Spreadsheet $spreadsheet, array $referenceSheets): void
    {
        foreach ($this->registry->dependencyOrder() as $channelKey) {
            if ($channelKey === 'references') {
                continue;
            }

            $channel = $this->registry->channel($channelKey);

            if (! $channel instanceof ChannelContract) {
                continue;
            }

            $sheet = $spreadsheet->createSheet();
            $sheet->setTitle($this->sheetTitle($channel->sheetName ?: $channel->key));
            $this->buildChannelSheet($sheet, $channel, $referenceSheets);
        }
    }

    /**
     * @param  array<string, string>  $referenceSheets
     */
    private function buildChannelSheet(Worksheet $sheet, ChannelContract $channel, array $referenceSheets): void
    {
        $fields = array_map(fn (array $payload): FieldContract => FieldContract::fromArray($payload), $channel->fields);
        $headers = array_map(fn (FieldContract $field): string => $field->name, $fields);

        $sheet->fromArray([$headers], null, 'A1');
        $sheet->freezePane('A2');
        $sheet->getStyle('1:1')->getFont()->setBold(true);
        $sheet->getStyle('1:1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('1:1')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E5E7EB');

        foreach ($fields as $index => $field) {
            $column = $this->columnName($index + 1);
            $sheet->getColumnDimension($column)->setWidth($this->columnWidth($field));

            if ($field->required) {
                $sheet->getStyle("{$column}1")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('BFDBFE');
            }

            if ($field->format === 'yyyy-mm-dd' || $field->type === 'date') {
                $comment = $sheet->getComment("{$column}1");
                $comment->getText()->createTextRun('Format tanggal: yyyy-mm-dd');
            }

            if ($field->reference !== null && isset($referenceSheets[$field->reference])) {
                $this->addReferenceDropdown($sheet, $column, $referenceSheets[$field->reference]);
            }
        }
    }

    private function addReferenceDropdown(Worksheet $sheet, string $column, string $referenceSheet): void
    {
        for ($row = 2; $row <= 500; $row++) {
            $validation = $sheet->getCell("{$column}{$row}")->getDataValidation();
            $validation->setType(DataValidation::TYPE_LIST);
            $validation->setErrorStyle(DataValidation::STYLE_STOP);
            $validation->setAllowBlank(true);
            $validation->setShowDropDown(true);
            $validation->setFormula1("'{$referenceSheet}'!\$A\$2:\$A\$500");
        }
    }

    private function referenceSheetName(OperationContract $operation): string
    {
        return $this->sheetTitle('ref_'.$operation->name);
    }

    private function sheetTitle(string $title): string
    {
        $title = preg_replace('/[\\\\\\/\\?\\*\\[\\]:]/', '_', $title) ?: 'sheet';

        return substr($title, 0, 31);
    }

    private function columnWidth(FieldContract $field): int
    {
        if ($field->type === 'date') {
            return 16;
        }

        if ($field->maxLength !== null) {
            return min(max($field->maxLength + 4, 14), 42);
        }

        return match ($field->type) {
            'uuid' => 38,
            'json' => 46,
            default => 22,
        };
    }

    private function columnName(int $index): string
    {
        $name = '';

        while ($index > 0) {
            $index--;
            $name = chr(65 + ($index % 26)).$name;
            $index = intdiv($index, 26);
        }

        return $name;
    }
}
