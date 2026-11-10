<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\DemoData;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DemoDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:07:30');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function demoWarehouse(): Warehouse
    {
        return Warehouse::where('name', DemoData::WAREHOUSE)->sole();
    }

    public function test_seed_creates_warehouse_zones_products_and_history(): void
    {
        Artisan::call('demo:seed', ['--days' => 10]);

        $warehouse = $this->demoWarehouse();
        $zones = $warehouse->zones()->orderBy('name')->get();
        $this->assertSame(['Zona FG', 'Zona Inbound', 'Zona Loading'], $zones->pluck('name')->all());
        $this->assertSame(['demo-fg', 'demo-inbound', 'demo-loading'], $zones->pluck('device_id')->all());
        $this->assertNotEmpty($zones->first()->products);

        foreach ($zones as $zone) {
            $latest = SensorLog::where('device_id', $zone->device_id)->max('created_at');
            // Pembacaan terakhir kurang dari 15 menit lalu, jadi zona tidak langsung offline.
            $this->assertGreaterThanOrEqual(now()->subMinutes(15)->getTimestamp(), Carbon::parse($latest)->getTimestamp());
            $this->assertLessThanOrEqual(now()->getTimestamp(), Carbon::parse($latest)->getTimestamp());
            $oldest = SensorLog::where('device_id', $zone->device_id)->min('created_at');
            $this->assertGreaterThanOrEqual(now()->subDays(10)->subMinutes(15)->getTimestamp(), Carbon::parse($oldest)->getTimestamp());
        }
    }

    public function test_seeded_incidents_match_the_readings(): void
    {
        Artisan::call('demo:seed', ['--days' => 30]);

        $incidents = Incident::whereIn('zone_id', $this->demoWarehouse()->zones()->pluck('id'))->get();
        $this->assertGreaterThanOrEqual(3, $incidents->count());
        $this->assertTrue($incidents->every(fn (Incident $incident) => $incident->ended_at !== null));
        $this->assertContains(IncidentReason::SensorOffline, $incidents->pluck('reason')->all());

        foreach ($incidents->where('reason', IncidentReason::SuhuTinggi) as $incident) {
            $zone = $incident->zone;
            $over = SensorLog::where('device_id', $zone->device_id)
                ->whereBetween('created_at', [$incident->started_at, $incident->ended_at])
                ->where('temperature', '>', $zone->temp_max)->count();
            $this->assertGreaterThanOrEqual(2, $over, 'insiden suhu tinggi punya pembacaan di atas batas');
            $this->assertGreaterThan($zone->temp_max, $incident->peak_value);
        }

        foreach ($incidents->where('reason', IncidentReason::SensorOffline) as $incident) {
            $inside = SensorLog::where('device_id', $incident->zone->device_id)
                ->where('created_at', '>', $incident->started_at)->where('created_at', '<', $incident->ended_at)->count();
            $this->assertSame(0, $inside, 'tidak ada pembacaan selama sensor offline');
        }
    }

    public function test_report_over_the_demo_data_shows_incidents_and_mostly_in_range(): void
    {
        Artisan::call('demo:seed', ['--days' => 30]);

        $zones = $this->demoWarehouse()->zones()->get();
        $rows = (new ReportService)->summary($zones, ReportPeriod::preset('30d', CarbonImmutable::now()));

        $this->assertGreaterThan(0, array_sum(array_column($rows, 'incident_count')));
        $this->assertGreaterThan(0, array_sum(array_column($rows, 'offline_minutes')));
        foreach ($rows as $row) {
            $this->assertNotNull($row['in_range_percent']);
            $this->assertGreaterThan(90.0, $row['in_range_percent']);
        }
    }

    public function test_normal_readings_stay_inside_the_limits_and_out_of_warning(): void
    {
        $at = CarbonImmutable::parse('2026-11-01 00:00:00');
        foreach (['demo-inbound', 'demo-fg', 'demo-loading'] as $device) {
            for ($minute = 0; $minute < 7 * 24 * 60; $minute += 7) {
                $reading = (new DemoData)->reading($device, $at->addMinutes($minute));
                $this->assertGreaterThan(12.0, $reading['temperature']);
                $this->assertLessThan(18.0, $reading['temperature']);
                $this->assertGreaterThan(65.0, $reading['humidity']);
                $this->assertLessThan(80.0, $reading['humidity']);
            }
        }
    }

    public function test_seed_refuses_when_demo_data_already_exists(): void
    {
        $this->assertSame(0, Artisan::call('demo:seed', ['--days' => 3]));
        $logs = SensorLog::count();

        $this->assertSame(1, Artisan::call('demo:seed', ['--days' => 3]));
        $this->assertStringContainsString('demo:clear', Artisan::output());
        $this->assertSame($logs, SensorLog::count());
    }

    public function test_seed_validates_days(): void
    {
        $this->assertSame(1, Artisan::call('demo:seed', ['--days' => 0]));
        $this->assertSame(1, Artisan::call('demo:seed', ['--days' => 91]));
        $this->assertSame(0, Warehouse::count());
    }

    public function test_clear_removes_demo_data_only(): void
    {
        $other = Zone::factory()->create(['device_id' => 'asli-1']);
        SensorLog::factory()->create(['device_id' => 'asli-1']);
        $incident = Incident::factory()->for($other)->create();

        Artisan::call('demo:seed', ['--days' => 5]);
        Artisan::call('demo:simulate', ['--times' => 1, '--interval' => 0]);
        $demoZoneIds = $this->demoWarehouse()->zones()->pluck('id');
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => 'App\Models\User', 'notifiable_id' => 1,
            'data' => json_encode(['warehouse_name' => DemoData::WAREHOUSE, 'title' => 'Demo']), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => 'App\Models\User', 'notifiable_id' => 1,
            'data' => json_encode(['warehouse_name' => 'Gudang Lain', 'title' => 'Asli']), 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame(0, Artisan::call('demo:clear'));

        $this->assertSame(0, Warehouse::where('name', DemoData::WAREHOUSE)->count());
        $this->assertSame(0, Zone::whereIn('id', $demoZoneIds)->count());
        $this->assertSame(0, SensorLog::where('device_id', 'like', 'demo-%')->count());
        $this->assertSame(0, Incident::whereIn('zone_id', $demoZoneIds)->count());
        $this->assertSame(0, DB::table('zone_products')->whereIn('zone_id', $demoZoneIds)->count());
        $this->assertSame(1, DB::table('notifications')->count(), 'hanya notifikasi gudang lain yang tersisa');
        // Data asli tidak tersentuh.
        $this->assertSame(1, SensorLog::where('device_id', 'asli-1')->count());
        $this->assertNotNull($incident->fresh());
        $this->assertNotNull($other->fresh());
    }

    public function test_clear_without_demo_data_succeeds(): void
    {
        $this->assertSame(0, Artisan::call('demo:clear'));
        $this->assertStringContainsString('Tidak ada data demo', Artisan::output());
    }

    public function test_simulate_adds_one_reading_per_demo_zone_per_cycle(): void
    {
        Artisan::call('demo:seed', ['--days' => 2]);
        $before = SensorLog::count();

        $this->assertSame(0, Artisan::call('demo:simulate', ['--times' => 2, '--interval' => 0]));

        $this->assertSame($before + 6, SensorLog::count());
    }

    public function test_simulate_without_demo_data_fails_with_hint(): void
    {
        $this->assertSame(1, Artisan::call('demo:simulate', ['--times' => 1, '--interval' => 0]));
        $this->assertStringContainsString('demo:seed', Artisan::output());
    }
}
