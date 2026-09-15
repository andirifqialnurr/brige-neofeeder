<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use App\Models\MappingProfile;
use App\Models\SourceConnection;
use App\Services\Mapping\FileMappingService;
use App\Services\Mapping\SourceFileReader;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class FileMappingController
{
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
            'rules.*.transform' => ['required', Rule::in(['trim', 'date_dmy', 'excel_date', 'gender'])]]);
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
        }
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

        return response()->json(['data' => $service->preview($mappingProfile, $source, $input['version'])], 200, ['Cache-Control' => 'no-store']);
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
}
