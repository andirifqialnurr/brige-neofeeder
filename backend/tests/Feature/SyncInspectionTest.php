<?php

namespace Tests\Feature;

use App\Models\SyncAttempt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class SyncInspectionTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_history_is_paginated_filtered_tenant_scoped_and_hides_raw_payloads(): void
    {
        Queue::fake();
        Http::preventStrayRequests();
        [$batch, $row, , $token] = $this->syncWorkspace();
        [, , , $other] = $this->syncWorkspace();
        $url = "/api/import-batches/{$batch->id}/sync-attempts";
        for ($i = 0; $i < 26; $i++) {
            SyncAttempt::create([
                'tenant_id' => $batch->tenant_id, 'staging_record_id' => $row->id,
                'action' => 'InsertBiodataMahasiswa', 'status' => $i === 25 ? 'unknown' : 'failed',
                'request_payload' => ['token' => 'PRIVATE-REQUEST', 'nik' => 'PRIVATE-NIK'],
                'response_payload' => ['secret' => 'PRIVATE-RESPONSE'],
            ]);
        }
        $this->getJson($url)->assertUnauthorized();
        $this->withToken($other)->getJson($url)->assertForbidden();
        $response = $this->withToken($token)->getJson($url)->assertOk()
            ->assertJsonCount(25, 'data')->assertJsonPath('meta.total', 26)->assertJsonPath('meta.last_page', 2);
        foreach (['PRIVATE-REQUEST', 'PRIVATE-NIK', 'PRIVATE-RESPONSE', 'request_payload', 'response_payload'] as $secret) {
            $this->assertStringNotContainsString($secret, $response->getContent());
        }
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->withToken($token)->getJson($url.'?page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->withToken($token)->getJson($url.'?status=unknown')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.can_retry', false);
        $this->withToken($token)->getJson($url.'?status=bogus')->assertUnprocessable();
        $this->withToken($token)->getJson($url.'?page=0')->assertUnprocessable();
        $this->withToken($other)->getJson("/api/import-batches/{$batch->id}/sync-progress")->assertForbidden();
        $this->assertDatabaseCount('sync_attempts', 26);
        Queue::assertNothingPushed();
        Http::assertNothingSent();
    }
}
