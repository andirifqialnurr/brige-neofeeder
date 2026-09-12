<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Templates\NeoFeederTemplateWorkbookService;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TemplateWorkbookController extends Controller
{
    public function __invoke(NeoFeederTemplateWorkbookService $workbookService): StreamedResponse
    {
        $spreadsheet = $workbookService->generate();
        $fileName = 'bridge-neofeeder-template-'.now()->format('Ymd-His').'.xlsx';

        return response()->streamDownload(
            function () use ($spreadsheet): void {
                $writer = new Xlsx($spreadsheet);
                $writer->save('php://output');
                $spreadsheet->disconnectWorksheets();
            },
            $fileName,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            ],
        );
    }
}
