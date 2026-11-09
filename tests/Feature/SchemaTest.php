<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\Zone;
use Database\Seeders\ZoneSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_zone_latest_log_returns_newest_by_device_id(): void
    {
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a']);
        SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'temperature' => 10]);
        $newest = SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'temperature' => 11]);
        SensorLog::factory()->create(['device_id' => 'gudang-lain', 'temperature' => 99]);

        $this->assertSame($newest->id, $zone->latestLog->id);
    }

    public function test_prunes_logs_older_than_90_days(): void
    {
        SensorLog::factory()->create(['created_at' => now()->subDays(91)]);
        $recent = SensorLog::factory()->create(['created_at' => now()->subDays(89)]);

        (new SensorLog)->pruneAll();

        $this->assertSame([$recent->id], SensorLog::pluck('id')->all());
    }

    public function test_open_incidents_excludes_ended(): void
    {
        $zone = Zone::factory()->create();
        $open = Incident::factory()->for($zone)->create(['ended_at' => null]);
        Incident::factory()->for($zone)->create(['ended_at' => now()]);

        $this->assertSame([$open->id], $zone->openIncidents()->pluck('id')->all());
        $this->assertTrue($open->isOpen());
    }

    public function test_seeder_creates_three_zones_with_placeholder_limits(): void
    {
        $this->seed(ZoneSeeder::class);

        $this->assertSame(3, Zone::count());
        $sayur = Zone::where('device_id', 'gudang-zona-b')->firstOrFail();
        $this->assertEquals(25, $sayur->temp_max);
        $this->assertEquals(15, $sayur->temp_min);
        $this->assertNull($sayur->device_token_hash);
    }
}
