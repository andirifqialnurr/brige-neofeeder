<?php

namespace App\Services\Templates;

use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class TrialPackService
{
    public function __construct(private NeoFeederTemplateWorkbookService $templates, private NeoFeederContractRegistry $registry) {}

    public function generate(): string
    {
        $directory = storage_path('app/private/trial-packs/'.now()->format('Ymd-His').'-'.Str::uuid());
        if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
            throw new RuntimeException('Direktori paket trial tidak dapat dibuat.');
        }
        $samples = [
            '01-biodata' => ['channel' => 'mahasiswa_biodata', 'row' => [
                'nama_mahasiswa' => 'MAHASISWA FIKTIF TRIAL BRIDGE', 'jenis_kelamin' => 'L',
                'tempat_lahir' => 'KOTA FIKTIF', 'tanggal_lahir' => '2001-01-01', 'nik' => '0000000000000001',
                'id_agama' => 'ISI_ID_AGAMA', 'kewarganegaraan' => 'ISI_ID_NEGARA', 'kelurahan' => 'KELURAHAN FIKTIF',
                'id_wilayah' => 'ISI_ID_WILAYAH', 'penerima_kps' => '0', 'nama_ibu_kandung' => 'IBU FIKTIF TRIAL',
                'id_kebutuhan_khusus_mahasiswa' => 'ISI_ID_KEBUTUHAN', 'id_kebutuhan_khusus_ayah' => 'ISI_ID_KEBUTUHAN', 'id_kebutuhan_khusus_ibu' => 'ISI_ID_KEBUTUHAN',
            ]],
            '02-riwayat' => ['channel' => 'mahasiswa_riwayat_pendidikan', 'row' => [
                'id_mahasiswa' => 'ISI_ID_HASIL_BIODATA', 'nim' => 'TRIAL-BRIDGE-0001',
                'id_jenis_daftar' => 'ISI_ID_JENIS_DAFTAR', 'id_periode_masuk' => 'ISI_ID_SEMESTER',
                'tanggal_daftar' => '2026-08-01', 'id_perguruan_tinggi' => 'ISI_ID_PT', 'id_prodi' => 'ISI_ID_PRODI', 'biaya_masuk' => '0',
            ]],
        ];
        foreach ($samples as $name => $sample) {
            $book = $this->templates->generate();
            try {
                $sheet = $book->getSheetByName($sample['channel']);
                $fields = array_column($this->registry->channel($sample['channel'])->fields, 'name');
                foreach ($fields as $column => $field) {
                    if (isset($sample['row'][$field])) {
                        $sheet->setCellValueExplicit([$column + 1, 2], $sample['row'][$field], DataType::TYPE_STRING);
                    }
                }
                $book->getSheetByName('README')->setCellValue('A12', 'PAKET FIKTIF: ganti ISI_* dengan referensi trial. Belum layak dikirim.');
                (new Xlsx($book))->save($directory.'/'.$name.'.xlsx');
            } finally {
                $book->disconnectWorksheets();
            }
            $stream = fopen($directory.'/'.$name.'-mapping.csv', 'xb');
            if (! $stream) {
                throw new RuntimeException('CSV trial tidak dapat dibuat.');
            }
            fputcsv($stream, array_keys($sample['row']), ',', '"', '');
            fputcsv($stream, array_values($sample['row']), ',', '"', '');
            fclose($stream);
        }
        $this->write($directory.'/README.md', file_get_contents(resource_path('trial/README.md')));
        $this->write($directory.'/contract-baseline.json', json_encode(config('neofeeder-contracts'), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        $stream = fopen($directory.'/response-log.csv', 'xb');
        fputcsv($stream, ['checkpoint', 'act', 'status', 'tested_at', 'error_code', 'description_sanitized', 'result_id', 'batch_id', 'attempt_id', 'evidence_path'], ',', '"', '');
        foreach (['GetToken', 'GetProdi', 'GetDictionary', 'ReferenceSync', 'DryRunBiodata', 'InsertBiodataMahasiswa', 'VerifyBiodata', 'DryRunRiwayat', 'InsertRiwayatPendidikanMahasiswa', 'VerifyRiwayat'] as $index => $action) {
            fputcsv($stream, [(string) ($index + 1), $action, 'BELUM DIUJI', '', '', '', '', '', '', ''], ',', '"', '');
        }
        fclose($stream);
        $manifest = ['kind' => 'synthetic-trial-preparation', 'generated_at' => now()->toIso8601String(),
            'template_version' => NeoFeederTemplateWorkbookService::TEMPLATE_VERSION,
            'live_verified' => false, 'outbound_requests' => 0, 'files' => []];
        foreach (glob($directory.'/*') as $file) {
            $manifest['files'][basename($file)] = ['sha256' => hash_file('sha256', $file), 'bytes' => filesize($file)];
        }
        $this->write($directory.'/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));

        return $directory;
    }

    private function write(string $path, string $content): void
    {
        if (file_put_contents($path, $content, LOCK_EX) === false) {
            throw new RuntimeException('File paket trial tidak dapat disimpan.');
        }
    }
}
