<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\MappingProfile;
use App\Models\ReferenceRecord;
use App\Models\SourceConnection;
use App\Services\Mapping\FileMappingService;
use App\Services\Mapping\MappingReferenceResolver;
use App\Services\Mapping\SourceFileReader;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class FileMappingController
{
    public function references(Request $request, NeoFeederContractRegistry $registry): JsonResponse
    {
        $input = $request->validate(['tenant_id' => 'nullable|uuid|exists:tenants,id',
            'channel' => ['required', Rule::in(FileMappingService::CHANNELS)], 'field' => 'required|string', 'search' => 'nullable|string|max:100']);
        $tenant = $this->tenant($request);
        $field = collect($registry->channel($input['channel'])->fields)->firstWhere('name', $input['field']);
        abort_unless($field['reference'] ?? null, 422);
        $query = ReferenceRecord::where('tenant_id', $tenant)->where('endpoint', $field['reference']);
        if ($search = trim($input['search'] ?? '')) {
            $query->where(fn ($q) => $q->where('label', 'like', '%'.$search.'%')->orWhere('value', 'like', '%'.$search.'%'));
        }

        return response()->json(['data' => $query->orderBy('label')->orderBy('value')->limit(100)->get(['value', 'label'])], 200, ['Cache-Control' => 'no-store']);
    }

    public function workspace(Request $request, NeoFeederContractRegistry $registry): JsonResponse
    {
        $request->validate(['tenant_id' => 'nullable|uuid|exists:tenants,id']);
        $tenantId = $this->tenant($request);

        return response()->json(['data' => [
            'sources' => SourceConnection::where('tenant_id', $tenantId)->latest()->limit(100)
                ->get(['id', 'tenant_id', 'name', 'headers', 'sheet_name', 'row_count', 'created_at']),
            'profiles' => MappingProfile::with('currentVersion')->where('tenant_id', $tenantId)->latest()->limit(100)->get()->map(fn ($profile) => $this->profile($profile)),
            'channels' => array_map(fn ($key) => ['key' => $key, 'fields' => $registry->channel($key)->fields], FileMappingService::CHANNELS),
        ]], 200, ['Cache-Control' => 'no-store']);
    }

    public function versions(Request $request, MappingProfile $mappingProfile): JsonResponse
    {
        $this->authorizeProfile($request, $mappingProfile);

        return response()->json(['data' => $mappingProfile->versions()->orderByDesc('version')->get(['id', 'version', 'rules', 'created_at'])], 200, ['Cache-Control' => 'no-store']);
    }

    public function duplicate(Request $request, MappingProfile $mappingProfile): JsonResponse
    {
        $this->authorizeProfile($request, $mappingProfile);
        $input = $request->validate(['name' => 'required|string|max:120']);
        $copy = DB::transaction(function () use ($mappingProfile, $input, $request) {
            $mappingProfile = MappingProfile::query()->lockForUpdate()->findOrFail($mappingProfile->id);
            $version = $mappingProfile->currentVersion()->firstOrFail();
            $copy = MappingProfile::create(['tenant_id' => $mappingProfile->tenant_id, 'name' => $input['name'], 'channel' => $mappingProfile->channel, 'version' => 1]);
            $copy->versions()->create(['version' => 1, 'rules' => $version->rules]);
            $this->audit($request, $mappingProfile->tenant_id, 'mapping.duplicated', $copy->id);

            return $copy->load('currentVersion');
        });

        return response()->json(['data' => $this->profile($copy)], 201, ['Cache-Control' => 'no-store']);
    }

    public function restore(Request $request, MappingProfile $mappingProfile): JsonResponse
    {
        $this->authorizeProfile($request, $mappingProfile);
        $input = $request->validate(['version' => 'required|integer|min:1', 'expected_version' => 'required|integer|min:1']);
        $restored = DB::transaction(function () use ($mappingProfile, $input, $request) {
            $mappingProfile = MappingProfile::query()->lockForUpdate()->findOrFail($mappingProfile->id);
            abort_unless($mappingProfile->version === $input['expected_version'], 409, 'Profil telah berubah. Muat ulang riwayat versi.');
            $version = $mappingProfile->versions()->where('version', $input['version'])->firstOrFail();
            $next = $mappingProfile->version + 1;
            $mappingProfile->update(['version' => $next]);
            $mappingProfile->versions()->create(['version' => $next, 'rules' => $version->rules]);
            $this->audit($request, $mappingProfile->tenant_id, 'mapping.version_restored', $mappingProfile->id);

            return $mappingProfile->load('currentVersion');
        });

        return response()->json(['data' => $this->profile($restored)], 200, ['Cache-Control' => 'no-store']);
    }

    public function upload(Request $request, SourceFileReader $reader): JsonResponse
    {
        $input = $request->validate(['tenant_id' => 'nullable|uuid|exists:tenants,id', 'file' => 'required|file|mimes:csv,txt,xlsx|max:2048',
            'delimiter' => ['nullable', Rule::in([',', ';', "\t"])], 'sheet_name' => 'nullable|string|max:31']);
        $tenantId = $this->tenant($request);
        $file = $request->file('file');
        abort_unless(in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'xlsx'], true), 422, 'Gunakan file CSV atau XLSX.');
        try {
            $snapshot = $reader->read($file, $input['delimiter'] ?? ',', $input['sheet_name'] ?? null);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca. Gunakan CSV UTF-8 atau XLSX tanpa formula.']);
        }
        $source = SourceConnection::create(['tenant_id' => $tenantId, 'type' => 'file',
            'name' => mb_substr($file->getClientOriginalName(), 0, 255), 'sha256' => hash_file('sha256', $file->getRealPath()), ...$snapshot]);
        $this->audit($request, $tenantId, 'source.uploaded', $source->id);

        return response()->json(['data' => $source->only(['id', 'tenant_id', 'name', 'headers', 'sheet_name', 'row_count', 'created_at'])], 201, ['Cache-Control' => 'no-store']);
    }

    public function save(Request $request, NeoFeederContractRegistry $registry): JsonResponse
    {
        $input = $request->validate(['tenant_id' => 'nullable|uuid|exists:tenants,id', 'profile_id' => 'nullable|uuid',
            'expected_version' => 'nullable|integer|min:1', 'name' => 'required|string|max:120',
            'channel' => ['required', Rule::in(FileMappingService::CHANNELS)], 'rules' => 'required|array|min:1|max:100',
            'rules.*.target' => 'required|string', 'rules.*.kind' => ['required', Rule::in(['source', 'constant'])],
            'rules.*.source' => 'nullable|string|max:100', 'rules.*.constant' => 'nullable|string|max:255',
            'rules.*.transform' => ['required', Rule::in(['trim', 'date_dmy', 'excel_date', 'gender', 'reference_label', 'reference_code', 'lookup', 'concat', 'split'])],
            'rules.*.pairs' => 'sometimes|array|max:100', 'rules.*.pairs.*.from' => 'required|string|max:255', 'rules.*.pairs.*.to' => 'required|string|max:255',
            'rules.*.separator' => 'sometimes|nullable|string|max:20', 'rules.*.part' => 'sometimes|integer|min:1|max:64',
            'rules.*.append_sources' => 'sometimes|array|max:7', 'rules.*.append_sources.*' => 'required|string|max:100',
            'rules.*.overrides' => 'sometimes|array|max:100', 'rules.*.overrides.*.from' => 'required|string|max:255',
            'rules.*.overrides.*.to' => 'required|string|max:255']);
        $tenantId = $this->tenant($request);
        $fields = array_column($registry->channel($input['channel'])->fields, 'name');
        $targets = array_column($input['rules'], 'target');
        if (count(array_unique($targets)) !== count($targets) || array_diff($targets, $fields)) {
            throw ValidationException::withMessages(['rules' => 'Field tujuan harus unik dan sesuai contract kanal.']);
        }
        foreach ($input['rules'] as $rule) {
            if ($rule['kind'] === 'source' && empty($rule['source'])) {
                throw ValidationException::withMessages(['rules' => 'Pilih kolom sumber untuk setiap aturan sumber.']);
            }
            $field = collect($registry->channel($input['channel'])->fields)->firstWhere('name', $rule['target']);
            if ($rule['transform'] === 'lookup') {
                abort_if(empty($rule['pairs']), 422, 'Isi tabel padanan terlebih dahulu.');
                $keys = array_map(fn ($pair) => trim($pair['from']), $rule['pairs']);
                abort_if(count(array_unique($keys)) !== count($keys) || in_array('', $keys, true), 422, 'Nilai asal padanan harus unik dan terisi.');
            }
            if ($rule['transform'] === 'concat') {
                abort_if(empty($rule['append_sources']), 422, 'Pilih kolom tambahan untuk digabungkan.');
            }
            if ($rule['transform'] === 'split') {
                abort_if(! isset($rule['separator']) || $rule['separator'] === '' || empty($rule['part']), 422, 'Isi pemisah dan nomor bagian.');
            }
            if (str_starts_with($rule['transform'], 'reference_')) {
                abort_unless($field['reference'] ?? null, 422, 'Field ini tidak mempunyai referensi.');
                abort_if($rule['transform'] === 'reference_code' && ! isset(MappingReferenceResolver::CODE_FIELDS[$field['reference']]), 422, 'Pencocokan kode belum tersedia untuk referensi ini.');
                $seen = [];
                foreach ($rule['overrides'] ?? [] as $pair) {
                    $from = trim($pair['from']);
                    abort_if($from === '' || isset($seen[$from]), 422, 'Nilai asal padanan harus unik dan terisi.');
                    $seen[$from] = true;
                    abort_unless(ReferenceRecord::where('tenant_id', $tenantId)->where('endpoint', $field['reference'])->where('value', $pair['to'])->exists(), 422, 'ID padanan tidak ada pada referensi kampus.');
                }
            }
        }
        foreach ($input['rules'] as &$rule) {
            if ($rule['transform'] === 'concat') {
                $rule['separator'] = $rule['separator'] ?? '';
            }
        }
        unset($rule);
        $profile = DB::transaction(function () use ($input, $tenantId, $request) {
            if ($input['profile_id'] ?? null) {
                $profile = MappingProfile::query()->lockForUpdate()->findOrFail($input['profile_id']);
                abort_unless($profile->tenant_id === $tenantId, 403);
                abort_unless($profile->version === ($input['expected_version'] ?? null), 409, 'Profil telah berubah. Muat ulang sebelum menyimpan.');
                abort_unless($profile->channel === $input['channel'], 422, 'Kanal profil tidak boleh berubah. Buat profil baru.');
                $profile->update(['name' => $input['name'], 'version' => $profile->version + 1]);
            } else {
                $profile = MappingProfile::create(['tenant_id' => $tenantId, 'name' => $input['name'], 'channel' => $input['channel'], 'version' => 1]);
            }
            $profile->versions()->create(['version' => $profile->version, 'rules' => $input['rules']]);
            $this->audit($request, $tenantId, 'mapping.saved', $profile->id);

            return $profile;
        });

        return response()->json(['data' => $this->profile($profile->load('currentVersion'))], 200, ['Cache-Control' => 'no-store']);
    }

    public function preview(Request $request, MappingProfile $mappingProfile, FileMappingService $service): JsonResponse
    {
        [$source, $input] = $this->source($request, $mappingProfile);
        $filters = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'status' => ['nullable', Rule::in(['valid', 'invalid'])],
            'search' => 'nullable|string|max:100',
        ]);

        return response()->json(['data' => $service->preview($mappingProfile, $source, $input['version'],
            $filters['page'] ?? 1, $filters['per_page'] ?? 25, $filters['status'] ?? null, $filters['search'] ?? null)], 200, ['Cache-Control' => 'no-store']);
    }

    public function previewReport(Request $request, MappingProfile $mappingProfile, FileMappingService $service): StreamedResponse
    {
        [$source, $input] = $this->source($request, $mappingProfile);
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['valid', 'invalid'])],
            'search' => 'nullable|string|max:100',
        ]);
        $preview = $service->preview($mappingProfile, $source, $input['version'], 1, SourceFileReader::MAX_ROWS,
            $filters['status'] ?? null, $filters['search'] ?? null);
        $this->audit($request, $mappingProfile->tenant_id, 'mapping.preview_report.downloaded', $mappingProfile->id);

        return response()->streamDownload(function () use ($preview): void {
            $output = fopen('php://output', 'wb');
            fputcsv($output, ['Baris sumber', 'Status', 'Hasil normalisasi', 'Error', 'Peringatan']);
            foreach ($preview['rows'] as $row) {
                fputcsv($output, [
                    $row['row_number'],
                    $this->safeCsv($row['status']),
                    $this->safeCsv(json_encode($row['normalized_row'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                    $this->safeCsv(implode(' | ', array_map(fn ($issue) => ($issue['field'] ?? 'Baris').': '.($issue['message'] ?? ''), $row['validation_result']['errors'] ?? []))),
                    $this->safeCsv(implode(' | ', array_map(fn ($issue) => ($issue['field'] ?? 'Baris').': '.($issue['message'] ?? ''), $row['validation_result']['warnings'] ?? []))),
                ]);
            }
            fclose($output);
        }, 'mapping-preview-'.$mappingProfile->id.'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function stage(Request $request, MappingProfile $mappingProfile, FileMappingService $service): JsonResponse
    {
        [$source, $input] = $this->source($request, $mappingProfile);
        $hash = $request->validate(['preview_hash' => 'required|string|size:64'])['preview_hash'];
        $batch = $service->stage($mappingProfile, $source, $input['version'], $hash, $request->user());

        return response()->json(['data' => $batch->only(['id', 'status', 'summary'])], 201, ['Cache-Control' => 'no-store']);
    }

    private function source(Request $request, MappingProfile $profile): array
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->tenant_id === $profile->tenant_id, 403);
        $input = $request->validate(['source_id' => 'required|uuid', 'version' => 'required|integer|min:1']);
        $source = SourceConnection::findOrFail($input['source_id']);
        abort_unless($source->tenant_id === $profile->tenant_id, 403);

        return [$source, $input];
    }

    private function authorizeProfile(Request $request, MappingProfile $profile): void
    {
        $user = $request->user();
        abort_unless($user->isAdmin() || $user->tenant_id === $profile->tenant_id, 403);
    }

    private function tenant(Request $request): string
    {
        $user = $request->user();
        $tenantId = $request->input('tenant_id') ?: $user->tenant_id;
        abort_unless($tenantId, 422, 'Pilih kampus terlebih dahulu.');
        abort_unless($user->isAdmin() || $user->tenant_id === $tenantId, 403);

        return $tenantId;
    }

    private function profile(MappingProfile $profile): array
    {
        return [...$profile->only(['id', 'tenant_id', 'name', 'channel', 'version']), 'rules' => $profile->currentVersion->rules];
    }

    private function audit(Request $request, string $tenantId, string $event, string $subject): void
    {
        AuditLog::create(['tenant_id' => $tenantId, 'actor_id' => $request->user()->id, 'event' => $event, 'subject_id' => $subject]);
    }

    private function safeCsv(mixed $value): string
    {
        $text = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $text) === 1 ? "'".$text : $text;
    }
}
