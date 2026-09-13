<?php

namespace Database\Seeders;

use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\ReferenceRecord;
use App\Models\SyncAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Imports\ImportWorkbookParser;
use App\Services\Sync\ImportBatchApprovalService;
use App\Services\Templates\NeoFeederTemplateWorkbookService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use RuntimeException;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('demo.password');
        if (! is_string($password) || strlen($password) < 12 || (app()->isProduction() && ! config('demo.allow_production'))) {
            throw new RuntimeException('Run php artisan bridge:seed-demo; demo seeding requires explicit opt-in and a password.');
        }
        DB::transaction(function () use ($password): void {
            foreach (['DEMO01' => 'Kampus Demo Nusantara', 'DEMO02' => 'Kampus Demo Kosong'] as $code => $name) {
                $tenant = Tenant::where('code', $code)->first();
                if ($tenant && ($tenant->metadata['demo'] ?? false) !== true) {
                    throw new RuntimeException("Reserved demo code {$code} belongs to an existing tenant. No data was changed.");
                }
                if (! $tenant) {
                    $tenant = Tenant::create(['name' => $name, 'code' => $code, 'status' => 'active', 'metadata' => ['demo' => true, 'seed_version' => 1]]);
                }
                $email = $code === 'DEMO01' ? 'demo-operator@example.test' : 'demo-empty@example.test';
                $existing = User::where('email', $email)->first();
                if ($existing && ($existing->tenant_id !== $tenant->id || $existing->role !== 'operator')) {
                    throw new RuntimeException('Demo email already belongs to a different account.');
                }
                if (! $existing) {
                    User::create(['tenant_id' => $tenant->id, 'name' => $code === 'DEMO01' ? 'Operator Demo' : 'Operator Demo Kosong', 'email' => $email, 'password' => $password, 'role' => 'operator', 'status' => 'active']);
                }
                if ($code === 'DEMO02' || ($tenant->metadata['seed_complete'] ?? false)) {
                    if ($code === 'DEMO01') {
                        $this->syncHistory($tenant);
                    }

                    continue;
                }
                NeoFeederConnection::create(['tenant_id' => $tenant->id, 'base_url' => 'https://neofeeder.example.invalid/ws/live2.php', 'status' => 'inactive', 'metadata' => ['demo' => true]]);
                $rows = $this->contractRows($tenant);
                $this->workbook($tenant, 'demo-valid.xlsx', $rows);
                $mixed = $rows;
                $biodata = $rows['mahasiswa_biodata'][0];
                $mixed['mahasiswa_biodata'][] = [...$biodata, 'nama_mahasiswa' => '', 'nik' => '0000000000000003'];
                $mixed['mahasiswa_biodata'][] = $biodata;
                $mixed['mahasiswa_biodata'][] = [...$biodata, 'nik' => '0000000000000004', 'tanggal_lahir' => '01/01/2001', 'jenis_kelamin' => 'X', 'id_agama' => '99999'];
                foreach (['98', '99'] as $value) {
                    $this->reference($tenant, 'GetWilayah', 'id_wilayah', $value, 'Wilayah Demo Ambigu');
                }
                $mixed['mahasiswa_biodata'][] = [...$biodata, 'nik' => '0000000000000005', 'id_wilayah' => 'Wilayah Demo Ambigu'];
                $this->workbook($tenant, 'demo-perbaikan.xlsx', $mixed);
                ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'status' => 'failed', 'summary' => ['demo' => true, 'original_name' => 'demo-file-rusak.xlsx', 'error' => 'Contoh simulasi: workbook tidak dapat dibaca.', 'total_rows' => 0]]);
                $tenant->update(['metadata' => [...$tenant->metadata, 'seed_complete' => true]]);
                $this->syncHistory($tenant);
            }
        });
    }

    private function syncHistory(Tenant $tenant): void
    {
        if (ImportBatch::where('tenant_id', $tenant->id)->where('summary->demo_delivery_v1', true)->exists()) {
            return;
        }
        $source = ImportBatch::where('tenant_id', $tenant->id)->where('summary->original_name', 'demo-valid.xlsx')->firstOrFail();
        $prototype = $source->stagingRecords()->where('channel', 'mahasiswa_biodata')->orderBy('row_number')->firstOrFail();
        $batch = ImportBatch::create([
            'tenant_id' => $tenant->id, 'source_type' => 'excel', 'status' => 'failed',
            'template_version' => $source->template_version,
            'summary' => ['demo' => true, 'demo_delivery_v1' => true, 'original_name' => 'demo-pengiriman.xlsx', 'total_rows' => 3, 'valid_rows' => 3, 'invalid_rows' => 0, 'warning_rows' => 0],
        ]);
        $rows = collect();
        foreach (['success', 'failed', 'unknown'] as $index => $status) {
            $row = $prototype->replicate();
            $data = [...$prototype->normalized_row, 'nama_mahasiswa' => 'Mahasiswa Riwayat Demo '.($index + 1), 'nik' => str_pad((string) ($index + 10), 16, '0', STR_PAD_LEFT)];
            $row->fill(['import_batch_id' => $batch->id, 'row_number' => $index + 2, 'status' => $status === 'success' ? 'success' : 'failed', 'normalized_row' => $data, 'raw_row' => $data])->save();
            $rows->push([$row, $status]);
        }
        $hash = app(ImportBatchApprovalService::class)->fingerprint($batch);
        $batch->forceFill(['created_at' => now()->subMinutes(6), 'dry_run_hash' => $hash, 'approved_hash' => $hash, 'approved_by' => User::where('tenant_id', $tenant->id)->where('email', 'demo-operator@example.test')->value('id'), 'approved_at' => now()->subMinutes(5), 'sync_started_at' => now()->subMinutes(4)])->save();
        foreach ($rows as [$row, $status]) {
            SyncAttempt::create([
                'tenant_id' => $tenant->id, 'staging_record_id' => $row->id, 'action' => 'InsertBiodataMahasiswa',
                'status' => $status, 'approval_hash' => $hash, 'idempotency_key' => hash('sha256', $hash.':'.$row->id),
                'request_payload' => ['act' => 'InsertBiodataMahasiswa', 'record' => $row->normalized_row],
                'response_payload' => ['demo' => true], 'retry_safe' => $status === 'failed',
                'error_code' => $status === 'success' ? '0' : ($status === 'unknown' ? 'delivery_unknown' : 'demo_rejected'),
                'error_desc' => $status === 'success' ? null : ($status === 'unknown' ? 'Simulasi: respons terputus setelah POST; periksa hasil di Neo Feeder.' : 'Simulasi: record ditolak Neo Feeder.'),
                'identity_payload' => $status === 'success' ? ['id_mahasiswa' => '00000000-0000-4000-8000-000000000010'] : null,
                'attempted_at' => now()->subMinutes(4), 'request_started_at' => now()->subMinutes(4), 'completed_at' => now()->subMinutes(3), 'execution_count' => 1,
            ])->forceFill(['created_at' => now()->subMinutes(4)])->save();
        }
    }

    private function contractRows(Tenant $tenant): array
    {
        $rows = [];
        foreach (config('neofeeder-contracts.channels') as $key => $channel) {
            if ($key === 'references') {
                continue;
            }
            $row = [];
            foreach ($channel['fields'] as $field) {
                if (! ($field['required'] ?? false)) {
                    $row[$field['name']] = null;

                    continue;
                }
                $value = match ($field['type']) {
                    'date' => '2001-01-01',
                    'uuid' => '00000000-0000-4000-8000-000000000001',
                    'boolean01' => '0',
                    'integer', 'numeric', 'double' => '1',
                    default => '1',
                };
                foreach ($field['rules'] ?? [] as $rule) {
                    if (is_string($rule) && str_starts_with($rule, 'enum:')) {
                        $value = explode(',', substr($rule, 5))[0];
                    }
                }
                $value = match ($field['name']) {
                    'nik' => '0000000000000001', 'nim' => 'DEMO0001',
                    'nama_mahasiswa' => 'Mahasiswa Demo Satu', 'nama_ibu_kandung' => 'Ibu Demo',
                    'tempat_lahir', 'kelurahan' => 'Kota Demo', 'kewarganegaraan' => 'ID',
                    'nama_mata_kuliah' => 'Pengantar Data Demo', 'kode_mata_kuliah' => 'DEMO101',
                    'nama_kurikulum' => 'Kurikulum Demo', 'nama_kelas_kuliah' => 'DM-A',
                    default => $value,
                };
                $row[$field['name']] = $value;
                if (isset($field['reference']) && str_starts_with($field['reference'], 'Get')) {
                    $this->reference($tenant, $field['reference'], $field['name'], $value, 'Referensi Demo '.$value);
                }
            }
            if ($key === 'nilai_perkuliahan') {
                $row = [...$row, 'nilai_angka' => '85', 'nilai_indeks' => '4', 'nilai_huruf' => 'A'];
            }
            $rows[$key] = [$row];
        }
        $rows['mahasiswa_biodata'][] = [...$rows['mahasiswa_biodata'][0], 'id_mahasiswa' => '00000000-0000-4000-8000-000000000002', 'nik' => '0000000000000002', 'nama_mahasiswa' => 'Mahasiswa Demo Update'];

        return $rows;
    }

    private function reference(Tenant $tenant, string $endpoint, string $key, string $value, string $label): void
    {
        ReferenceRecord::firstOrCreate(['tenant_id' => $tenant->id, 'endpoint' => $endpoint, 'value' => $value], ['value_key' => $key, 'label' => $label, 'raw_payload' => ['demo' => true, $key => $value], 'synced_at' => now()]);
    }

    private function workbook(Tenant $tenant, string $filename, array $rows): void
    {
        $workbook = app(NeoFeederTemplateWorkbookService::class)->generate();
        try {
            foreach ($rows as $key => $records) {
                $sheet = $workbook->getSheetByName(config("neofeeder-contracts.channels.{$key}.sheet_name"));
                foreach ($records as $index => $row) {
                    foreach (array_values($row) as $column => $value) {
                        if ($value !== null) {
                            $sheet->setCellValueExplicit([$column + 1, $index + 2], (string) $value, DataType::TYPE_STRING);
                        }
                    }
                }
            }
            $directory = 'demo-v1/'.$tenant->id;
            Storage::disk('uploads')->makeDirectory($directory);
            $path = $directory.'/'.$filename;
            (new Xlsx($workbook))->save(Storage::disk('uploads')->path($path));
            $batch = ImportBatch::create(['tenant_id' => $tenant->id, 'source_type' => 'excel', 'file_path' => $path, 'template_version' => NeoFeederTemplateWorkbookService::TEMPLATE_VERSION, 'status' => 'uploaded', 'summary' => ['demo' => true, 'original_name' => $filename, 'size' => Storage::disk('uploads')->size($path)]]);
            app(ImportWorkbookParser::class)->parse($batch);
        } finally {
            $workbook->disconnectWorksheets();
        }
    }
}
