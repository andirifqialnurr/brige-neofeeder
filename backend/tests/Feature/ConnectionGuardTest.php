<?php

namespace Tests\Feature;

use App\Models\NeoFeederConnection;
use App\Services\NeoFeeder\NeoFeederClient;
use App\Services\NeoFeeder\OutboundPaused;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\CreatesSyncWorkspace;
use Tests\TestCase;

class ConnectionGuardTest extends TestCase
{
    use CreatesSyncWorkspace, RefreshDatabase;

    public function test_three_transport_failures_pause_requests_and_recover_after_cooldown(): void
    {
        [$batch] = $this->syncWorkspace();
        $connection = NeoFeederConnection::create(['tenant_id' => $batch->tenant_id, 'base_url' => 'https://feeder.test/ws', 'timeout_ms' => 2000]);
        $client = app(NeoFeederClient::class);
        $calls = 0;
        Http::fake(function ($request, $options) use (&$calls) {
            $this->assertSame(2, $options['timeout']);
            $this->assertSame(2, $options['connect_timeout']);
            $calls++;
            if ($calls <= 3) {
                throw new ConnectionException('offline');
            }

            return Http::response(['error_code' => '0', 'data' => []]);
        });
        for ($i = 0; $i < 3; $i++) {
            try {
                $client->post($connection->base_url, 'GetProdi', [], $connection);
            } catch (ConnectionException) {
            }
        }
        try {
            $client->post($connection->base_url, 'InsertBiodataMahasiswa', [], $connection);
            $this->fail('Circuit should be open');
        } catch (OutboundPaused) {
        }
        $this->assertSame(3, $calls);
        $this->travel(61)->seconds();
        $this->assertTrue($client->post($connection->base_url, 'GetProdi', [], $connection)->successful());
        Http::assertSentCount(1);
    }

    public function test_business_errors_do_not_open_circuit_and_connection_rate_is_isolated(): void
    {
        config(['services.neofeeder.requests_per_minute' => 4]);
        [$batch] = $this->syncWorkspace();
        $connection = NeoFeederConnection::create(['tenant_id' => $batch->tenant_id, 'base_url' => 'https://feeder.test/ws']);
        Http::fake(['*' => Http::response(['error_code' => '100', 'error_desc' => 'Business rejection'])]);
        $client = app(NeoFeederClient::class);
        for ($i = 0; $i < 4; $i++) {
            $this->assertFalse($client->post($connection->base_url, 'InsertBiodataMahasiswa', [], $connection)->successful());
        }
        try {
            $client->post($connection->base_url, 'GetProdi', [], $connection);
            $this->fail('Rate limit should block');
        } catch (OutboundPaused) {
        }
        $other = $connection->replicate();
        $other->save();
        $client->post($other->base_url, 'GetProdi', [], $other);
        Http::assertSentCount(5);
    }

    public function test_timeout_bounds_and_login_throttle(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
        [$batch, , , $token] = $this->syncWorkspace();
        $body = ['tenant_id' => $batch->tenant_id, 'base_url' => 'https://feeder.test/ws', 'timeout_ms' => 31000];
        $this->withToken($token)->postJson('/api/neofeeder-connections', $body)->assertUnprocessable();
        $id = $this->withToken($token)->postJson('/api/neofeeder-connections', [...$body, 'timeout_ms' => 1000])->assertCreated()->assertJsonPath('data.timeout_ms', 1000)->json('data.id');
        $this->withToken($token)->patchJson('/api/neofeeder-connections/'.$id, ['timeout_ms' => 500])->assertUnprocessable();
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(422);
        }
        $this->postJson('/api/auth/login', ['email' => 'missing@example.test', 'password' => 'wrong'])->assertStatus(429)->assertHeader('Retry-After');
    }
}
