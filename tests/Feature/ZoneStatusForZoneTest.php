<?php

namespace Tests\Feature;

use App\Enums\ZoneStatus;
use App\Models\SensorLog;
use App\Models\Zone;
use App\Services\ZoneStatusEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneStatusForZoneTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_without_logs_is_tanpa_data_not_offline(): void
    {
        $zone = Zone::factory()->create();

        $this->assertSame(ZoneStatus::TanpaData, (new ZoneStatusEvaluator)->forZone($zone));
    }

    public function test_log_older_than_120_seconds_is_offline(): void
    {
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a']);
        SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'created_at' => now()->subSeconds(121)]);

        $this->assertSame(ZoneStatus::Offline, (new ZoneStatusEvaluator)->forZone($zone));
    }

    public function test_log_within_120_seconds_uses_normal_status(): void
    {
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a']);
        SensorLog::factory()->create([
            'device_id' => 'gudang-zona-a', 'temperature' => 30, 'humidity' => 88,
            'created_at' => now()->subSeconds(119),
        ]);

        $this->assertSame(ZoneStatus::Bahaya, (new ZoneStatusEvaluator)->forZone($zone));
    }
}
