<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Enums\Permission;
use App\Models\Incident;
use App\Models\Role;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportPageTest extends TestCase
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

    /** @return array<string, mixed> */
    private function embeddedData(string $html): array
    {
        $this->assertSame(1, preg_match('~<script id="report-data" type="application/json">(.*?)</script>~s', $html, $match));

        return json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
    }

    public function test_page_shows_summary_rows_for_zones_of_selected_warehouse_only(): void
    {
        $this->log($this->a1, 18, 85, '2026-11-10 10:00:00');
        $this->log($this->a1, 20, 90, '2026-11-10 10:01:00');
        $this->log($this->a1, 22, 95, '2026-11-10 10:02:00');
        $this->log($this->b1, 5, 50, '2026-11-10 10:00:00');

        $this->actingAs($this->operator($this->a))->get('/reports')
            ->assertOk()
            ->assertSee('Ringkasan per zona')
            ->assertSee('Zona A1')
            ->assertSee('Zona A2')
            ->assertDontSee('Zona B1')
            ->assertSee('18,0')
            ->assertSee('20,0')
            ->assertSee('22,0');
    }

    public function test_warehouse_selector_lists_only_visible_warehouses(): void
    {
        $this->actingAs($this->operator($this->a))->get('/reports')
            ->assertOk()->assertSee('Gudang A')->assertDontSee('Gudang B');

        $this->actingAs(User::factory()->create())->get('/reports')
            ->assertOk()->assertSee('Gudang A')->assertSee('Gudang B');
    }

    public function test_empty_period_shows_dashes_and_no_data_notice(): void
    {
        $this->actingAs($this->operator($this->a))->get('/reports')
            ->assertOk()
            ->assertSee('Belum ada data pada periode ini.')
            ->assertSee('–');
    }

    public function test_invalid_period_shows_error_message_and_no_summary(): void
    {
        $this->actingAs($this->operator($this->a))->get('/reports?periode=custom&dari=2026-11-05&sampai=2026-11-01')
            ->assertOk()
            ->assertSee('Tanggal awal tidak boleh setelah tanggal akhir.')
            ->assertDontSee('Ringkasan per zona');
    }

    public function test_foreign_warehouse_and_zone_parameters_are_forbidden(): void
    {
        $operator = $this->operator($this->a);

        $this->actingAs($operator)->get("/reports?gudang={$this->b->id}")->assertForbidden();
        $this->actingAs($operator)->get("/reports?zona={$this->b1->id}")->assertForbidden();
    }

    public function test_unknown_warehouse_is_not_found(): void
    {
        $this->actingAs(User::factory()->create())->get('/reports?gudang=9999')->assertNotFound();
    }

    public function test_incident_list_shows_zone_incidents_in_period(): void
    {
        Incident::factory()->for($this->a1)->create([
            'reason' => IncidentReason::LembapTinggi, 'started_at' => '2026-11-10 09:00:00', 'ended_at' => '2026-11-10 09:30:00', 'peak_value' => 97,
        ]);
        Incident::factory()->for($this->a2)->create([
            'reason' => IncidentReason::SuhuRendah, 'started_at' => '2026-11-10 09:00:00', 'ended_at' => '2026-11-10 09:30:00',
        ]);

        $this->actingAs($this->operator($this->a))->get("/reports?zona={$this->a1->id}")
            ->assertOk()
            ->assertSee('Kelembapan terlalu tinggi')
            ->assertDontSee('Suhu terlalu rendah');
    }

    public function test_page_states_retention(): void
    {
        $this->actingAs($this->operator($this->a))->get('/reports')->assertSee('Data disimpan 90 hari');
    }

    public function test_chart_data_is_embedded_as_json(): void
    {
        $this->a1->update(['name' => '</script><b>Zona']);
        $this->log($this->a1, 18, 85, '2026-11-10 10:00:10');
        $this->log($this->a1, 22, 90, '2026-11-10 10:03:00');

        $response = $this->actingAs($this->operator($this->a))->get("/reports?zona={$this->a1->id}&periode=24h")->assertOk();
        $data = $this->embeddedData($response->getContent());

        $this->assertSame(['2026-11-10T10:00:00+07:00'], $data['labels']);
        // JSON tidak membedakan 20 dan 20.0, jadi dibandingkan sebagai angka.
        $this->assertEquals([20], $data['temperature']['avg']);
        $this->assertEquals([18], $data['temperature']['min']);
        $this->assertEquals([22], $data['temperature']['max']);
        $this->assertEquals(15, $data['temp_min']);
        $this->assertEquals(25, $data['temp_max']);
        $this->assertSame('</script><b>Zona', $data['zone']);
        $this->assertStringNotContainsString('</script><b>', $response->getContent());
    }

    public function test_user_without_any_warehouse_sees_notice(): void
    {
        $this->actingAs($this->operator())->get('/reports')
            ->assertOk()
            ->assertSee('Anda belum ditugaskan ke gudang mana pun.');
    }

    public function test_navigation_and_dashboard_link_follow_the_permission(): void
    {
        $this->actingAs($this->operator($this->a))->get('/dashboard')
            ->assertOk()
            ->assertSee(url('/reports'))
            ->assertSee('Riwayat lengkap');

        $dashboardOnly = User::factory()->withPermissions(Permission::DashboardView)->assignedTo($this->a)->create();
        $this->actingAs($dashboardOnly)->get('/dashboard')
            ->assertOk()
            ->assertDontSee(url('/reports'))
            ->assertDontSee('Riwayat lengkap');
    }
}
