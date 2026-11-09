<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Role;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\IncidentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WarehouseAccessTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $a;

    private Warehouse $b;

    private Zone $zoneA;

    private Zone $zoneB;

    private Incident $incidentA;

    private Incident $incidentB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Warehouse::factory()->create(['name' => 'Gudang A']);
        $this->b = Warehouse::factory()->create(['name' => 'Gudang B']);
        $this->zoneA = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona A1', 'device_id' => 'dev-a']);
        $this->zoneB = Zone::factory()->create(['warehouse_id' => $this->b->id, 'name' => 'Zona B1', 'device_id' => 'dev-b']);
        $this->incidentA = Incident::factory()->for($this->zoneA)->create();
        $this->incidentB = Incident::factory()->for($this->zoneB)->create();
    }

    private function operator(Warehouse ...$warehouses): User
    {
        return User::factory()->withRole(Role::where('name', 'Operator')->sole())->assignedTo(...$warehouses)->create();
    }

    private function supervisor(Warehouse ...$warehouses): User
    {
        return User::factory()->withRole(Role::where('name', 'Supervisor')->sole())->assignedTo(...$warehouses)->create();
    }

    public function test_dashboard_data_contains_only_assigned_warehouses(): void
    {
        $data = $this->actingAs($this->operator($this->a))->getJson('/dashboard/data')->assertOk()->json();

        $this->assertSame([$this->zoneA->id], collect($data['zones'])->pluck('id')->all());
        $this->assertSame([$this->incidentA->id], collect($data['banners'])->pluck('incident_id')->all());
    }

    public function test_administrator_sees_every_warehouse_without_assignments(): void
    {
        $admin = User::factory()->create();
        $this->assertSame(0, $admin->warehouses()->count());

        $data = $this->actingAs($admin)->getJson('/dashboard/data')->assertOk()->json();

        $this->assertEqualsCanonicalizing([$this->zoneA->id, $this->zoneB->id], collect($data['zones'])->pluck('id')->all());
        $this->assertCount(2, $data['banners']);
    }

    public function test_user_with_two_warehouses_sees_both(): void
    {
        $data = $this->actingAs($this->operator($this->a, $this->b))->getJson('/dashboard/data')->assertOk()->json();

        $this->assertEqualsCanonicalizing([$this->zoneA->id, $this->zoneB->id], collect($data['zones'])->pluck('id')->all());
    }

    public function test_unassigned_non_administrator_gets_empty_data_not_an_error(): void
    {
        $data = $this->actingAs($this->operator())->getJson('/dashboard/data')->assertOk()->json();

        $this->assertSame([], $data['zones']);
        $this->assertSame([], $data['banners']);
    }

    public function test_readings_for_another_warehouse_zone_is_forbidden_json(): void
    {
        $user = $this->operator($this->a);

        $this->actingAs($user)->getJson("/zones/{$this->zoneB->id}/readings")->assertForbidden()->assertJsonStructure(['message']);
        $this->actingAs($user)->getJson("/zones/{$this->zoneA->id}/readings")->assertOk();
    }

    public function test_incident_log_lists_only_assigned_warehouse_incidents(): void
    {
        $incidents = $this->actingAs($this->operator($this->a))->get('/incidents')->assertOk()->viewData('incidents');

        $this->assertSame([$this->incidentA->id], $incidents->pluck('id')->all());
    }

    public function test_acknowledge_in_another_warehouse_is_forbidden_and_leaves_incident_open(): void
    {
        $user = $this->operator($this->a);

        $this->actingAs($user)->postJson("/incidents/{$this->incidentB->id}/acknowledge")->assertForbidden();
        $this->assertNull($this->incidentB->fresh()->acknowledged_by);

        $this->actingAs($user)->postJson("/incidents/{$this->incidentA->id}/acknowledge")->assertOk();
        $this->assertSame($user->id, $this->incidentA->fresh()->acknowledged_by);
    }

    public function test_zone_limits_list_and_edit_are_scoped(): void
    {
        $user = $this->supervisor($this->a);

        $this->actingAs($user)->get('/zones')->assertOk()->assertSee('Zona A1')->assertDontSee('Zona B1');
        $this->actingAs($user)->get("/zones/{$this->zoneA->id}/edit")->assertOk();
        $this->actingAs($user)->get("/zones/{$this->zoneB->id}/edit")->assertForbidden();

        $before = $this->zoneB->temp_max;
        $this->actingAs($user)->put("/zones/{$this->zoneB->id}", [
            'temp_min' => 1, 'temp_max' => 9, 'humidity_min' => 60, 'humidity_max' => 85,
            'warning_margin' => 1, 'humidity_warning_margin' => 2,
        ])->assertForbidden();
        $this->assertSame($before, $this->zoneB->fresh()->temp_max);
    }

    public function test_recent_incidents_on_dashboard_are_scoped(): void
    {
        $recent = $this->actingAs($this->operator($this->a))->get('/dashboard')->assertOk()->viewData('recentIncidents');

        $this->assertSame([$this->incidentA->id], $recent->pluck('id')->all());
    }

    public function test_alerts_go_only_to_assigned_users_and_administrators(): void
    {
        $this->incidentB->delete();
        $admin = User::factory()->create();
        $onlyA = $this->operator($this->a);
        $onlyB = $this->operator($this->b);
        $both = $this->operator($this->a, $this->b);

        foreach ([0, 30] as $offset) {
            $this->travel($offset)->seconds();
            $log = SensorLog::factory()->create(['device_id' => 'dev-b', 'temperature' => 26, 'humidity' => 88]);
            app(IncidentManager::class)->handleReading($this->zoneB->fresh(), $log);
        }

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $onlyB->notifications()->count());
        $this->assertSame(1, $both->notifications()->count());
        $this->assertSame(0, $onlyA->notifications()->count());
    }
}
