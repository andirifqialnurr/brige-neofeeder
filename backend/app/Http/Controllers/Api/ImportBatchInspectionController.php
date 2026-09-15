<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\StagingRecord;
use App\Services\DryRun\ImportBatchDryRunService;
use App\Services\Imports\ImportWorkbookParser;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use App\Services\Operations\SensitiveData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportBatchInspectionController extends Controller
{
    public function show(Request $request, ImportBatch $importBatch, NeoFeederContractRegistry $registry): JsonResponse
    {
        $this->authorizeBatch($request, $importBatch);
        $importBatch->load('tenant', 'approver')->loadCount('stagingRecords');

        return response()->json(['data' => [
            ...$importBatch->only(['id', 'tenant_id', 'source_type', 'template_version', 'status', 'summary', 'staging_records_count', 'created_at', 'updated_at']),
            'tenant_name' => $importBatch->tenant?->name,
            'approval' => [
                'dry_run_hash' => $importBatch->dry_run_hash,
                'approved_at' => $importBatch->approved_at,
                'approved_by_name' => $importBatch->approver?->name,
                'approved' => (bool) ($importBatch->approved_at && $importBatch->approved_by && $importBatch->approved_hash === $importBatch->dry_run_hash),
            ],
            'is_demo' => ($importBatch->tenant->metadata['demo'] ?? false) === true,
            'sheets' => $importBatch->stagingRecords()->select('sheet_name')->selectRaw('COUNT(*) AS total_rows')->groupBy('sheet_name')->orderBy('sheet_name')->get(),
            'dependency_order' => $registry->dependencyOrder(),
        ]]);
    }

    public function rows(Request $request, ImportBatch $importBatch): JsonResponse
    {
        $this->authorizeBatch($request, $importBatch);
        $input = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'sheet' => 'nullable|string|max:64',
            'status' => ['nullable', Rule::in(ImportWorkbookParser::ROW_STATUSES)],
        ]);
        $query = $importBatch->stagingRecords()->orderBy('sheet_name')->orderBy('row_number')->orderBy('id');
        foreach (['sheet' => 'sheet_name', 'status' => 'status'] as $filter => $column) {
            if (! empty($input[$filter])) {
                $query->where($column, $input[$filter]);
            }
        }
        $page = $query->paginate($input['per_page'] ?? 25);

        return response()->json([
            'data' => $page->getCollection()->map(fn (StagingRecord $row) => [
                ...$row->only(['id', 'channel', 'sheet_name', 'row_number', 'status']),
                'errors' => count($row->validation_result['errors'] ?? []),
                'warnings' => count($row->validation_result['warnings'] ?? []),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()],
        ]);
    }

    public function row(Request $request, ImportBatch $importBatch, string $record, ImportBatchDryRunService $preview, SensitiveData $privacy): JsonResponse
    {
        $this->authorizeBatch($request, $importBatch);
        $row = $importBatch->stagingRecords()->findOrFail($record);
        $this->audit($request, $importBatch, 'import.row.viewed', ['staging_record_id' => $row->id]);

        return response()->json(['data' => $privacy->present([
            ...$preview->previewRecord($row),
            'raw_row' => $request->user()->isAdmin() ? $row->raw_row : null,
            'normalized_row' => $row->normalized_row,
            'can_reveal_sensitive' => $request->user()->isAdmin(),
            'sensitive_revealed' => false,
        ])], 200, ['Cache-Control' => 'no-store']);
    }

    public function reveal(Request $request, ImportBatch $importBatch, string $record, ImportBatchDryRunService $preview, SensitiveData $privacy): JsonResponse
    {
        $this->authorizeBatch($request, $importBatch);
        abort_unless($request->user()->isAdmin(), 403);
        $input = $request->validate(['purpose' => ['required', Rule::in(['verification', 'correction'])]]);
        $row = $importBatch->stagingRecords()->findOrFail($record);
        $this->audit($request, $importBatch, 'import.row.sensitive_viewed', ['staging_record_id' => $row->id, 'purpose' => $input['purpose']]);

        return response()->json(['data' => $privacy->present([
            ...$preview->previewRecord($row), 'raw_row' => $row->raw_row, 'normalized_row' => $row->normalized_row,
            'can_reveal_sensitive' => true, 'sensitive_revealed' => true,
        ], true)], 200, ['Cache-Control' => 'no-store']);
    }

    public function report(Request $request, ImportBatch $importBatch): StreamedResponse
    {
        $this->authorizeBatch($request, $importBatch);
        abort_if(in_array($importBatch->status, ['uploaded', 'parsing'], true), 409, 'Tunggu proses parsing selesai.');
        $this->audit($request, $importBatch, 'import.report.downloaded');

        return response()->streamDownload(function () use ($importBatch): void {
            $workbook = new Spreadsheet;
            $sheet = $workbook->getActiveSheet()->setTitle('Temuan');
            $sheet->fromArray(['Sheet', 'Baris', 'Status', 'Severity', 'Field', 'Rule', 'Pesan']);
            $line = 2;
            $write = function (array $values) use ($sheet, &$line): void {
                foreach ($values as $index => $value) {
                    $sheet->setCellValueExplicit([$index + 1, $line], (string) ($value ?? ''), DataType::TYPE_STRING);
                }
                $line++;
            };
            foreach (($importBatch->summary['missing_sheets'] ?? []) as $missing) {
                $write([$missing, '', 'invalid', 'error', '', 'missing_sheet', 'Sheet wajib tidak ditemukan.']);
            }
            if ($error = $importBatch->summary['error'] ?? null) {
                $write(['', '', $importBatch->status, 'error', '', 'parse_failed', $error]);
            }
            foreach ($importBatch->stagingRecords()->orderBy('sheet_name')->orderBy('row_number')->cursor() as $row) {
                foreach (['errors' => 'error', 'warnings' => 'warning', 'info' => 'info'] as $key => $severity) {
                    foreach (($row->validation_result[$key] ?? []) as $issue) {
                        $write([$row->sheet_name, $row->row_number, $row->status, $severity, $issue['field'] ?? '', $issue['rule'] ?? '', app(SensitiveData::class)->present($issue['message'] ?? '', false, 'message')]);
                    }
                }
            }
            $sheet->freezePane('A2')->setAutoFilter('A1:G'.max(1, $line - 1));
            $sheet->getStyle('A1:G1')->getFont()->setBold(true);
            foreach (['A' => 34, 'B' => 10, 'C' => 14, 'D' => 14, 'E' => 32, 'F' => 28, 'G' => 70] as $column => $width) {
                $sheet->getColumnDimension($column)->setWidth($width);
            }
            $sheet->getStyle('A1:G'.max(1, $line - 1))->getAlignment()->setWrapText(true);
            try {
                (new Xlsx($workbook))->save('php://output');
            } finally {
                $workbook->disconnectWorksheets();
            }
        }, 'temuan-'.$importBatch->id.'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store',
        ]);
    }

    private function authorizeBatch(Request $request, ImportBatch $batch): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || ($user->tenant_id && $user->tenant_id === $batch->tenant_id), 403);
    }

    private function audit(Request $request, ImportBatch $batch, string $event, array $metadata = []): void
    {
        AuditLog::create([
            'tenant_id' => $batch->tenant_id, 'actor_id' => $request->user()->id,
            'event' => $event, 'subject_type' => ImportBatch::class, 'subject_id' => $batch->id,
            'metadata' => $metadata, 'ip_address' => $request->ip(),
        ]);
    }
}
