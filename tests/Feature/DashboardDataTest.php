<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DashboardDataTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        Cache::forever('zones:last-offline-check', now()->toIso8601String());
    }

    private function zoneWithLog(string $name, ?float $temperature, int $secondsAgo = 10, float $humidity = 88): Zone
    {
        $zone = Zone::factory()->create(['name' => $name]);
        if ($temperature !== null) {
            SensorLog::factory()->create([
                'device_id' => $zone->device_id, 'temperature' => $temperature, 'humidity' => $humidity,
                'created_at' => now()->subSeconds($secondsAgo),
            ]);
        }

        return $zone;
    }

    private function data(): array
    {
        return $this->actingAs($this->user)->getJson('/dashboard/data')->assertOk()->json();
    }

    public function test_data_orders_zones_by_severity(): void
    {
        $this->zoneWithLog('E tanpa data', null);
        $this->zoneWithLog('D aman', 20);
        $this->zoneWithLog('C waspada', 23);
        $this->zoneWithLog('B offline', 20, 300);
        $this->zoneWithLog('A bahaya', 30);

        $zones = $this->data()['zones'];

        $this->assertSame(
            ['bahaya', 'offline', 'waspada', 'aman', 'tanpa_data'],
            array_column($zones, 'status'),
        );
        $this->assertSame('A bahaya', $zones[0]['name']);
    }

    public function test_data_exposes_reading_and_limits_for_each_zone(): void
    {
        $this->zoneWithLog('Zona X', 21.5);

        $zone = $this->data()['zones'][0];

        $this->assertEquals(21.5, $zone['temperature']);
        $this->assertEquals(88, $zone['humidity']);
        $this->assertEquals(15, $zone['temp_min']);
        $this->assertEquals(25, $zone['temp_max']);
        $this->assertEquals(80, $zone['humidity_min']);
        $this->assertEquals(95, $zone['humidity_max']);
        $this->assertLessThanOrEqual(15, $zone['last_seen_seconds']);
    }

    public function test_data_with_zero_zones_returns_empty_lists(): void
    {
        $data = $this->data();

        $this->assertSame([], $data['zones']);
        $this->assertSame([], $data['banners']);
        $this->assertSame(0, $data['unread_notifications']);
    }

    public function test_zone_without_logs_has_status_tanpa_data_not_offline(): void
    {
        $this->zoneWithLog('Baru', null);

        $zone = $this->data()['zones'][0];

        $this->assertSame('tanpa_data', $zone['status']);
        $this->assertNull($zone['temperature']);
        $this->assertNull($zone['last_seen_seconds']);
    }

    public function test_trend_is_naik_above_max_turun_below_min_else_null(): void
    {
        $this->zoneWithLog('Panas', 29);
        $this->zoneWithLog('Dingin', 10);
        $this->zoneWithLog('Normal', 20);

        $trends = collect($this->data()['zones'])->pluck('trend', 'name');

        $this->assertSame('naik', $trends['Panas']);
        $this->assertSame('turun', $trends['Dingin']);
        $this->assertNull($trends['Normal']);
    }

    public function test_humidity_trend_is_naik_above_max_turun_below_min_else_null(): void
    {
        $this->zoneWithLog('Lembap', 20, 10, 98);
        $this->zoneWithLog('Kering', 20, 10, 70);
        $this->zoneWithLog('Normal', 20, 10, 88);

        $trends = collect($this->data()['zones'])->pluck('humidity_trend', 'name');

        $this->assertSame('naik', $trends['Lembap']);
        $this->assertSame('turun', $trends['Kering']);
        $this->assertNull($trends['Normal']);
    }

    public function test_humidity_trend_is_null_when_humidity_limits_are_not_set(): void
    {
        $zone = $this->zoneWithLog('Tanpa batas RH', 20, 10, 5);
        $zone->update(['humidity_min' => null, 'humidity_max' => null]);

        $this->assertNull($this->data()['zones'][0]['humidity_trend']);
    }

    public function test_open_danger_incident_produces_kritis_banner(): void
    {
        $zone = $this->zoneWithLog('Zona B', 29.4);
        $incident = Incident::factory()->for($zone)->create(['peak_value' => 29.4]);

        $banner = $this->data()['banners'][0];

        $this->assertSame('kritis', $banner['kind']);
        $this->assertSame($incident->id, $banner['incident_id']);
        $this->assertStringContainsString('Zona B', $banner['message']);
        $this->assertNull($banner['acknowledged_by']);
    }

    public function test_open_offline_incident_produces_offline_banner(): void
    {
        $zone = $this->zoneWithLog('Zona C', 20, 300);
        Incident::factory()->for($zone)->create(['reason' => IncidentReason::SensorOffline, 'peak_value' => null]);

        $banner = $this->data()['banners'][0];

        $this->assertSame('offline', $banner['kind']);
        $this->assertStringContainsString('Zona C', $banner['message']);
    }

    public function test_closed_incident_has_no_banner(): void
    {
        $zone = $this->zoneWithLog('Zona B', 20);
        Incident::factory()->for($zone)->create(['ended_at' => now()]);

        $this->assertSame([], $this->data()['banners']);
    }

    public function test_banner_shows_acknowledger_name_after_ack(): void
    {
        $zone = $this->zoneWithLog('Zona B', 29);
        $petugas = User::factory()->create(['name' => 'Budi']);
        Incident::factory()->for($zone)->create(['acknowledged_by' => $petugas->id, 'acknowledged_at' => now()]);

        $this->assertSame('Budi', $this->data()['banners'][0]['acknowledged_by']);
    }

    public function test_unread_notifications_count_is_returned(): void
    {
        $zone = $this->zoneWithLog('Zona B', 29);
        $incident = Incident::factory()->for($zone)->create();
        $this->user->notify(new IncidentOpened($incident));

        $this->assertSame(1, $this->data()['unread_notifications']);
    }

    public function test_scheduler_stale_flag(): void
    {
        $this->assertFalse($this->data()['scheduler_stale']);

        Cache::forever('zones:last-offline-check', now()->subMinutes(6)->toIso8601String());
        $this->assertTrue($this->data()['scheduler_stale']);

        Cache::forget('zones:last-offline-check');
        $this->assertTrue($this->data()['scheduler_stale']);
    }

    public function test_guest_cannot_access_data_endpoints(): void
    {
        $zone = Zone::factory()->create();

        $this->getJson('/dashboard/data')->assertUnauthorized();
        $this->getJson("/zones/{$zone->id}/readings")->assertUnauthorized();
    }

    public function test_readings_bucket_into_five_minute_averages(): void
    {
        $this->travelTo(now()->setTime(10, 30));
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a']);
        foreach ([['10:00:10', 20, 80], ['10:02:00', 22, 90], ['10:06:00', 30, 70]] as [$time, $t, $h]) {
            SensorLog::factory()->create([
                'device_id' => 'gudang-zona-a', 'temperature' => $t, 'humidity' => $h,
                'created_at' => now()->setTimeFromTimeString($time),
            ]);
        }
        SensorLog::factory()->create(['device_id' => 'gudang-lain', 'temperature' => 99]);

        $json = $this->actingAs($this->user)->getJson("/zones/{$zone->id}/readings")->assertOk()->json();

        $this->assertEquals([21, 30], $json['temperature']);
        $this->assertEquals([85, 70], $json['humidity']);
        $this->assertCount(2, $json['labels']);
        $this->assertEquals(15, $json['temp_min']);
        $this->assertEquals(25, $json['temp_max']);
    }

    public function test_readings_hours_parameter_is_clamped_between_1_and_72(): void
    {
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a']);
        SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'created_at' => now()->subHours(80)]);
        SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'created_at' => now()->subHours(70)]);
        SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'created_at' => now()->subMinutes(30)]);

        $get = fn (string $q) => $this->actingAs($this->user)->getJson("/zones/{$zone->id}/readings{$q}")->json('labels');

        $this->assertCount(2, $get('?hours=999'), 'maksimum 72 jam');
        $this->assertCount(1, $get('?hours=0'), 'minimum 1 jam');
        $this->assertCount(1, $get('?hours=abc'), 'default 24 jam');
    }
}
