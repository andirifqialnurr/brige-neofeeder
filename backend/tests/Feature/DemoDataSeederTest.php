<?php

namespace Tests\Feature;

use App\Jobs\RefreshNeoFeederReferenceJob;
use App\Models\ImportBatch;
use App\Models\NeoFeederConnection;
use App\Models\StagingRecord;
use App\Models\SyncAttempt;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Sync\NeoFeederRecordSyncService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_is_idempotent_covers_channels_and_uses_real_parser(): void
    {
        Storage::fake('uploads');
        Http::preventStrayRequests();
        Queue::fake();
        config(['demo.password' => 'Local-demo-test-123']);
        $this->seed(DemoDataSeeder::class);
        $count = StagingRecord::count();
        $user = User::where('email', 'demo-operator@example.test')->firstOrFail();
        $password = $user->password;
        config(['demo.password' => 'A-different-password-123']);
        $this->seed(DemoDataSeeder::class);
        $this->assertSame($password, $user->refresh()->password);
        $this->assertSame($count, StagingRecord::count());
        $this->assertDatabaseCount('tenants', 2);
        $this->assertDatabaseCount('users', 2);
        $this->assertDatabaseCount('import_batches', 3);
        $this->assertSame(11, StagingRecord::distinct()->count('channel'));
        $valid = ImportBatch::where('summary->original_name', 'demo-valid.xlsx')->firstOrFail();
        $this->assertSame('validated', $valid->status, json_encode($valid->stagingRecords->pluck('validation_result')->all()));
        $mixed = ImportBatch::where('summary->original_name', 'demo-perbaikan.xlsx')->firstOrFail();
        $this->assertSame('invalid', $mixed->status);
        $this->assertGreaterThan(0, $mixed->summary['warning_rows']);
        $rules = $mixed->stagingRecords->flatMap(fn ($row) => $row->validation_result['errors'])->pluck('rule');
        foreach (['required', 'duplicate_row', 'date_format', 'enum', 'reference_exists'] as $rule) {
            $this->assertContains($rule, $rules);
        }
        $token = $this->postJson('/api/auth/login', ['email' => $user->email, 'password' => 'Local-demo-test-123'])->assertOk()->json('access_token');
        $this->withToken($token)->getJson('/api/auth/me')->assertOk();
        $this->withToken($token)->postJson("/api/import-batches/{$valid->id}/dry-run")->assertOk()->assertJsonPath('data.summary.invalid_rows', 0);
        $row = $valid->stagingRecords()->where('normalized_row->nama_mahasiswa', 'Mahasiswa Demo Update')->firstOrFail();
        $this->withToken($token)->getJson("/api/import-batches/{$valid->id}/rows/{$row->id}")->assertOk()->assertJsonPath('data.candidate_operation', 'update');
        $this->withToken($token)->postJson("/api/import-batches/{$valid->id}/sync")->assertConflict();
        $this->withToken($token)->postJson('/api/references/sync', ['tenant_id' => $user->tenant_id])->assertConflict();
        $connection = NeoFeederConnection::firstOrFail();
        $this->withToken($token)->postJson("/api/neofeeder-connections/{$connection->id}/test")->assertConflict();
        $attempt = SyncAttempt::create(['tenant_id' => $user->tenant_id, 'staging_record_id' => $row->id, 'action' => 'UpdateBiodataMahasiswa', 'status' => 'failed']);
        $this->withToken($token)->postJson("/api/sync-attempts/{$attempt->id}/retry")->assertConflict();
        $connection->update(['status' => 'active']);
        foreach ([
            fn () => app(NeoFeederRecordSyncService::class)->sync($attempt),
            fn () => app()->call([new RefreshNeoFeederReferenceJob($user->tenant_id, 'GetProdi'), 'handle']),
        ] as $outbound) {
            try {
                $outbound();
                $this->fail('Demo worker must refuse outbound integration.');
            } catch (HttpException $exception) {
                $this->assertSame(409, $exception->getStatusCode());
            }
        }
        $this->withToken($token)->get("/api/import-batches/{$mixed->id}/report")->assertOk();
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }

    public function test_production_seeding_requires_explicit_opt_in(): void
    {
        config(['demo.password' => 'Local-demo-test-123']);
        $this->app->instance('env', 'production');
        $this->expectException(\RuntimeException::class);
        try {
            app(DemoDataSeeder::class)->run();
        } finally {
            $this->assertDatabaseCount('tenants', 0);
        }
    }

    public function test_seed_never_overwrites_an_existing_campus(): void
    {
        config(['demo.password' => 'Local-demo-test-123']);
        Tenant::create(['name' => 'Existing', 'code' => 'DEMO01', 'status' => 'active']);
        $this->expectException(\RuntimeException::class);
        try {
            $this->seed(DemoDataSeeder::class);
        } finally {
            $this->assertDatabaseHas('tenants', ['name' => 'Existing']);
            $this->assertDatabaseCount('users', 0);
        }
    }
}
