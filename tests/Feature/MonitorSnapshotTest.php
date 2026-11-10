<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\MonitorSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorSnapshotTest extends TestCase
{
    use RefreshDatabase;

    private Carbon $now;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        // Zona waktu aplikasi (Asia/Jakarta) juga zona bawaan PHP; waktu uji harus searah dengan itu
        // supaya created_at yang tersimpan dan terbaca kembali tidak bergeser.
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->now = Carbon::parse('2026-11-10 14:32:05');
        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang Demo']);
    }

    /** Batas bawaan: suhu 15–25 (margin 2), kelembapan 80–95 (margin 5). */
    private const READINGS = [
        'aman' => [20, 87, 10],
        'waspada' => [24, 87, 10],
        'bahaya' => [26, 87, 10],
        'offline' => [20, 87, 600],
    ];

    private function zone(string $name, string $state, ?Warehouse $warehouse = null, array $attributes = []): Zone
    {
        static $sequence = 0;
        $sequence++;

        $zone = Zone::factory()->create($attributes + [
            'name' => $name,
            'warehouse_id' => ($warehouse ?? $this->warehouse)->id,
            'device_id' => "dev-monitor-{$sequence}",
        ]);

        if ($state !== 'tanpa') {
            [$temperature, $humidity, $secondsAgo] = self::READINGS[$state];
            SensorLog::factory()->create([
                'device_id' => $zone->device_id,
                'temperature' => $temperature,
                'humidity' => $humidity,
                'created_at' => $this->now->copy()->subSeconds($secondsAgo),
            ]);
        }

        return $zone;
    }

    private function build(?Warehouse $warehouse = null): array
    {
        return app(MonitorSnapshot::class)->build($warehouse ?? $this->warehouse, $this->now);
    }

    /** @return array<string, array{0: list<string>, 1: string, 2: string}> */
    public static function overallCases(): array
    {
        return [
            'semua aman' => [['aman', 'aman'], 'aman', 'SEMUA ZONA AMAN'],
            'satu bahaya satu aman' => [['bahaya', 'aman'], 'bahaya', 'PERHATIAN: 1 ZONA DI LUAR BATAS'],
            'dua bahaya' => [['bahaya', 'bahaya', 'aman'], 'bahaya', 'PERHATIAN: 2 ZONA DI LUAR BATAS'],
            'offline lebih parah dari waspada' => [['offline', 'waspada'], 'offline', 'SENSOR OFFLINE: 1 ZONA'],
            'waspada dan aman' => [['waspada', 'aman'], 'waspada', 'WASPADA: 1 ZONA MENDEKATI BATAS'],
            'bahaya mengalahkan offline' => [['bahaya', 'offline'], 'bahaya', 'PERHATIAN: 1 ZONA DI LUAR BATAS'],
            'tanpa zona' => [[], 'tanpa_data', 'BELUM ADA DATA'],
            'semua belum mengirim data' => [['tanpa', 'tanpa'], 'tanpa_data', 'BELUM ADA DATA'],
            'aman dan belum ada data' => [['aman', 'tanpa'], 'aman', 'SEMUA ZONA AMAN'],
        ];
    }

    #[DataProvider('overallCases')]
    public function test_overall_level_follows_the_worst_zone(array $states, string $level, string $headline): void
    {
        foreach ($states as $i => $state) {
            $this->zone("Zona {$i}", $state);
        }

        $overall = $this->build()['overall'];

        $this->assertSame($level, $overall['level']);
        $this->assertSame($headline, $overall['headline']);
    }

    public function test_danger_detail_names_the_zone_reason_value_and_limit(): void
    {
        $this->zone('Zona Loading', 'aman', attributes: ['temp_max' => 20]);
        SensorLog::query()->latest('id')->first()->forceFill(['temperature' => 21.4])->save();

        $detail = $this->build()['overall']['detail'];

        $this->assertSame('Zona Loading: Suhu terlalu tinggi 21.4°C (batas 20°C)', $detail);
    }

    public function test_a_zone_with_two_dangers_lists_both(): void
    {
        $this->zone('Zona Ganda', 'aman');
        SensorLog::query()->latest('id')->first()->forceFill(['temperature' => 26, 'humidity' => 97])->save();

        $detail = $this->build()['overall']['detail'];

        $this->assertStringContainsString('Zona Ganda: Suhu terlalu tinggi 26°C (batas 25°C)', $detail);
        $this->assertStringContainsString('Zona Ganda: Kelembapan terlalu tinggi 97% (batas 95%)', $detail);
        $this->assertStringContainsString(' · ', $detail);
    }

    public function test_offline_and_warning_details_list_zone_names(): void
    {
        $this->zone('Zona Mati', 'offline');
        $this->assertSame('Zona Mati', $this->build()['overall']['detail']);

        $this->zone('Zona Dekat', 'waspada');
        // Offline masih yang terburuk, jadi rinciannya tetap nama zona offline.
        $this->assertSame('Zona Mati', $this->build()['overall']['detail']);
    }

    public function test_warning_detail_lists_warning_zone_names(): void
    {
        $this->zone('Zona Dekat', 'waspada');
        $this->zone('Zona Aman', 'aman');

        $this->assertSame('Zona Dekat', $this->build()['overall']['detail']);
    }

    public function test_zones_are_sorted_by_severity_then_name(): void
    {
        $this->zone('Zona B', 'aman');
        $this->zone('Zona A', 'aman');
        $this->zone('Zona Z', 'bahaya');
        $this->zone('Zona Y', 'offline');
        $this->zone('Zona W', 'waspada');
        $this->zone('Zona N', 'tanpa');

        $names = array_column($this->build()['zones'], 'name');

        $this->assertSame(['Zona Z', 'Zona Y', 'Zona W', 'Zona A', 'Zona B', 'Zona N'], $names);
    }

    public function test_zone_without_humidity_limits_has_null_limits_and_does_not_break(): void
    {
        $this->zone('Zona Kering', 'aman', attributes: ['humidity_min' => null, 'humidity_max' => null]);

        $zone = $this->build()['zones'][0];

        $this->assertNull($zone['humidity_min']);
        $this->assertNull($zone['humidity_max']);
        $this->assertSame('aman', $zone['status']);
    }

    public function test_offline_zone_keeps_its_last_values_and_reports_last_seen_seconds(): void
    {
        $this->zone('Zona Mati', 'offline');

        $zone = $this->build()['zones'][0];

        $this->assertSame('offline', $zone['status']);
        $this->assertSame('OFFLINE', $zone['status_label']);
        $this->assertEquals(20, $zone['temperature']);
        $this->assertEquals(87, $zone['humidity']);
        $this->assertSame(600, $zone['last_seen_seconds']);
    }

    public function test_zone_without_logs_reports_nulls_and_tanpa_data(): void
    {
        $this->zone('Zona Baru', 'tanpa');

        $zone = $this->build()['zones'][0];

        $this->assertSame('tanpa_data', $zone['status']);
        $this->assertSame('BELUM ADA DATA', $zone['status_label']);
        $this->assertNull($zone['temperature']);
        $this->assertNull($zone['humidity']);
        $this->assertNull($zone['last_seen_seconds']);
    }

    public function test_only_the_given_warehouses_zones_are_included(): void
    {
        $other = Warehouse::factory()->create(['name' => 'Gudang Lain']);
        $this->zone('Zona Saya', 'aman');
        $this->zone('Zona Orang Lain', 'bahaya', $other);

        $snapshot = $this->build();

        $this->assertSame(['Zona Saya'], array_column($snapshot['zones'], 'name'));
        $this->assertSame('aman', $snapshot['overall']['level']);
        $this->assertStringNotContainsString('Zona Orang Lain', json_encode($snapshot));
        $this->assertSame('Gudang Demo', $snapshot['warehouse']);
    }

    public function test_snapshot_never_exposes_products_or_ids(): void
    {
        $zone = $this->zone('Zona Rahasia', 'aman');
        $zone->syncProducts(['Telur Premium Rahasia']);

        $snapshot = $this->build();

        $this->assertStringNotContainsString('Telur Premium Rahasia', json_encode($snapshot));
        $this->assertSame(
            ['name', 'status', 'status_label', 'temperature', 'humidity', 'temp_min', 'temp_max', 'humidity_min', 'humidity_max', 'last_seen_seconds'],
            array_keys($snapshot['zones'][0]),
        );
        $this->assertSame(['warehouse', 'server_time', 'timezone', 'overall', 'zones'], array_keys($snapshot));
    }

    public function test_timezone_and_server_time(): void
    {
        $snapshot = $this->build();

        $this->assertSame('Asia/Jakarta', $snapshot['timezone']);
        $this->assertSame('2026-11-10T14:32:05+07:00', $snapshot['server_time']);
    }
}
