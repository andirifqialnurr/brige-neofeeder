<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\ImportBatch;
use App\Models\SourceConnection;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class BatchReconciliationController
{
    public const STATES = ['not_sent', 'invalid', 'active', 'success', 'failed', 'unknown', 'skipped'];

    public function __invoke(Request $request, ImportBatch $importBatch)
    {
        abort_unless($request->user()->isAdmin() || $request->user()->tenant_id === $importBatch->tenant_id, 403);
        $input = $request->validate(['page' => 'sometimes|integer|min:1', 'state' => ['nullable', Rule::in(self::STATES)], 'format' => ['nullable', Rule::in(['csv'])]]);
        abort_if(in_array($importBatch->status, ['uploaded', 'parsing']), 409, 'Tunggu parsing selesai sebelum rekonsiliasi.');
        $rows = DB::table('staging_records as r')->where('r.import_batch_id', $importBatch->id)
            ->leftJoin('sync_attempts as a', function ($join) {
                $join->on('a.staging_record_id', '=', 'r.id')->whereRaw('a.id = (select a2.id from sync_attempts a2 where a2.staging_record_id = r.id order by a2.id desc limit 1)');
            })->orderBy('r.sheet_name')->orderBy('r.row_number')->orderBy('r.id')
            ->limit(20001)->get(['r.id', 'r.channel', 'r.sheet_name', 'r.row_number', 'r.status as staging_status', 'r.source_lineage',
                'a.id as attempt_id', 'a.status as delivery_status', 'a.error_code', 'a.identity_payload', 'a.completed_at']);
        abort_if($rows->count() > 20000, 422, 'Laporan dibatasi 20.000 baris per batch.');
        $rows = $rows->map(function ($row) {
            $lineage = json_decode($row->source_lineage ?? '{}', true) ?? [];
            $identity = json_decode($row->identity_payload ?? '{}', true) ?? [];
            $allowedIds = app(NeoFeederContractRegistry::class)->channel($row->channel)?->identityFields ?? [];
            $state = match ($row->delivery_status) {
                'queued', 'retrying', 'syncing' => 'active',
                'success', 'failed', 'unknown', 'skipped' => $row->delivery_status,
                default => $row->staging_status === 'invalid' ? 'invalid' : ($row->staging_status === 'skipped' ? 'skipped' : 'not_sent'),
            };

            return ['record_id' => $row->id, 'sheet' => $row->sheet_name, 'source_row' => $lineage['source_row'] ?? $row->row_number,
                'source_id' => $lineage['source_id'] ?? null, 'mapping_version' => $lineage['mapping_version'] ?? null,
                'staging_status' => $row->staging_status, 'state' => $state, 'attempt_id' => $row->attempt_id,
                'error_code' => $row->error_code, 'result_ids' => array_filter($identity, fn ($value, $key) => in_array($key, $allowedIds, true) && is_scalar($value), ARRAY_FILTER_USE_BOTH),
                'completed_at' => $row->completed_at];
        });
        $summary = array_fill_keys(self::STATES, 0);
        foreach ($rows as $row) {
            $summary[$row['state']]++;
        }
        $sourceCount = $importBatch->source_type === 'mapping'
            ? SourceConnection::where('tenant_id', $importBatch->tenant_id)->whereKey($importBatch->summary['source_id'] ?? null)->value('row_count') : null;
        $generated = now()->toIso8601String();
        $filtered = $rows->filter(fn ($row) => empty($input['state']) || $row['state'] === $input['state'])->values();
        if (($input['format'] ?? null) === 'csv') {
            AuditLog::create(['tenant_id' => $importBatch->tenant_id, 'actor_id' => $request->user()->id, 'event' => 'import.reconciliation.exported', 'subject_id' => $importBatch->id]);

            return response()->streamDownload(function () use ($filtered, $generated, $importBatch) {
                $out = fopen('php://output', 'wb');
                fputcsv($out, ['generated_at', 'batch_id', 'record_id', 'sheet', 'source_row', 'source_id', 'mapping_version', 'staging_status', 'state', 'attempt_id', 'error_code', 'result_ids', 'completed_at'], ',', '"', '');
                foreach ($filtered as $row) {
                    $row['result_ids'] = json_encode($row['result_ids'], JSON_UNESCAPED_UNICODE);
                    $values = array_map(function ($value) {
                        $text = (string) ($value ?? '');

                        return preg_match('/^[\s]*[=+@-]/u', $text) ? "'".$text : $text;
                    }, [$generated, $importBatch->id, ...array_values($row)]);
                    fputcsv($out, $values, ',', '"', '');
                }
                fclose($out);
            }, 'rekonsiliasi-'.$importBatch->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
        }
        $page = $input['page'] ?? 1;

        return response()->json(['data' => $filtered->slice(($page - 1) * 25, 25)->values(),
            'summary' => [...$summary, 'staging_rows' => $rows->count(), 'source_rows' => $sourceCount, 'source_difference' => $sourceCount === null ? null : $sourceCount - $rows->count()],
            'generated_at' => $generated, 'meta' => ['current_page' => $page, 'last_page' => max(1, (int) ceil($filtered->count() / 25)), 'total' => $filtered->count()]], 200, ['Cache-Control' => 'no-store']);
    }
}
