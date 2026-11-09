<?php

namespace App\Http\Middleware;

use App\Models\Zone;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mengautentikasi perangkat ESP32 lewat bearer token (sha256 tersimpan di zones).
 */
class AuthenticateDevice
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if ($token === null || $token === '') {
            return $this->unauthorized();
        }

        $zone = Zone::where('device_token_hash', hash('sha256', $token))->first();
        if ($zone === null) {
            return $this->unauthorized();
        }

        // device_id yang kosong/hilang dibiarkan lolos agar ditolak validasi (422);
        // device_id milik zona lain ditolak di sini (401).
        $deviceId = $request->input('device_id');
        if (is_string($deviceId) && $deviceId !== '' && $deviceId !== $zone->device_id) {
            return $this->unauthorized();
        }

        $request->attributes->set('zone', $zone);

        return $next($request);
    }

    private function unauthorized(): Response
    {
        return response()->json(['message' => 'Unauthenticated device.'], 401);
    }
}
