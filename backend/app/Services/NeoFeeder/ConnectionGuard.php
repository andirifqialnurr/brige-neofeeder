<?php

namespace App\Services\NeoFeeder;

use App\Models\NeoFeederConnection;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;

class ConnectionGuard
{
    private function key(NeoFeederConnection $connection): string
    {
        return 'neofeeder:guard:'.hash('sha256', $connection->tenant_id.':'.$connection->id.':'.$connection->base_url);
    }

    public function run(NeoFeederConnection $connection, string $action, Closure $send): NeoFeederResponse
    {
        $key = $this->key($connection);
        $lock = Cache::lock($key.':lock', 40);
        if (! $lock->get()) {
            throw new OutboundPaused('Koneksi sedang dipakai. Data belum dikirim; coba lagi setelah proses selesai.');
        }
        try {
            $state = Cache::get($key, ['failures' => 0, 'until' => 0]);
            if ($state['until'] > now()->timestamp) {
                throw new OutboundPaused('Koneksi dijeda sementara setelah gangguan. Data belum dikirim.');
            }
            if (RateLimiter::tooManyAttempts($key.':rate', config('services.neofeeder.requests_per_minute', 120))) {
                throw new OutboundPaused('Batas request koneksi tercapai. Data belum dikirim.');
            }
            RateLimiter::hit($key.':rate', 60);
            try {
                $response = $send();
            } catch (ConnectionException|RequestException $error) {
                $this->failure($key, $state);
                throw $error;
            }
            if ($response->errorCode === '') {
                $this->failure($key, $state);
            } elseif ($action !== 'GetToken') {
                Cache::forget($key);
            }

            return $response;
        } finally {
            $lock->release();
        }
    }

    private function failure(string $key, array $state): void
    {
        $failures = $state['failures'] + 1;
        Cache::put($key, ['failures' => $failures, 'until' => $failures >= 3 ? now()->timestamp + 60 : 0], 300);
    }
}
