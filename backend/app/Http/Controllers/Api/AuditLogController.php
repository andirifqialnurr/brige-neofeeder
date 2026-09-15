<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController
{
    public function index(Request $request): JsonResponse
    {
        $page = $this->query($request)->paginate(25);

        return response()->json(['data' => $page->getCollection()->map(fn ($log) => $this->serialize($log)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => $page->perPage()]],
            200, ['Cache-Control' => 'no-store']);
    }

    public function export(Request $request): StreamedResponse
    {
        $query = $this->query($request);
        abort_if((clone $query)->count() > 10000, 422, 'Batasi filter ekspor hingga maksimal 10.000 peristiwa.');
        $exportLog = AuditLog::create(['tenant_id' => $request->user()->isAdmin() ? $request->input('tenant_id') : $request->user()->tenant_id,
            'actor_id' => $request->user()->id, 'event' => 'audit.exported', 'metadata' => ['format' => 'csv']]);
        $query->where('id', '!=', $exportLog->id);

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['ID', 'Waktu', 'Kampus', 'Aktor', 'Peristiwa', 'ID objek'], ',', '"', '');
            foreach ($query->lazy(250) as $log) {
                $row = $this->serialize($log);
                $values = [$row['id'], $row['created_at'], $row['tenant_name'], $row['actor_name'], $row['event'], $row['subject_id']];
                fputcsv($stream, array_map(fn ($value) => preg_match('/^[\s]*[=+@-]/u', (string) $value) ? "'".$value : $value, $values), ',', '"', '');
            }
            fclose($stream);
        }, 'audit-log.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    private function query(Request $request): Builder
    {
        $input = $request->validate(['tenant_id' => 'nullable|uuid|exists:tenants,id', 'search' => 'nullable|string|max:100',
            'from' => 'nullable|date_format:Y-m-d', 'to' => 'nullable|date_format:Y-m-d', 'page' => 'sometimes|integer|min:1']);
        $from = $input['from'] ?? now()->subDays(30)->toDateString();
        abort_if(($input['to'] ?? null) && $input['to'] < $from, 422, 'Tanggal akhir harus setelah tanggal awal.');
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->tenant_id, 403);
        $query = AuditLog::with(['actor:id,name', 'tenant:id,name'])->orderByDesc('created_at')->orderByDesc('id');
        if (! $user->isAdmin()) {
            $query->where('tenant_id', $user->tenant_id);
        } elseif ($input['tenant_id'] ?? null) {
            $query->where('tenant_id', $input['tenant_id']);
        }
        if ($input['search'] ?? null) {
            $query->where(fn ($q) => $q->where('event', 'like', '%'.$input['search'].'%')->orWhere('subject_id', 'like', '%'.$input['search'].'%'));
        }
        $query->where('created_at', '>=', $from.' 00:00:00');
        if ($input['to'] ?? null) {
            $query->where('created_at', '<=', $input['to'].' 23:59:59');
        }

        return $query;
    }

    private function serialize(AuditLog $log): array
    {
        return [...$log->only(['id', 'event', 'subject_id']), 'created_at' => $log->created_at->toIso8601String(),
            'tenant_name' => $log->tenant?->name ?? 'Platform', 'actor_name' => $log->actor?->name ?? 'Sistem',
            'metadata' => array_intersect_key($log->metadata ?? [], array_flip(['purpose', 'staging_record_id', 'records', 'format', 'staging_preserved']))];
    }
}
