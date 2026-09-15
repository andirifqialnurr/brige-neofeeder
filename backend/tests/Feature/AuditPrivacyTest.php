<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class AuditPrivacyTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_masking_covers_preview_and_data_without_changing_approved_input(): void
    {
        [$batch, $row, $user, $token] = $this->syncWorkspace();
        $row->update(['normalized_row' => [...$row->normalized_row, 'handphone' => '081234567890', 'npwp' => '123456789012345'],
            'raw_row' => ['nik' => '0000000000000000', 'password' => ['value' => 'DO-NOT-EXPOSE']]]);
        $url = '/api/import-batches/'.$batch->id.'/rows/'.$row->id;
        $this->withToken($token)->getJson($url)->assertOk()->assertJsonPath('data.normalized_row.nik', '************0000')
            ->assertJsonPath('data.raw_row', null)->assertJsonPath('data.can_reveal_sensitive', false)
            ->assertJsonPath('data.normalized_row.handphone', '********7890')->assertJsonPath('data.normalized_row.npwp', '***********2345');
        $this->withToken($token)->postJson($url.'/reveal', ['purpose' => 'verification'])->assertForbidden();
        $this->withToken($token)->postJson('/api/import-batches/'.$batch->id.'/dry-run')->assertOk()
            ->assertJsonPath('data.payload_preview.0.payload.record.nik', '************0000');
        $this->assertSame('0000000000000000', $row->refresh()->normalized_row['nik']);
        $user->update(['role' => 'admin']);
        $this->withToken($token)->postJson($url.'/reveal', [])->assertUnprocessable();
        $this->withToken($token)->postJson($url.'/reveal', ['purpose' => 'verification'])->assertOk()
            ->assertJsonPath('data.normalized_row.nik', '0000000000000000')->assertJsonPath('data.sensitive_revealed', true)
            ->assertJsonPath('data.raw_row.password', '[disembunyikan]');
        $log = AuditLog::where('event', 'import.row.sensitive_viewed')->firstOrFail();
        $this->assertSame('verification', $log->metadata['purpose']);
        $this->assertStringNotContainsString('0000000000000000', $log->toJson());
    }

    public function test_audit_search_export_and_pagination_remain_tenant_scoped(): void
    {
        [$batch, , $user, $token] = $this->syncWorkspace();
        [$foreign] = $this->syncWorkspace();
        $user->update(['name' => '=FORMULA()']);
        for ($i = 0; $i < 27; $i++) {
            AuditLog::create(['tenant_id' => $batch->tenant_id, 'actor_id' => $user->id, 'event' => 'import.test',
                'metadata' => ['password' => 'PRIVATE', 'payload' => ['nik' => '1234567890123456']]]);
        }
        AuditLog::create(['tenant_id' => $foreign->tenant_id, 'event' => 'foreign.secret']);
        $response = $this->withToken($token)->getJson('/api/audit-logs?page=2&tenant_id='.$foreign->tenant_id)
            ->assertOk()->assertJsonPath('meta.total', 27)->assertJsonCount(2, 'data');
        $this->assertStringNotContainsString('PRIVATE', $response->getContent());
        $this->withToken($token)->getJson('/api/audit-logs?search=foreign')->assertJsonPath('meta.total', 0);
        $csv = $this->withToken($token)->get('/api/audit-logs/export?search=import.test')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=FORMULA()", $csv);
        $this->assertStringNotContainsString('foreign', $csv);
        $this->assertStringNotContainsString('PRIVATE', $csv);
        $this->assertDatabaseHas('audit_logs', ['event' => 'audit.exported', 'tenant_id' => $batch->tenant_id]);
    }
}
