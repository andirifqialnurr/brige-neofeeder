<?php

namespace Tests\Feature;

use App\Models\ApiAccessToken;
use App\Models\ImportBatch;
use App\Models\ReferenceRecord;
use App\Models\StagingRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Templates\NeoFeederTemplateWorkbookService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportBatchUploadTest extends TestCase
{
    use RefreshDatabase;

    public function test_operator_can_upload_template_and_stage_rows(): void
    {
        Storage::fake('uploads');

        [$tenant, $plainToken] = $this->tenantOperatorToken();
        $this->seedBiodataReferences($tenant->id);
        $path = $this->workbookPath();

        $this
            ->withToken($plainToken)
            ->postJson('/api/import-batches/upload', [
                'file' => new UploadedFile(
                    $path,
                    'template.xlsx',
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                    null,
                    true,
                ),
            ])
            ->assertCreated()
            ->assertJsonPath('data.tenant_id', $tenant->id)
            ->assertJsonPath('data.file_exists', true);

        $batch = ImportBatch::query()->firstOrFail();

        $this->assertSame('validated', $batch->status);
        $this->assertSame(1, $batch->summary['total_rows']);
        $this->assertSame([], $batch->summary['missing_sheets']);
        $this->assertSame(1, $batch->summary['valid_rows']);

        $record = StagingRecord::query()->firstOrFail();

        $this->assertSame('mahasiswa_biodata', $record->channel);
        $this->assertSame(2, $record->row_number);
        $this->assertSame('valid', $record->status);
        $this->assertSame('3201010101010001', $record->normalized_row['nik']);
        $this->assertArrayHasKey('pending', array_flip($batch->summary['available_row_statuses']));
    }

    public function test_upload_rejects_non_excel_files(): void
    {
        [$tenant, $plainToken] = $this->tenantOperatorToken();

        $this
            ->withToken($plainToken)
            ->postJson('/api/import-batches/upload', [
                'tenant_id' => $tenant->id,
                'file' => UploadedFile::fake()->create('template.txt', 1, 'text/plain'),
            ])
            ->assertUnprocessable();
    }

    /**
     * @return array{0: Tenant, 1: string}
     */
    private function tenantOperatorToken(): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Kampus Contoh',
            'code' => 'KMP',
            'status' => 'active',
        ]);

        $user = User::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Operator',
            'email' => fake()->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => 'operator',
            'status' => 'active',
        ]);

        $plainToken = 'import-upload-token-'.str()->random(8);

        ApiAccessToken::query()->create([
            'user_id' => $user->id,
            'name' => 'test',
            'token_hash' => hash('sha256', $plainToken),
        ]);

        return [$tenant, $plainToken];
    }

    private function workbookPath(): string
    {
        $workbook = app(NeoFeederTemplateWorkbookService::class)->generate();
        $sheet = $workbook->getSheetByName('mahasiswa_biodata');
        $sheet->setCellValue('B2', 'Mahasiswa Contoh');
        $sheet->setCellValue('C2', 'L');
        $sheet->setCellValue('D2', 'Batam');
        $sheet->setCellValue('E2', '2000-01-01');
        $sheet->setCellValue('F2', 1);
        $sheet->setCellValue('G2', '3201010101010001');
        $sheet->setCellValue('J2', 'ID');
        $sheet->setCellValue('O2', 'Batam Kota');
        $sheet->setCellValue('Q2', '016000');
        $sheet->setCellValue('W2', 0);
        $sheet->setCellValue('AF2', 'Ibu Contoh');
        $sheet->setCellValue('AP2', 0);
        $sheet->setCellValue('AQ2', 0);
        $sheet->setCellValue('AR2', 0);

        $path = tempnam(sys_get_temp_dir(), 'bridge-neofeeder-template-').'.xlsx';
        (new Xlsx($workbook))->save($path);

        return $path;
    }

    private function seedBiodataReferences(string $tenantId): void
    {
        foreach ([
            ['GetAgama', 'id_agama', '1', 'Islam'],
            ['GetNegara', 'id_negara', 'ID', 'Indonesia'],
            ['GetWilayah', 'id_wilayah', '016000', 'Kota Batam'],
        ] as [$endpoint, $valueKey, $value, $label]) {
            ReferenceRecord::query()->create([
                'tenant_id' => $tenantId,
                'endpoint' => $endpoint,
                'value_key' => $valueKey,
                'value' => $value,
                'label' => $label,
                'raw_payload' => [],
                'synced_at' => now(),
            ]);
        }
    }
}
