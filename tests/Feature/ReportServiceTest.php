<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\Zone;
use App\Services\ReportService;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private ReportPeriod $day;

    private ReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->zone = Zone::factory()->create([
            'device_id' => 'dev-r', 'temp_min' => 15, 'temp_max' => 25, 'humidity_min' => 80, 'humidity_max' => 95,
        ]);
        $this->day = ReportPeriod::preset('24h', CarbonImmutable::now());
        $this->service = new ReportService;
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function log(float $temperature, float $humidity, string $at, string $device = 'dev-r'): SensorLog
    {
        return SensorLog::factory()->create([
            'device_id' => $device, 'temperature' => $temperature, 'humidity' => $humidity,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    private function incident(IncidentReason $reason, string $start, ?string $end, ?Zone $zone = null): Incident
    {
        return Incident::factory()->for($zone ?? $this->zone)->create([
            'reason' => $reason, 'started_at' => $start, 'ended_at' => $end,
        ]);
    }

    public function test_summary_aggregates_only_this_zones_readings_inside_the_period(): void
    {
        $this->log(18, 85, '2026-11-10 10:00:00');
        $this->log(20, 90, '2026-11-10 10:01:00');
        $this->log(22, 95, '2026-11-10 10:02:00');
        $this->log(99, 99, '2026-11-09 11:59:59'); // sebelum from
        $this->log(99, 99, '2026-11-10 12:00:01'); // setelah to
        $this->log(99, 99, '2026-11-10 10:00:00', 'dev-lain');
        $other = Zone::factory()->create(['device_id' => 'dev-kosong']);

        $rows = $this->service->summary(collect([$this->zone, $other]), $this->day);

        $this->assertCount(2, $rows);
        $this->assertSame($this->zone->id, $rows[0]['zone']->id);
        $this->assertSame(3, $rows[0]['readings']);
        $this->assertEqualsWithDelta(18, $rows[0]['temperature']['min'], 0.001);
        $this->assertEqualsWithDelta(22, $rows[0]['temperature']['max'], 0.001);
        $this->assertEqualsWithDelta(20, $rows[0]['temperature']['avg'], 0.001);
        $this->assertEqualsWithDelta(85, $rows[0]['humidity']['min'], 0.001);
        $this->assertEqualsWithDelta(95, $rows[0]['humidity']['max'], 0.001);
        $this->assertEqualsWithDelta(90, $rows[0]['humidity']['avg'], 0.001);
        $this->assertEqualsWithDelta(100.0, $rows[0]['in_range_percent'], 0.001);
        $this->assertSame($other->id, $rows[1]['zone']->id);
        $this->assertSame(0, $rows[1]['readings']);
    }

    public function test_in_range_percent_counts_readings_within_both_limits_inclusive(): void
    {
        $this->log(15, 85, '2026-11-10 10:00:00'); // tepat di batas bawah suhu: aman
        $this->log(25, 90, '2026-11-10 10:01:00'); // tepat di batas atas suhu: aman
        $this->log(26, 90, '2026-11-10 10:02:00'); // suhu di atas batas
        $this->log(20, 96, '2026-11-10 10:03:00'); // suhu aman tetapi kelembapan di atas batas

        $row = $this->service->summary(collect([$this->zone]), $this->day)[0];

        $this->assertEqualsWithDelta(50.0, $row['in_range_percent'], 0.001);
    }

    public function test_in_range_ignores_humidity_when_limits_are_null(): void
    {
        $this->zone->update(['humidity_min' => null, 'humidity_max' => null]);
        $this->log(20, 10, '2026-11-10 10:00:00');

        $row = $this->service->summary(collect([$this->zone]), $this->day)[0];

        $this->assertEqualsWithDelta(100.0, $row['in_range_percent'], 0.001);
    }

    public function test_zone_without_readings_has_null_stats_and_zero_counts(): void
    {
        $row = $this->service->summary(collect([$this->zone]), $this->day)[0];

        $this->assertSame(0, $row['readings']);
        $this->assertSame(['min' => null, 'max' => null, 'avg' => null], $row['temperature']);
        $this->assertSame(['min' => null, 'max' => null, 'avg' => null], $row['humidity']);
        $this->assertNull($row['in_range_percent']);
        $this->assertSame(0, $row['incident_count']);
        $this->assertSame(0, $row['incident_minutes']);
        $this->assertSame(0, $row['offline_minutes']);
    }

    public function test_incident_counts_clip_to_period_and_do_not_double_count_overlaps(): void
    {
        $this->incident(IncidentReason::SuhuTinggi, '2026-11-10 10:00:00', '2026-11-10 11:00:00');
        $this->incident(IncidentReason::LembapTinggi, '2026-11-10 10:30:00', '2026-11-10 11:30:00');
        $this->incident(IncidentReason::SensorOffline, '2026-11-10 02:00:00', '2026-11-10 02:20:00');
        $this->incident(IncidentReason::SuhuRendah, '2026-11-07 12:00:00', '2026-11-09 15:00:00'); // dipotong: 12:00-15:00 = 180 mnt
        $this->incident(IncidentReason::SuhuTinggi, '2026-11-10 11:00:00', null);                  // terbuka: sampai sekarang
        $this->incident(IncidentReason::SuhuTinggi, '2026-11-08 10:00:00', '2026-11-08 11:00:00'); // selesai sebelum periode
        $this->incident(IncidentReason::SuhuTinggi, '2026-11-10 09:00:00', '2026-11-10 09:30:00', Zone::factory()->create()); // zona lain

        $row = $this->service->summary(collect([$this->zone]), $this->day)[0];

        $this->assertSame(5, $row['incident_count']);
        // Gabungan 10:00-12:00 (120 mnt) + 09-11 12:00-15:00 (180 mnt); tumpang tindih dihitung sekali.
        $this->assertSame(300, $row['incident_minutes']);
        $this->assertSame(20, $row['offline_minutes']);
    }

    public function test_series_buckets_keep_min_avg_and_max(): void
    {
        $this->log(10, 80, '2026-11-10 10:00:10');
        $this->log(30, 90, '2026-11-10 10:03:00');
        $this->log(20, 85, '2026-11-10 10:05:00');

        $series = $this->service->series($this->zone, $this->day);

        $this->assertSame(300, $series['bucket_seconds']);
        $this->assertSame(['2026-11-10T10:00:00+07:00', '2026-11-10T10:05:00+07:00'], $series['labels']);
        $this->assertEqualsWithDelta(10, $series['temperature']['min'][0], 0.001);
        $this->assertEqualsWithDelta(30, $series['temperature']['max'][0], 0.001);
        $this->assertEqualsWithDelta(20, $series['temperature']['avg'][0], 0.001);
        $this->assertEqualsWithDelta(80, $series['humidity']['min'][0], 0.001);
        $this->assertEqualsWithDelta(90, $series['humidity']['max'][0], 0.001);
        $this->assertEqualsWithDelta(20, $series['temperature']['avg'][1], 0.001);
    }

    public function test_readings_at_a_bucket_boundary_fall_into_different_buckets(): void
    {
        $this->log(20, 85, '2026-11-10 11:04:59');
        $this->log(20, 85, '2026-11-10 11:05:00');

        $series = $this->service->series($this->zone, $this->day);

        $this->assertSame(['2026-11-10T11:00:00+07:00', '2026-11-10T11:05:00+07:00'], $series['labels']);
    }

    public function test_series_is_empty_when_there_are_no_readings(): void
    {
        $series = $this->service->series($this->zone, $this->day);

        $this->assertSame([], $series['labels']);
        $this->assertSame(['avg' => [], 'min' => [], 'max' => []], $series['temperature']);
        $this->assertSame(['avg' => [], 'min' => [], 'max' => []], $series['humidity']);
    }

    public function test_series_point_count_is_bounded_for_ninety_days(): void
    {
        $period = ReportPeriod::preset('90d', CarbonImmutable::now());
        $rows = [];
        for ($hour = 1; $hour <= 90 * 24; $hour++) {
            $at = $period->from->addHours($hour)->format('Y-m-d H:i:s');
            $rows[] = ['device_id' => 'dev-r', 'temperature' => 20, 'humidity' => 85, 'created_at' => $at, 'updated_at' => $at];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            SensorLog::insert($chunk);
        }

        $series = $this->service->series($this->zone, $period);

        $this->assertGreaterThan(0, count($series['labels']));
        $this->assertLessThanOrEqual(300, count($series['labels']));
        $this->assertSame(43200, $series['bucket_seconds']);
    }

    public function test_incidents_lists_overlapping_newest_first_and_respects_limit(): void
    {
        $old = $this->incident(IncidentReason::SuhuTinggi, '2026-11-09 13:00:00', '2026-11-09 14:00:00');
        $mid = $this->incident(IncidentReason::LembapTinggi, '2026-11-10 08:00:00', '2026-11-10 09:00:00');
        $new = $this->incident(IncidentReason::SuhuTinggi, '2026-11-10 11:00:00', null);
        $this->incident(IncidentReason::SuhuTinggi, '2026-11-01 08:00:00', '2026-11-01 09:00:00'); // di luar periode

        $this->assertSame([$new->id, $mid->id, $old->id], $this->service->incidents($this->zone, $this->day)->pluck('id')->all());
        $this->assertSame([$new->id, $mid->id], $this->service->incidents($this->zone, $this->day, 2)->pluck('id')->all());
    }

    public function test_readings_streams_in_id_order_for_zone_and_period_only(): void
    {
        $first = $this->log(18, 85, '2026-11-10 10:00:00');
        $this->log(99, 99, '2026-11-10 10:00:00', 'dev-lain');
        $this->log(99, 99, '2026-11-08 10:00:00');
        $second = $this->log(19, 86, '2026-11-10 10:01:00');

        $readings = $this->service->readings($this->zone, $this->day);

        $this->assertInstanceOf(\Illuminate\Support\LazyCollection::class, $readings);
        $this->assertSame([$first->id, $second->id], $readings->pluck('id')->all());
    }
}
