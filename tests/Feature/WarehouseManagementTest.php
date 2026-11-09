<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarehouseManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(Warehouse ...$warehouses): User
    {
        return User::factory()->withPermissions(Permission::WarehouseManage)->assignedTo(...$warehouses)->create();
    }

    public function test_index_lists_only_visible_warehouses_with_zone_counts(): void
    {
        $a = Warehouse::factory()->create(['name' => 'Gudang Cikokol']);
        $b = Warehouse::factory()->create(['name' => 'Gudang BSD']);
        Zone::factory()->count(2)->create(['warehouse_id' => $a->id]);
        Zone::factory()->create(['warehouse_id' => $b->id]);

        $this->actingAs($this->actor($a))->get('/warehouses')
            ->assertOk()
            ->assertSee('Gudang Cikokol')
            ->assertSee('2 zona')
            ->assertDontSee('Gudang BSD');

        $this->actingAs(User::factory()->create())->get('/warehouses')->assertSee('Gudang Cikokol')->assertSee('Gudang BSD');
    }

    public function test_store_creates_warehouse_and_auto_assigns_a_non_administrator_creator(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/warehouses', ['name' => 'Gudang Baru'])
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('status');

        $warehouse = Warehouse::where('name', 'Gudang Baru')->sole();
        $this->assertSame([$warehouse->id], $actor->warehouses()->pluck('warehouses.id')->all());
    }

    public function test_administrator_creator_is_not_assigned_explicitly(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)->post('/warehouses', ['name' => 'Gudang Baru'])->assertRedirect(route('warehouses.index'));

        $this->assertSame(0, $admin->warehouses()->count());
        $this->assertSame(1, Warehouse::where('name', 'Gudang Baru')->count());
    }

    public function test_name_is_required_and_unique(): void
    {
        Warehouse::factory()->create(['name' => 'Gudang Cikokol']);
        $actor = $this->actor();

        $this->actingAs($actor)->post('/warehouses', ['name' => ''])->assertSessionHasErrors('name');
        $this->actingAs($actor)->post('/warehouses', ['name' => 'Gudang Cikokol'])->assertSessionHasErrors('name');
    }

    public function test_update_renames_warehouse(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Lama']);

        $this->actingAs($this->actor($warehouse))->put("/warehouses/{$warehouse->id}", ['name' => 'Baru'])
            ->assertRedirect(route('warehouses.index'));

        $this->assertSame('Baru', $warehouse->fresh()->name);
    }

    public function test_cannot_manage_a_warehouse_the_actor_cannot_access(): void
    {
        $mine = Warehouse::factory()->create();
        $other = Warehouse::factory()->create(['name' => 'Milik Orang']);
        $actor = $this->actor($mine);

        $this->actingAs($actor)->get("/warehouses/{$other->id}/edit")->assertForbidden();
        $this->actingAs($actor)->put("/warehouses/{$other->id}", ['name' => 'Diambil'])->assertForbidden();
        $this->actingAs($actor)->delete("/warehouses/{$other->id}")->assertForbidden();

        $this->assertSame('Milik Orang', $other->fresh()->name);
    }

    public function test_warehouse_with_zones_cannot_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->create();
        Zone::factory()->count(2)->create(['warehouse_id' => $warehouse->id]);

        $this->actingAs($this->actor($warehouse))->delete("/warehouses/{$warehouse->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('2 zona', session('error'));
        $this->assertNotNull($warehouse->fresh());
    }

    public function test_empty_warehouse_can_be_deleted(): void
    {
        $warehouse = Warehouse::factory()->create();
        $actor = $this->actor($warehouse);

        $this->actingAs($actor)->delete("/warehouses/{$warehouse->id}")
            ->assertRedirect(route('warehouses.index'))
            ->assertSessionHas('status');

        $this->assertNull(Warehouse::find($warehouse->id));
        $this->assertSame(0, DB::table('warehouse_user')->where('warehouse_id', $warehouse->id)->count());
    }

    public function test_pages_require_warehouse_manage_permission(): void
    {
        $user = User::factory()->withPermissions(Permission::ZoneView)->create();
        $warehouse = Warehouse::factory()->create();

        $this->actingAs($user)->get('/warehouses')->assertForbidden();
        $this->actingAs($user)->get('/warehouses/create')->assertForbidden();
        $this->actingAs($user)->post('/warehouses', ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get("/warehouses/{$warehouse->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/warehouses/{$warehouse->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete("/warehouses/{$warehouse->id}")->assertForbidden();
    }

    public function test_new_permissions_appear_on_the_role_form_and_administrator_has_them(): void
    {
        $this->assertTrue(Role::administrator()->grants(Permission::WarehouseManage));
        $this->assertTrue(Role::administrator()->grants(Permission::ZoneManage));
        $this->assertSame('warehouse.manage', Permission::WarehouseManage->value);
        $this->assertSame('zone.manage', Permission::ZoneManage->value);

        $this->actingAs(User::factory()->create())->get('/roles/create')
            ->assertOk()
            ->assertSee('Mengelola gudang')
            ->assertSee('Mengelola zona dan produk')
            ->assertSee('value="warehouse.manage"', false)
            ->assertSee('value="zone.manage"', false);
    }

    public function test_warehouse_events_are_recorded_in_the_audit_log(): void
    {
        $actor = User::factory()->create(['name' => 'Budi Santoso']);

        $this->actingAs($actor)->post('/warehouses', ['name' => 'Gudang Baru']);
        $created = AuditLog::where('event', 'warehouse.created')->sole();
        $this->assertSame('Budi Santoso', $created->user_name);
        $this->assertSame('Gudang Baru', $created->subject_label);
        $this->assertSame('Gudang Baru', $created->diff['name']['to']);

        $warehouse = Warehouse::where('name', 'Gudang Baru')->sole();

        // Menyimpan tanpa perubahan nama tidak mencatat apa pun.
        $this->actingAs($actor)->put("/warehouses/{$warehouse->id}", ['name' => 'Gudang Baru']);
        $this->assertSame(0, AuditLog::where('event', 'warehouse.updated')->count());

        $this->actingAs($actor)->put("/warehouses/{$warehouse->id}", ['name' => 'Gudang Renamed']);
        $updated = AuditLog::where('event', 'warehouse.updated')->sole();
        $this->assertSame('Gudang Baru', $updated->diff['name']['from']);
        $this->assertSame('Gudang Renamed', $updated->diff['name']['to']);

        $this->actingAs($actor)->delete("/warehouses/{$warehouse->id}");
        $deleted = AuditLog::where('event', 'warehouse.deleted')->sole();
        $this->assertSame('Gudang Renamed', $deleted->subject_label);
        $this->assertSame('Gudang Renamed', $deleted->diff['name']['from']);
    }
}
