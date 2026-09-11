<?php

namespace Tests\Unit;

use App\Services\Templates\NeoFeederTemplateWorkbookService;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;
use Tests\TestCase;

class NeoFeederTemplateWorkbookServiceTest extends TestCase
{
    public function test_it_generates_phase_one_template_sheets(): void
    {
        $workbook = app(NeoFeederTemplateWorkbookService::class)->generate();

        $this->assertContains('README', $workbook->getSheetNames());
        $this->assertContains('ref_prodi', $workbook->getSheetNames());
        $this->assertContains('mahasiswa_biodata', $workbook->getSheetNames());
        $this->assertContains('mahasiswa_riwayat_pendidikan', $workbook->getSheetNames());
        $this->assertContains('mata_kuliah', $workbook->getSheetNames());
        $this->assertContains('mahasiswa_lulus_do', $workbook->getSheetNames());
    }

    public function test_it_writes_readme_metadata(): void
    {
        $sheet = app(NeoFeederTemplateWorkbookService::class)->generate()->getSheetByName('README');

        $this->assertSame('Template Version', $sheet->getCell('A2')->getValue());
        $this->assertSame(NeoFeederTemplateWorkbookService::TEMPLATE_VERSION, $sheet->getCell('B2')->getValue());
        $this->assertSame('Generated At', $sheet->getCell('A3')->getValue());
        $this->assertNotEmpty($sheet->getCell('B3')->getValue());
    }

    public function test_it_marks_required_columns_and_adds_date_notes(): void
    {
        $sheet = app(NeoFeederTemplateWorkbookService::class)->generate()->getSheetByName('mahasiswa_biodata');

        $this->assertSame('nama_mahasiswa', $sheet->getCell('B1')->getValue());
        $this->assertSame('BFDBFE', $sheet->getStyle('B1')->getFill()->getStartColor()->getRGB());
        $this->assertSame('tanggal_lahir', $sheet->getCell('E1')->getValue());
        $this->assertStringContainsString('yyyy-mm-dd', $sheet->getComment('E1')->getText()->getPlainText());
    }

    public function test_it_adds_reference_dropdowns(): void
    {
        $sheet = app(NeoFeederTemplateWorkbookService::class)->generate()->getSheetByName('mahasiswa_biodata');
        $validation = $sheet->getCell('F2')->getDataValidation();

        $this->assertSame(DataValidation::TYPE_LIST, $validation->getType());
        $this->assertSame("'ref_agama'!\$A\$2:\$A\$500", $validation->getFormula1());
    }
}
