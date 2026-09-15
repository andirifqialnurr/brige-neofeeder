<?php

namespace App\Services\Mapping;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use ZipArchive;

class SourceFileReader
{
    public const MAX_ROWS = 2000;

    public function read(UploadedFile $file, string $delimiter = ',', ?string $sheetName = null): array
    {
        $headers = [];
        $rows = [];
        $append = function (array $values, int $line) use (&$headers, &$rows): void {
            if ($line === 1) {
                $headers = array_map(fn ($value) => trim(ltrim((string) $value, "\xEF\xBB\xBF")), $values);
                if (count($headers) < 1 || count($headers) > 64 || in_array('', $headers, true)
                    || count(array_unique($headers)) !== count($headers) || collect($headers)->contains(fn ($header) => mb_strlen($header) > 100)) {
                    $this->invalid('Header harus unik, terisi, dan maksimal 64 kolom (100 karakter per header).');
                }

                return;
            }
            if (collect($values)->every(fn ($value) => $value === null || trim((string) $value) === '')) {
                return;
            }
            if (count($values) !== count($headers)) {
                $this->invalid('Jumlah kolom berbeda dari header pada baris '.$line.'.');
            }
            if (count($rows) >= self::MAX_ROWS) {
                $this->invalid('Maksimal 2.000 baris per file mapping.');
            }
            foreach ($values as $value) {
                if (mb_strlen((string) $value) > 2000 || ! mb_check_encoding((string) $value, 'UTF-8')) {
                    $this->invalid('Gunakan teks UTF-8 dengan maksimal 2.000 karakter per sel.');
                }
                if (is_string($value) && str_starts_with(ltrim($value), '=')) {
                    $this->invalid('File sumber harus berisi nilai, bukan formula.');
                }
            }
            $rows[] = ['row_number' => $line, 'values' => array_combine($headers, $values)];
        };
        if (strtolower($file->getClientOriginalExtension()) === 'csv') {
            $stream = fopen($file->getRealPath(), 'rb');
            try {
                $line = 0;
                while (($values = fgetcsv($stream, 0, $delimiter, '"', '')) !== false) {
                    $append($values, ++$line);
                }
            } finally {
                fclose($stream);
            }
            $sheetName = null;
        } else {
            $zip = new ZipArchive;
            if ($zip->open($file->getRealPath()) !== true) {
                $this->invalid('Workbook tidak dapat dibaca.');
            }
            try {
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) {
                    $size += $zip->statIndex($i)['size'];
                }
                if ($zip->numFiles > 1000 || $size > 32 * 1024 * 1024) {
                    $this->invalid('Workbook terlalu besar setelah dibuka.');
                }
            } finally {
                $zip->close();
            }
            $reader = new Xlsx;
            $sheets = $reader->listWorksheetNames($file->getRealPath());
            $sheetName = $sheetName ?: ($sheets[0] ?? '');
            if (! in_array($sheetName, $sheets, true)) {
                $this->invalid('Sheet sumber tidak ditemukan.');
            }
            $info = collect($reader->listWorksheetInfo($file->getRealPath()))->firstWhere('worksheetName', $sheetName);
            if (! $info || $info['totalRows'] > self::MAX_ROWS + 1 || $info['totalColumns'] > 64) {
                $this->invalid('Sheet melebihi batas 2.000 baris data atau 64 kolom.');
            }
            $reader->setReadDataOnly(true)->setLoadSheetsOnly($sheetName)->setReadFilter(new class implements IReadFilter
            {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row <= 2002 && Coordinate::columnIndexFromString($columnAddress) <= 65;
                }
            });
            $book = $reader->load($file->getRealPath());
            try {
                $sheet = $book->getSheetByName($sheetName);
                $width = Coordinate::columnIndexFromString($sheet->getHighestDataColumn());
                for ($line = 1; $line <= $sheet->getHighestDataRow(); $line++) {
                    $values = [];
                    for ($col = 1; $col <= $width; $col++) {
                        $cell = $sheet->getCell([$col, $line]);
                        if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                            $this->invalid('File sumber harus berisi nilai, bukan formula.');
                        }
                        $values[] = $cell->getValue();
                    }
                    $append($values, $line);
                }
            } finally {
                $book->disconnectWorksheets();
            }
        }
        if ($rows === []) {
            $this->invalid('File sumber tidak memiliki baris data.');
        }

        return ['headers' => $headers, 'snapshot' => $rows, 'sheet_name' => $sheetName, 'row_count' => count($rows)];
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['file' => $message]);
    }
}
