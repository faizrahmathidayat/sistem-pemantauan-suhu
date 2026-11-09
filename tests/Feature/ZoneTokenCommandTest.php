<?php

namespace Tests\Feature;

use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ZoneTokenCommandTest extends TestCase
{
    use RefreshDatabase;

    private function issueToken(string $deviceId): string
    {
        $this->assertSame(0, Artisan::call('zone:token', ['device_id' => $deviceId]));
        preg_match('/Token: (\S{48})/', Artisan::output(), $m);
        $this->assertNotEmpty($m, 'token tidak ditemukan pada output');

        return $m[1];
    }

    private function send(string $token, string $deviceId)
    {
        return $this->postJson('/api/v1/telemetry', ['device_id' => $deviceId, 'temperature' => 20, 'humidity' => 85], [
            'Authorization' => 'Bearer '.$token,
        ]);
    }

    public function test_command_prints_token_once_and_authenticates(): void
    {
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a']);

        $token = $this->issueToken('gudang-zona-a');

        $this->assertSame(hash('sha256', $token), $zone->fresh()->device_token_hash);
        $this->assertNotSame($token, $zone->fresh()->device_token_hash);
        $this->send($token, 'gudang-zona-a')->assertCreated();
    }

    public function test_rerun_rotates_token_old_token_fails(): void
    {
        Zone::factory()->create(['device_id' => 'gudang-zona-a']);

        $old = $this->issueToken('gudang-zona-a');
        $new = $this->issueToken('gudang-zona-a');

        $this->assertNotSame($old, $new);
        $this->send($old, 'gudang-zona-a')->assertUnauthorized();
        $this->send($new, 'gudang-zona-a')->assertCreated();
    }

    public function test_unknown_device_id_fails(): void
    {
        $this->assertSame(1, Artisan::call('zone:token', ['device_id' => 'tidak-ada']));
    }
}
