<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Perangkat mengirim tiap 30 detik; 20/menit memberi ruang untuk percobaan ulang.
        RateLimiter::for('telemetry', fn (Request $request) => Limit::perMinute(20)
            ->by(hash('sha256', (string) ($request->bearerToken() ?: $request->ip()))));
    }
}
