<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Role;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\DashboardSnapshot;
use App\Services\IncidentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MultiWarehouseDashboardTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $a;

    private Warehouse $b;

    private Zone $okZone;

    private Zone $badZone;

    private Zone $otherZone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Warehouse::factory()->create(['name' => 'Gudang Cikokol']);
        $this->b = Warehouse::factory()->create(['name' => 'Gudang BSD']);
        $this->okZone = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona Inbound', 'device_id' => 'dev-ok', 'temp_min' => 15, 'temp_max' => 25, 'humidity_min' => 80, 'humidity_max' => 95]);
        $this->badZone = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona FG', 'device_id' => 'dev-bad', 'temp_min' => 15, 'temp_max' => 25, 'humidity_min' => 80, 'humidity_max' => 95]);
        $this->otherZone = Zone::factory()->create(['warehouse_id' => $this->b->id, 'name' => 'Zona Loading', 'device_id' => 'dev-other', 'temp_min' => 15, 'temp_max' => 25, 'humidity_min' => 80, 'humidity_max' => 95]);
    }

    private function reading(string $device, float $temperature): void
    {
        SensorLog::factory()->create(['device_id' => $device, 'temperature' => $temperature, 'humidity' => 88]);
    }

    private function operator(Warehouse ...$warehouses): User
    {
        return User::factory()->withRole(Role::where('name', 'Operator')->sole())->assignedTo(...$warehouses)->create();
    }

    public function test_snapshot_lists_warehouses_with_worst_status_and_open_incident_count(): void
    {
        $this->reading('dev-ok', 20);
        $this->reading('dev-bad', 30);
        $this->reading('dev-other', 20);
        Incident::factory()->for($this->badZone)->create();

        $warehouses = collect(app(DashboardSnapshot::class)->build(User::factory()->create())['warehouses'])->keyBy('name');

        $this->assertSame('bahaya', $warehouses['Gudang Cikokol']['status']);
        $this->assertSame(1, $warehouses['Gudang Cikokol']['open_incidents']);
        $this->assertSame($this->a->id, $warehouses['Gudang Cikokol']['id']);
        $this->assertSame('aman', $warehouses['Gudang BSD']['status']);
        $this->assertSame(0, $warehouses['Gudang BSD']['open_incidents']);
    }

    public function test_warehouse_without_zones_has_no_data_status(): void
    {
        Warehouse::factory()->create(['name' => 'Gudang Kosong']);

        $warehouses = collect(app(DashboardSnapshot::class)->build(User::factory()->create())['warehouses'])->keyBy('name');

        $this->assertSame('tanpa_data', $warehouses['Gudang Kosong']['status']);
    }

    public function test_snapshot_only_lists_visible_warehouses_and_flags_missing_access(): void
    {
        $snapshot = app(DashboardSnapshot::class);

        $this->assertSame(['Gudang Cikokol'], collect($snapshot->build($this->operator($this->a))['warehouses'])->pluck('name')->all());
        $this->assertTrue($snapshot->build($this->operator($this->a))['has_access']);
        $this->assertFalse($snapshot->build($this->operator())['has_access']);
        $this->assertTrue($snapshot->build(User::factory()->create())['has_access']);
    }

    public function test_zone_rows_carry_the_warehouse_name(): void
    {
        $row = collect(app(DashboardSnapshot::class)->build($this->operator($this->b))['zones'])->sole();

        $this->assertSame('Gudang BSD', $row['warehouse_name']);
    }

    public function test_banner_names_warehouse_and_zone(): void
    {
        Incident::factory()->for($this->badZone)->create();

        $banner = app(DashboardSnapshot::class)->build(User::factory()->create())['banners'][0];

        $this->assertSame('Gudang Cikokol › Zona FG', $banner['location']);
    }

    public function test_notification_title_and_data_include_the_warehouse(): void
    {
        $admin = User::factory()->create();
        foreach ([0, 30] as $offset) {
            $this->travel($offset)->seconds();
            $log = SensorLog::factory()->create(['device_id' => 'dev-bad', 'temperature' => 26, 'humidity' => 88]);
            app(IncidentManager::class)->handleReading($this->badZone->fresh(), $log);
        }

        $notification = $admin->notifications()->sole();
        $this->assertStringContainsString('Gudang Cikokol › Zona FG', $notification->data['title']);
        $this->assertSame('Gudang Cikokol', $notification->data['warehouse_name']);
    }

    public function test_dashboard_page_embeds_visible_warehouses_and_shows_notice_when_unassigned(): void
    {
        $this->actingAs($this->operator($this->a))->get('/dashboard')
            ->assertOk()
            ->assertSee('Gudang Cikokol')
            ->assertDontSee('Gudang BSD')
            ->assertDontSee('Anda belum ditugaskan ke gudang mana pun');

        $this->actingAs($this->operator())->get('/dashboard')
            ->assertOk()
            ->assertSee('Anda belum ditugaskan ke gudang mana pun');
    }

    public function test_incident_log_and_notification_page_show_warehouse_name(): void
    {
        $admin = User::factory()->create();
        Incident::factory()->for($this->badZone)->create();
        foreach ([0, 30] as $offset) {
            $this->travel($offset)->seconds();
            $log = SensorLog::factory()->create(['device_id' => 'dev-other', 'temperature' => 26, 'humidity' => 88]);
            app(IncidentManager::class)->handleReading($this->otherZone->fresh(), $log);
        }

        $this->actingAs($admin)->get('/incidents')->assertOk()->assertSee('Gudang Cikokol')->assertSee('Zona FG');
        $this->actingAs($admin)->get('/notifications')->assertOk()->assertSee('Gudang BSD › Zona Loading');
    }

    public function test_zone_limits_page_groups_zones_by_warehouse(): void
    {
        $this->actingAs(User::factory()->create())->get('/zones')
            ->assertOk()
            ->assertSeeInOrder(['Gudang BSD', 'Zona Loading', 'Gudang Cikokol', 'Zona FG'])
            ->assertSeeInOrder(['Gudang Cikokol', 'Zona FG', 'Zona Inbound']);
    }
}
