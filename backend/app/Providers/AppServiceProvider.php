<?php

namespace App\Providers;

use App\Services\Mapping\DatabaseSchemaDiscoveryContract;
use App\Services\Mapping\DatabaseSchemaDiscoveryService;
use App\Services\Mapping\DatabaseSourceReader;
use App\Services\Mapping\DatabaseSourceReaderContract;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DatabaseSchemaDiscoveryContract::class, DatabaseSchemaDiscoveryService::class);
        $this->app->bind(DatabaseSourceReaderContract::class, DatabaseSourceReader::class);
    }

    public function boot(): void
    {
        RateLimiter::for('bridge-login', fn (Request $request) => [
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login-account:'.hash('sha256', $request->ip().':'.mb_strtolower((string) $request->input('email')))),
        ]);
        RateLimiter::for('bridge-tenant', function (Request $request) {
            $user = $request->user();
            $key = $user->isAdmin() ? 'admin:'.$user->id : 'tenant:'.$user->tenant_id;

            return Limit::perMinute($request->isMethod('GET') ? 300 : 120)->by($key.':'.($request->isMethod('GET') ? 'read' : 'write'));
        });
    }
}
