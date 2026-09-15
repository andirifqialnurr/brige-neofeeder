<?php

namespace Tests\Feature;

use App\Models\ReferenceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class AcademicMappingTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    private function workspace(): array
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$batch, , , $token] = $this->syncWorkspace();
        $prodi = '00000000-0000-4000-8000-000000000001';
        ReferenceRecord::create(['tenant_id' => $batch->tenant_id, 'endpoint' => 'GetProdi', 'value_key' => 'id_prodi', 'value' => $prodi, 'label' => 'Prodi Demo', 'raw_payload' => ['kode_program_studi' => '55201']]);
        ReferenceRecord::create(['tenant_id' => $batch->tenant_id, 'endpoint' => 'GetSemester', 'value_key' => 'id_semester', 'value' => '20261', 'label' => 'Semester Demo']);

        return [$batch->tenant_id, $token, $prodi];
    }

    private function source(string $token, string $csv): string
    {
        return $this->withToken($token)->postJson('/api/mapping/sources', ['file' => UploadedFile::fake()->createWithContent('academic.csv', $csv)])->assertCreated()->json('data.id');
    }

    private function constant(string $field, string $value, string $transform = 'trim'): array
    {
        return ['target' => $field, 'kind' => 'constant', 'constant' => $value, 'transform' => $transform];
    }

    public function test_course_mapping_resolves_program_code_preserves_course_code_and_previews_insert_update(): void
    {
        [, $token, $prodi] = $this->workspace();
        $source = $this->source($token, "Kode,Nama,SKS\n0001,Mata Kuliah Fiktif,3\n");
        $rules = [$this->constant('id_prodi', '55201', 'reference_code')];
        foreach (['kode_mata_kuliah' => 'Kode', 'nama_mata_kuliah' => 'Nama', 'sks_mata_kuliah' => 'SKS'] as $target => $header) {
            $rules[] = ['target' => $target, 'kind' => 'source', 'source' => $header, 'transform' => 'trim'];
        }
        $payload = ['name' => 'Mata Kuliah', 'channel' => 'mata_kuliah', 'rules' => $rules];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', $payload)->assertOk()->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];
        $body = ['source_id' => $source, 'version' => 1];
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->assertJsonPath('data.summary.valid_rows', 1)->assertJsonPath('data.rows.0.normalized_row.kode_mata_kuliah', '0001')->assertJsonPath('data.rows.0.normalized_row.id_prodi', $prodi)->json('data');
        $batch = $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/import-batches/'.$batch.'/dry-run')->assertOk()->assertJsonPath('data.payload_preview.0.action', 'InsertMataKuliah');
        $payload['rules'][] = $this->constant('id_matkul', '00000000-0000-4000-8000-000000000002');
        $this->withToken($token)->postJson('/api/mapping/profiles', [...$payload, 'profile_id' => $profile['id'], 'expected_version' => 1])->assertOk();
        $body['version'] = 2;
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->json('data');
        $batch = $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/import-batches/'.$batch.'/dry-run')->assertOk()->assertJsonPath('data.payload_preview.0.action', 'UpdateMataKuliah');
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_class_mapping_requires_course_reference_and_rejects_foreign_program(): void
    {
        [$tenant, $token, $prodi] = $this->workspace();
        $source = $this->source($token, "Kode\n0001\n");
        $rules = [$this->constant('id_prodi', $prodi), $this->constant('id_semester', '20261'),
            $this->constant('nama_kelas_kuliah', 'A'), $this->constant('apa_untuk_pditt', '0'),
            ['target' => 'id_matkul', 'kind' => 'source', 'source' => 'Kode', 'transform' => 'reference_code']];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', ['name' => 'Kelas', 'channel' => 'kelas_kuliah', 'rules' => $rules])->assertOk()->json('data');
        $url = '/api/mapping/profiles/'.$profile['id'];
        $body = ['source_id' => $source, 'version' => 1];
        $before = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->assertJsonPath('data.summary.invalid_rows', 1)->json('data');
        $course = ReferenceRecord::create(['tenant_id' => $tenant, 'endpoint' => 'GetListMataKuliah', 'value_key' => 'id_matkul', 'value' => '00000000-0000-4000-8000-000000000002', 'label' => 'Mata Kuliah Demo', 'raw_payload' => ['kode_mata_kuliah' => '0001', 'id_prodi' => 'other-program']]);
        $bad = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->assertJsonPath('data.summary.invalid_rows', 1)->json('data');
        $this->assertContains('course_program', array_column($bad['rows'][0]['validation_result']['errors'], 'rule'));
        $course->update(['raw_payload' => ['kode_mata_kuliah' => '0001', 'id_prodi' => $prodi]]);
        $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $before['preview_hash']])->assertConflict();
        $preview = $this->withToken($token)->postJson($url.'/preview', $body)->assertOk()->assertJsonPath('data.summary.valid_rows', 1)->json('data');
        $batch = $this->withToken($token)->postJson($url.'/stage', [...$body, 'preview_hash' => $preview['preview_hash']])->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/import-batches/'.$batch.'/dry-run')->assertOk()->assertJsonPath('data.payload_preview.0.action', 'InsertKelasKuliah');
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_course_mapping_rejects_invalid_credit_and_duplicate_course_keys(): void
    {
        [, $token, $prodi] = $this->workspace();
        $source = $this->source($token, "Kode,SKS\n001,-1\n002,3\n002,3\n");
        $rules = [$this->constant('id_prodi', $prodi), $this->constant('nama_mata_kuliah', 'Fiktif'),
            ['target' => 'kode_mata_kuliah', 'kind' => 'source', 'source' => 'Kode', 'transform' => 'trim'],
            ['target' => 'sks_mata_kuliah', 'kind' => 'source', 'source' => 'SKS', 'transform' => 'trim']];
        $id = $this->withToken($token)->postJson('/api/mapping/profiles', ['name' => 'Invalid MK', 'channel' => 'mata_kuliah', 'rules' => $rules])->assertOk()->json('data.id');
        $this->withToken($token)->postJson('/api/mapping/profiles/'.$id.'/preview', ['source_id' => $source, 'version' => 1])->assertOk()->assertJsonPath('data.summary.invalid_rows', 3);
    }

    public function test_participant_and_grade_mapping_validate_dependencies_and_build_expected_actions(): void
    {
        [$tenant, $token] = $this->workspace();
        $class = '00000000-0000-4000-8000-000000000010';
        $registration = '00000000-0000-4000-8000-000000000011';
        foreach ([
            ['endpoint' => 'GetListKelasKuliah', 'value_key' => 'id_kelas_kuliah', 'value' => $class, 'label' => 'Kelas A'],
            ['endpoint' => 'GetListRiwayatPendidikanMahasiswa', 'value_key' => 'id_registrasi_mahasiswa', 'value' => $registration, 'label' => 'Registrasi A'],
        ] as $reference) {
            ReferenceRecord::create(['tenant_id' => $tenant, ...$reference, 'raw_payload' => []]);
        }
        $source = $this->source($token, "Kelas,Registrasi\n{$class},{$registration}\n");
        $rules = [
            ['target' => 'id_kelas_kuliah', 'kind' => 'source', 'source' => 'Kelas', 'transform' => 'trim'],
            ['target' => 'id_registrasi_mahasiswa', 'kind' => 'source', 'source' => 'Registrasi', 'transform' => 'trim'],
        ];
        $profile = $this->withToken($token)->postJson('/api/mapping/profiles', ['name' => 'Peserta', 'channel' => 'peserta_kelas', 'rules' => $rules])->assertOk()->json('data');
        $preview = $this->withToken($token)->postJson('/api/mapping/profiles/'.$profile['id'].'/preview', ['source_id' => $source, 'version' => 1])
            ->assertOk()->assertJsonPath('data.summary.valid_rows', 1)->json('data');
        $batch = $this->withToken($token)->postJson('/api/mapping/profiles/'.$profile['id'].'/stage', ['source_id' => $source, 'version' => 1, 'preview_hash' => $preview['preview_hash']])
            ->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/import-batches/'.$batch.'/dry-run')->assertOk()->assertJsonPath('data.payload_preview.0.action', 'InsertPesertaKelasKuliah');

        $gradeSource = $this->source($token, "Kelas,Registrasi,Angka\n{$class},{$registration},95.5\n");
        $gradeRules = [...$rules, ['target' => 'nilai_angka', 'kind' => 'source', 'source' => 'Angka', 'transform' => 'trim']];
        $grade = $this->withToken($token)->postJson('/api/mapping/profiles', ['name' => 'Nilai', 'channel' => 'nilai_perkuliahan', 'rules' => $gradeRules])->assertOk()->json('data');
        $gradePreview = $this->withToken($token)->postJson('/api/mapping/profiles/'.$grade['id'].'/preview', ['source_id' => $gradeSource, 'version' => 1])
            ->assertOk()->assertJsonPath('data.summary.valid_rows', 1)->json('data');
        $gradeBatch = $this->withToken($token)->postJson('/api/mapping/profiles/'.$grade['id'].'/stage', ['source_id' => $gradeSource, 'version' => 1, 'preview_hash' => $gradePreview['preview_hash']])
            ->assertCreated()->json('data.id');
        $this->withToken($token)->postJson('/api/import-batches/'.$gradeBatch.'/dry-run')->assertOk()->assertJsonPath('data.payload_preview.0.action', 'UpdateNilaiPerkuliahanKelas');

        $invalidSource = $this->source($token, "Kelas,Registrasi,Angka\n{$class},{$registration},101\n");
        $this->withToken($token)->postJson('/api/mapping/profiles/'.$grade['id'].'/preview', ['source_id' => $invalidSource, 'version' => 1])
            ->assertOk()->assertJsonPath('data.summary.invalid_rows', 1)->assertJsonPath('data.rows.0.validation_result.errors.0.rule', 'grade_range');
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }
}
