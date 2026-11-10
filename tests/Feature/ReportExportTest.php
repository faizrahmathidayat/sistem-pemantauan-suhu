<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $a;

    private Warehouse $b;

    private Zone $a1;

    private Zone $a2;

    private Zone $b1;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        $this->a = Warehouse::factory()->create(['name' => 'Gudang A']);
        $this->b = Warehouse::factory()->create(['name' => 'Gudang B']);
        $this->a1 = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona A1', 'device_id' => 'dev-a1']);
        $this->a2 = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona A2', 'device_id' => 'dev-a2']);
        $this->b1 = Zone::factory()->create(['warehouse_id' => $this->b->id, 'name' => 'Zona B1', 'device_id' => 'dev-b1']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function operator(Warehouse ...$warehouses): User
    {
        return User::factory()->withRole(Role::where('name', 'Operator')->sole())->assignedTo(...$warehouses)->create();
    }

    private function log(Zone $zone, float $temperature, float $humidity, string $at): void
    {
        SensorLog::factory()->create([
            'device_id' => $zone->device_id, 'temperature' => $temperature, 'humidity' => $humidity,
            'created_at' => $at, 'updated_at' => $at,
        ]);
    }

    /** @return list<list<string|null>> baris CSV tanpa BOM */
    private function rows(TestResponse $response): array
    {
        $content = $response->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content, 'BOM UTF-8');

        return array_map('str_getcsv', explode("\n", rtrim(substr($content, 3), "\n")));
    }

    public function test_summary_csv_has_bom_header_and_one_row_per_zone(): void
    {
        $this->log($this->a1, 18, 85, '2026-11-10 10:00:00');
        $this->log($this->a1, 22, 95, '2026-11-10 10:01:00');
        $this->log($this->b1, 5, 50, '2026-11-10 10:00:00');

        $rows = $this->rows($this->actingAs($this->operator($this->a))->get('/reports/summary.csv')->assertOk());

        $this->assertSame([
            'Gudang', 'Zona', 'Device ID', 'Pembacaan', 'Suhu min', 'Suhu maks', 'Suhu rata-rata',
            'Kelembapan min', 'Kelembapan maks', 'Kelembapan rata-rata', '% dalam batas',
            'Jumlah insiden', 'Menit insiden', 'Menit offline',
        ], $rows[0]);
        $this->assertCount(3, $rows);
        $this->assertSame(['Gudang A', 'Zona A1', 'dev-a1', '2', '18', '22', '20', '85', '95', '90', '100', '0', '0', '0'], $rows[1]);
        $this->assertSame('Zona A2', $rows[2][1]);
    }

    public function test_readings_csv_contains_only_selected_zone_and_period_in_time_order(): void
    {
        $this->log($this->a1, 18.5, 85, '2026-11-10 10:00:00');
        $this->log($this->a2, 99, 99, '2026-11-10 10:00:00');
        $this->log($this->a1, 99, 99, '2026-11-01 10:00:00'); // di luar periode 7 hari
        $this->log($this->a1, 19, 86, '2026-11-10 10:01:00');

        $rows = $this->rows($this->actingAs($this->operator($this->a))->get("/reports/readings.csv?zona={$this->a1->id}")->assertOk());

        $this->assertSame(['Waktu', 'Gudang', 'Zona', 'Device ID', 'Suhu (°C)', 'Kelembapan (%)'], $rows[0]);
        $this->assertSame([
            ['2026-11-10 10:00:00', 'Gudang A', 'Zona A1', 'dev-a1', '18.5', '85'],
            ['2026-11-10 10:01:00', 'Gudang A', 'Zona A1', 'dev-a1', '19', '86'],
        ], array_slice($rows, 1));
    }

    public function test_formula_like_names_are_escaped(): void
    {
        $this->a1->update(['name' => '=HYPERLINK("x")']);
        $this->a->update(['name' => '@Gudang']);
        $this->log($this->a1, 18, 85, '2026-11-10 10:00:00');
        $user = $this->operator($this->a);

        $summary = $this->rows($this->actingAs($user)->get('/reports/summary.csv'));
        $readings = $this->rows($this->actingAs($user)->get("/reports/readings.csv?zona={$this->a1->id}"));

        $this->assertSame("'@Gudang", $summary[1][0]);
        $this->assertSame('\'=HYPERLINK("x")', $summary[1][1]);
        $this->assertSame("'@Gudang", $readings[1][1]);
        $this->assertSame('\'=HYPERLINK("x")', $readings[1][2]);
    }

    public function test_empty_period_yields_header_only_for_readings_and_empty_cells_for_summary(): void
    {
        $user = $this->operator($this->a);

        $readings = $this->rows($this->actingAs($user)->get("/reports/readings.csv?zona={$this->a1->id}"));
        $summary = $this->rows($this->actingAs($user)->get('/reports/summary.csv'));

        $this->assertCount(1, $readings);
        $this->assertSame(['Gudang A', 'Zona A1', 'dev-a1', '0', '', '', '', '', '', '', '', '0', '0', '0'], $summary[1]);
    }

    public function test_invalid_period_returns_422(): void
    {
        $user = $this->operator($this->a);
        $query = 'periode=custom&dari=2026-11-05&sampai=2026-11-01';

        $this->actingAs($user)->get("/reports/summary.csv?{$query}")->assertStatus(422);
        $this->actingAs($user)->get("/reports/readings.csv?zona={$this->a1->id}&{$query}")->assertStatus(422);
    }

    public function test_readings_export_without_a_zone_returns_422(): void
    {
        $empty = Warehouse::factory()->create(['name' => 'Gudang Kosong']);

        $this->actingAs($this->operator($empty))->get("/reports/readings.csv?gudang={$empty->id}")->assertStatus(422);
    }

    public function test_user_without_any_warehouse_cannot_export(): void
    {
        $user = $this->operator();

        $this->actingAs($user)->get('/reports/summary.csv')->assertStatus(422);
        $this->actingAs($user)->get('/reports/readings.csv')->assertStatus(422);
    }

    public function test_foreign_zone_or_warehouse_returns_403_for_both_exports(): void
    {
        $user = $this->operator($this->a);

        $this->actingAs($user)->get("/reports/summary.csv?gudang={$this->b->id}")->assertForbidden();
        $this->actingAs($user)->get("/reports/summary.csv?zona={$this->b1->id}")->assertForbidden();
        $this->actingAs($user)->get("/reports/readings.csv?gudang={$this->b->id}")->assertForbidden();
        $this->actingAs($user)->get("/reports/readings.csv?zona={$this->b1->id}")->assertForbidden();
    }

    public function test_response_is_not_cacheable_and_has_attachment_filename(): void
    {
        $response = $this->actingAs($this->operator($this->a))->get("/reports/readings.csv?zona={$this->a1->id}")->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment', $response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('laporan-data-gudang-a-zona-a1-20261103-20261110.csv', $response->headers->get('Content-Disposition'));
    }

    public function test_large_readings_export_is_complete(): void
    {
        $rows = [];
        for ($second = 0; $second < 12000; $second++) {
            $at = Carbon::parse('2026-11-10 08:00:00')->addSeconds($second)->format('Y-m-d H:i:s');
            $rows[] = ['device_id' => 'dev-a1', 'temperature' => 20, 'humidity' => 85, 'created_at' => $at, 'updated_at' => $at];
        }
        foreach (array_chunk($rows, 1000) as $chunk) {
            SensorLog::insert($chunk);
        }

        $rows = $this->rows($this->actingAs($this->operator($this->a))->get("/reports/readings.csv?zona={$this->a1->id}&periode=24h")->assertOk());

        $this->assertCount(12001, $rows);
        $this->assertSame('2026-11-10 08:00:00', $rows[1][0]);
        $this->assertSame('2026-11-10 11:19:59', $rows[12000][0]);
    }
}
