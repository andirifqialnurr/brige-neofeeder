<?php

namespace Tests\Feature;

use App\Models\ImportBatch;
use App\Models\MappingProfile;
use App\Models\ReferenceRecord;
use App\Models\SourceConnection;
use App\Services\NeoFeeder\Contracts\NeoFeederContractRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class FileMappingTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    private function fixture(): array
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$batch, $row, , $token] = $this->syncWorkspace();
        $rules = [];
        $headers = [];
        $values = [];
        foreach ($row->normalized_row as $field => $value) {
            $source = ['nama_mahasiswa' => 'Nama', 'jenis_kelamin' => 'Gender', 'tanggal_lahir' => 'Tanggal'][$field] ?? $field;
            $headers[] = $source;
            $values[] = ['jenis_kelamin' => 'Laki-laki', 'tanggal_lahir' => '01/01/2000'][$field] ?? $value;
            $rules[] = ['target' => $field, 'kind' => 'source', 'source' => $source,
                'transform' => ['jenis_kelamin' => 'gender', 'tanggal_lahir' => 'date_dmy'][$field] ?? 'trim'];
        }
        foreach (app(NeoFeederContractRegistry::class)->channel('mahasiswa_biodata')->fields as $field) {
            if (($field['reference'] ?? null) && isset($row->normalized_row[$field['name']])) {
                ReferenceRecord::firstOrCreate(['tenant_id' => $batch->tenant_id, 'endpoint' => $field['reference'],
                    'value' => (string) $row->normalized_row[$field['name']]], ['value_key' => $field['name'], 'label' => 'Referensi fiktif', 'raw_payload' => []]);
            }
        }
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, $headers, ',', '"', '');
        fputcsv($stream, $values, ',', '"', '');
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);
        $source = $this->withToken($token)->postJson('/api/mapping/sources', ['file' => UploadedFile::fake()->createWithContent('kampus.csv', $csv)])
            ->assertCreated()->assertJsonPath('data.row_count', 1)->json('data');
        $payload = ['name' => 'Mapping Mahasiswa', 'channel' => 'mahasiswa_biodata', 'rules' => $rules];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertOk()->json('data');

        return [$source, $profile, $token, $payload];
    }

    public function test_csv_mapping_preview_staging_lineage_and_repeated_stage(): void
    {
        [$source, $profile, $token] = $this->fixture();
        $body = ['source_id' => $source['id'], 'version' => 1];
        $url = '/api/mapping/profiles/'.$profile['id'];
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()
            ->assertJsonPath('data.summary.valid_rows', 1)->assertJsonPath('data.rows.0.normalized_row.jenis_kelamin', 'L')
            ->assertJsonPath('data.rows.0.normalized_row.tanggal_lahir', '2000-01-01')
            ->assertJsonPath('data.rows.0.normalized_row.nik', '************0000')->json('data');
        $body['preview_hash'] = $preview['preview_hash'];
        $id = $this->withToken($token)->postJson($url.'/stage', $body)->assertCreated()->assertJsonPath('data.status', 'validated')->json('data.id');
        $this->withToken($token)->postJson($url.'/stage', $body)->assertCreated()->assertJsonPath('data.id', $id);
        $row = ImportBatch::findOrFail($id)->stagingRecords()->firstOrFail();
        $this->assertSame('0000000000000000', $row->normalized_row['nik']);
        $this->assertSame(2, $row->source_lineage['source_row']);
        $this->assertSame('kampus.csv', $row->source_lineage['source_name']);
        $this->assertSame(1, $row->source_lineage['mapping_version']);
        $this->assertDatabaseCount('mapping_runs', 1);
        $this->assertDatabaseCount('sync_attempts', 0);
        $this->withToken($token)->postJson('/api/import-batches/'.$id.'/dry-run')->assertOk();
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_reference_mapping_requires_explicit_ambiguous_choice_and_preserves_errors(): void
    {
        [$source, $profile, $token, $payload] = $this->fixture();
        $tenant = $source['tenant_id'];
        ReferenceRecord::where('tenant_id', $tenant)->where('endpoint', 'GetAgama')->delete();
        foreach (['1', '2'] as $id) {
            ReferenceRecord::create(['tenant_id' => $tenant, 'endpoint' => 'GetAgama', 'value_key' => 'id_agama', 'value' => $id, 'label' => 'Nama sama', 'raw_payload' => []]);
        }
        $payload['rules'] = array_values(array_filter($payload['rules'], fn ($rule) => $rule['target'] !== 'id_agama'));
        $payload['rules'][] = ['target' => 'id_agama', 'kind' => 'constant', 'constant' => 'Nama sama', 'transform' => 'reference_label'];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertOk()->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];
        $body = ['source_id' => $source['id'], 'version' => 1];
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->assertJsonPath('data.summary.invalid_rows', 1)->json('data');
        $batchId = $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])->assertCreated()->assertJsonPath('data.status', 'invalid')->json('data.id');
        $this->assertContains('mapping_reference', array_column(ImportBatch::findOrFail($batchId)->stagingRecords()->first()->validation_result['errors'], 'rule'));
        $payload['rules'][count($payload['rules']) - 1]['overrides'] = [['from' => 'Nama sama', 'to' => '2']];
        $this->withToken($token)->postJson('/api/mapping/profiles', [...$payload, 'profile_id' => $profile['id'], 'expected_version' => 1])->assertOk();
        $this->withToken($token)->postJson($url.'/preview', [...$body, 'version' => 2])->assertOk()->assertJsonPath('data.rows.0.normalized_row.id_agama', '2')->assertJsonPath('data.summary.valid_rows', 1);
        $this->withToken($token)->getJson('/api/mapping/references?channel=mahasiswa_biodata&field=id_agama')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_reference_changes_invalidate_preview_and_foreign_overrides_are_rejected(): void
    {
        [$source, , $token, $payload] = $this->fixture();
        $payload['rules'] = [['target' => 'id_agama', 'kind' => 'constant', 'constant' => 'Referensi fiktif', 'transform' => 'reference_label']];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertOk()->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];
        $body = ['source_id' => $source['id'], 'version' => 1];
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->json('data');
        ReferenceRecord::where('tenant_id', $source['tenant_id'])->where('endpoint', 'GetAgama')->update(['label' => 'Berubah']);
        $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])->assertConflict();
        [$foreign, , , $otherToken] = $this->syncWorkspace();
        ReferenceRecord::create(['tenant_id' => $foreign->tenant_id, 'endpoint' => 'GetAgama', 'value_key' => 'id_agama', 'value' => '999', 'label' => 'Asing']);
        $payload['rules'][0]['overrides'] = [['from' => 'Referensi fiktif', 'to' => '999']];
        $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertUnprocessable();
        $this->withToken($otherToken)->getJson('/api/mapping/references?tenant_id='.$source['tenant_id'].'&channel=mahasiswa_biodata&field=id_agama')->assertForbidden();
    }

    public function test_profile_versions_and_preview_hash_reject_stale_input(): void
    {
        [$source, $profile, $token, $payload] = $this->fixture();
        $body = ['source_id' => $source['id'], 'version' => 1];
        $url = '/api/mapping/profiles/'.$profile['id'];
        $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => str_repeat('0', 64)])->assertConflict();
        $updated = [...$payload, 'profile_id' => $profile['id'], 'expected_version' => 1, 'name' => 'Mapping Versi 2'];
        $this->withToken($token)->postJson('/api/mapping/profiles', $updated)->assertOk()->assertJsonPath('data.version', 2);
        $this->withToken($token)->postJson('/api/mapping/profiles', $updated)->assertConflict();
        $this->withToken($token)->postJson($url.'/preview', $body)->assertConflict();
        $this->assertSame($payload['rules'], MappingProfile::findOrFail($profile['id'])->versions()->where('version', 1)->firstOrFail()->rules);
        $this->assertDatabaseCount('mapping_profile_versions', 2);
    }

    public function test_mapping_preview_can_filter_paginate_and_export_all_rows(): void
    {
        [$source, $profile, $token] = $this->fixture();
        $stored = SourceConnection::findOrFail($source['id']);
        $first = $stored->snapshot[0];
        $stored->update(['snapshot' => array_map(function (int $index) use ($first): array {
            $row = $first;
            $row['row_number'] = $index + 2;
            $row['values']['Nama'] = 'Mahasiswa '.$index;

            return $row;
        }, range(0, 29)), 'row_count' => 30]);
        $url = '/api/mapping/profiles/'.$profile['id'].'/preview';
        $base = ['source_id' => $source['id'], 'version' => 1];

        $page = $this->withToken($token)->postJson($url.'?'.http_build_query([...$base, 'page' => 2, 'per_page' => 10]), $base)
            ->assertOk()->assertJsonPath('data.summary.total_rows', 30)->assertJsonPath('data.meta.current_page', 2)
            ->assertJsonPath('data.meta.last_page', 3)->assertJsonPath('data.meta.total', 30)->assertJsonCount(10, 'data.rows')->json('data');
        $this->assertSame(12, $page['rows'][0]['row_number']);
        $this->assertSame(64, strlen($page['preview_hash']));

        $this->withToken($token)->postJson($url.'?'.http_build_query([...$base, 'status' => 'invalid', 'search' => 'Mahasiswa 7']), $base)
            ->assertOk()->assertJsonPath('data.meta.total', 1)->assertJsonPath('data.rows.0.row_number', 9)
            ->assertJsonPath('data.rows.0.status', 'invalid');

        $csv = $this->withToken($token)->get('/api/mapping/profiles/'.$profile['id'].'/preview-report?'.http_build_query([...$base, 'search' => 'Mahasiswa 7']))
            ->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Baris sumber', $csv->streamedContent());
        $this->assertStringContainsString('Mahasiswa 7', $csv->streamedContent());
        $this->assertStringNotContainsString('0000000000000000', $csv->streamedContent());
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $stored->tenant_id, 'event' => 'mapping.preview_report.downloaded', 'subject_id' => $profile['id']]);
    }

    public function test_mapping_profile_history_can_duplicate_and_restore_without_mutating_old_versions(): void
    {
        [$source, $profile, $token, $payload] = $this->fixture();
        $payload['profile_id'] = $profile['id'];
        $payload['expected_version'] = 1;
        $payload['name'] = 'Mapping versi dua';
        $payload['rules'][1]['transform'] = 'trim';
        $updated = $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertOk()->assertJsonPath('data.version', 2)->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];

        $history = $this->withToken($token)->getJson($url.'/versions')->assertOk()->assertJsonCount(2, 'data')->json('data');
        $this->assertSame(2, $history[0]['version']);
        $this->assertSame(1, $history[1]['version']);
        $this->assertNotSame($history[0]['rules'], $history[1]['rules']);

        $restored = $this->withToken($token)->postJson($url.'/restore', ['version' => 1, 'expected_version' => 2])
            ->assertOk()->assertJsonPath('data.version', 3)->json('data');
        $this->assertSame($history[1]['rules'], $restored['rules']);
        $this->assertSame($history[1]['rules'], MappingProfile::findOrFail($profile['id'])->versions()->where('version', 3)->firstOrFail()->rules);
        $this->withToken($token)->postJson($url.'/restore', ['version' => 2, 'expected_version' => 2])->assertConflict();

        $copy = $this->withToken($token)->postJson($url.'/duplicate', ['name' => 'Salinan mapping'])
            ->assertCreated()->assertJsonPath('data.name', 'Salinan mapping')->assertJsonPath('data.channel', $updated['channel'])
            ->assertJsonPath('data.version', 1)->json('data');
        $this->assertSame($restored['rules'], $copy['rules']);
        $this->assertDatabaseCount('mapping_profile_versions', 4);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $source['tenant_id'], 'event' => 'mapping.version_restored', 'subject_id' => $profile['id']]);
        $this->assertDatabaseHas('audit_logs', ['tenant_id' => $source['tenant_id'], 'event' => 'mapping.duplicated', 'subject_id' => $copy['id']]);
    }

    public function test_advanced_transforms_keep_zeroes_spaces_and_report_unmapped_values(): void
    {
        [$source, , $token, $payload] = $this->fixture();
        $file = SourceConnection::findOrFail($source['id']);
        $file->update(['headers' => ['First', 'Last', 'Code', 'Gender'], 'snapshot' => [
            ['row_number' => 2, 'values' => ['First' => 'Andi', 'Last' => 'Fiktif', 'Code' => '001 002', 'Gender' => '0']],
            ['row_number' => 3, 'values' => ['First' => 'Budi', 'Last' => '', 'Code' => '003', 'Gender' => 'X']],
        ], 'row_count' => 2]);
        $payload['rules'] = [
            ['target' => 'nama_mahasiswa', 'kind' => 'source', 'source' => 'First', 'transform' => 'concat', 'append_sources' => ['Last'], 'separator' => ' '],
            ['target' => 'jenis_kelamin', 'kind' => 'source', 'source' => 'Gender', 'transform' => 'lookup', 'pairs' => [['from' => '0', 'to' => 'L']]],
            ['target' => 'nisn', 'kind' => 'source', 'source' => 'Code', 'transform' => 'split', 'separator' => ' ', 'part' => 2],
        ];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertOk()->assertJsonPath('data.rules.0.separator', ' ')->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];
        $body = ['source_id' => $file->id, 'version' => 1];
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()
            ->assertJsonPath('data.rows.0.normalized_row.nama_mahasiswa', 'Andi Fiktif')
            ->assertJsonPath('data.rows.0.normalized_row.jenis_kelamin', 'L')
            ->assertJsonPath('data.rows.0.normalized_row.nisn', '002')->json('data');
        $this->assertCount(2, array_filter($preview['rows'][1]['validation_result']['errors'], fn ($issue) => $issue['rule'] === 'mapping_transform'));
        $id = $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])->assertCreated()->json('data.id');
        $errors = ImportBatch::findOrFail($id)->stagingRecords()->where('row_number', 3)->first()->validation_result['errors'];
        $this->assertCount(2, array_filter($errors, fn ($issue) => $issue['rule'] === 'mapping_transform'));
        $payload['rules'][1]['pairs'][] = ['from' => '0', 'to' => 'P'];
        $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertUnprocessable();
    }

    public function test_mapping_apis_deny_foreign_tenants_and_sources(): void
    {
        [$source, $profile, $token, $payload] = $this->fixture();
        [$foreign, , , $otherToken] = $this->syncWorkspace();
        $this->withToken($otherToken)->getJson('/api/mapping/workspace?tenant_id='.$source['tenant_id'])->assertForbidden();
        $this->withToken($otherToken)->postJson('/api/mapping/profiles/'.$profile['id'].'/preview', ['source_id' => $source['id'], 'version' => 1])->assertForbidden();
        $this->withToken($otherToken)->postJson('/api/mapping/profiles', [...$payload, 'profile_id' => $profile['id'], 'expected_version' => 1])->assertForbidden();
        $foreignSource = SourceConnection::create(['tenant_id' => $foreign->tenant_id, 'name' => 'other.csv', 'sha256' => str_repeat('1', 64), 'headers' => ['Nama'], 'snapshot' => [], 'row_count' => 0]);
        $this->withToken($token)->postJson('/api/mapping/profiles/'.$profile['id'].'/preview', ['source_id' => $foreignSource->id, 'version' => 1])->assertForbidden();
        $this->withToken($otherToken)->postJson('/api/mapping/sources', ['tenant_id' => $source['tenant_id'], 'file' => UploadedFile::fake()->createWithContent('foreign.csv', "Nama\nFiktif\n")])->assertForbidden();
    }

    public function test_xlsx_input_rejects_formulas_duplicate_headers_and_silent_truncation(): void
    {
        [, , , $token] = $this->syncWorkspace();
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->fromArray([['Nama', 'NIK'], ['Fiktif', null]]);
        $sheet->setCellValueExplicit('B2', '0000000000000001', DataType::TYPE_STRING);
        $path = tempnam(sys_get_temp_dir(), 'mapping').'.xlsx';
        try {
            (new Xlsx($book))->save($path);
            $source = $this->withToken($token)->postJson('/api/mapping/sources', ['file' => new UploadedFile($path, 'source.xlsx', null, null, true)])
                ->assertCreated()->json('data');
            $this->assertSame('0000000000000001', SourceConnection::findOrFail($source['id'])->snapshot[0]['values']['NIK']);
            $sheet->setCellValue('A2', '=1+1');
            (new Xlsx($book))->save($path);
            $this->withToken($token)->postJson('/api/mapping/sources', ['file' => new UploadedFile($path, 'source.xlsx', null, null, true)])->assertUnprocessable();
            $sheet->setCellValue('A2', 'Fiktif');
            $sheet->setCellValue('A5000', 'Must not be silently omitted');
            (new Xlsx($book))->save($path);
            $this->withToken($token)->postJson('/api/mapping/sources', ['file' => new UploadedFile($path, 'source.xlsx', null, null, true)])->assertUnprocessable();
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
        $this->withToken($token)->postJson('/api/mapping/sources', ['file' => UploadedFile::fake()->createWithContent('duplicate.csv', "Nama,Nama\nA,B\n")])->assertUnprocessable();
        $this->withToken($token)->postJson('/api/mapping/sources', ['file' => UploadedFile::fake()->createWithContent('large.csv', "Nama\n".str_repeat("Fiktif\n", 2001))])->assertUnprocessable();
    }

    public function test_education_mapping_supports_constants_and_rejects_impossible_dates(): void
    {
        [$batch, , , $token] = $this->syncWorkspace();
        $uuid = '00000000-0000-4000-8000-000000000001';
        $constants = ['id_mahasiswa' => $uuid, 'id_jenis_daftar' => '1', 'id_periode_masuk' => '20261',
            'id_perguruan_tinggi' => $uuid, 'id_prodi' => $uuid, 'biaya_masuk' => '0'];
        foreach (app(NeoFeederContractRegistry::class)->channel('mahasiswa_riwayat_pendidikan')->fields as $field) {
            if (($field['reference'] ?? null) && isset($constants[$field['name']])) {
                ReferenceRecord::create(['tenant_id' => $batch->tenant_id, 'endpoint' => $field['reference'],
                    'value_key' => $field['name'], 'value' => $constants[$field['name']], 'label' => 'Fiktif', 'raw_payload' => []]);
            }
        }
        $source = $this->withToken($token)->postJson('/api/mapping/sources', ['file' => UploadedFile::fake()->createWithContent('riwayat.csv', "NIM,Daftar\n0001,01/08/2026\n0002,31/02/2026\n")])->assertCreated()->json('data');
        $rules = collect($constants)->map(fn ($value, $field) => ['target' => $field, 'kind' => 'constant', 'constant' => $value, 'transform' => 'trim'])->values()->all();
        $rules[] = ['target' => 'nim', 'kind' => 'source', 'source' => 'NIM', 'transform' => 'trim'];
        $rules[] = ['target' => 'tanggal_daftar', 'kind' => 'source', 'source' => 'Daftar', 'transform' => 'date_dmy'];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', ['name' => 'Riwayat', 'channel' => 'mahasiswa_riwayat_pendidikan', 'rules' => $rules])->assertOk()->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];
        $body = ['source_id' => $source['id'], 'version' => 1];
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()
            ->assertJsonPath('data.summary.valid_rows', 1)->assertJsonPath('data.summary.invalid_rows', 1)
            ->assertJsonPath('data.rows.0.normalized_row.nim', '0001')->assertJsonPath('data.rows.0.normalized_row.biaya_masuk', '0')->json('data');
        $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])
            ->assertCreated()->assertJsonPath('data.status', 'invalid')->assertJsonPath('data.summary.invalid_rows', 1);
    }
}
