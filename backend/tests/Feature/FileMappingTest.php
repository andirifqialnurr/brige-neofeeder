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
