<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\DashboardSnapshot;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class WarehouseSchemaTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'database/migrations/2026_11_09_130001_create_warehouses_and_zone_products.php';

    public function test_zone_belongs_to_a_warehouse_and_lists_products_in_order(): void
    {
        $zone = Zone::factory()->create();

        $zone->syncProducts(['Telur A', 'Telur B']);
        $this->assertSame(['Telur A', 'Telur B'], $zone->products()->pluck('name')->all());
        $this->assertSame($zone->warehouse_id, $zone->warehouse->id);

        $zone->syncProducts(['Telur B']);
        $this->assertSame(['Telur B'], $zone->fresh()->products->pluck('name')->all());
    }

    public function test_warehouse_has_zones_and_users(): void
    {
        $warehouse = Warehouse::factory()->create();
        Zone::factory()->count(2)->create(['warehouse_id' => $warehouse->id]);
        $user = User::factory()->create();
        $warehouse->users()->attach($user);

        $this->assertCount(2, $warehouse->zones);
        $this->assertSame([$user->id], $warehouse->users->pluck('id')->all());
    }

    public function test_warehouse_name_is_unique(): void
    {
        Warehouse::factory()->create(['name' => 'Gudang Cikokol']);

        $this->expectException(QueryException::class);
        Warehouse::factory()->create(['name' => 'Gudang Cikokol']);
    }

    public function test_zone_name_is_unique_within_a_warehouse_but_not_across(): void
    {
        $a = Warehouse::factory()->create();
        $b = Warehouse::factory()->create();
        Zone::factory()->create(['warehouse_id' => $a->id, 'name' => 'Zona FG']);
        Zone::factory()->create(['warehouse_id' => $b->id, 'name' => 'Zona FG']);

        $this->expectException(QueryException::class);
        Zone::factory()->create(['warehouse_id' => $a->id, 'name' => 'Zona FG']);
    }

    public function test_deleting_a_zone_removes_its_products(): void
    {
        $zone = Zone::factory()->create();
        $zone->syncProducts(['Telur A']);

        $zone->delete();

        $this->assertSame(0, DB::table('zone_products')->count());
    }

    public function test_warehouse_with_zones_cannot_be_deleted_at_database_level(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $this->markTestSkipped('FK zones.warehouse_id hanya dibuat di database selain SQLite; di SQLite ditegakkan aplikasi (Task 4).');
        }

        $zone = Zone::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('warehouses')->where('id', $zone->warehouse_id)->delete();
    }

    public function test_fresh_install_has_no_default_warehouse(): void
    {
        $this->assertSame(0, Warehouse::count());
    }

    public function test_migration_moves_existing_zones_into_a_default_warehouse(): void
    {
        $this->artisan('migrate:rollback', ['--path' => self::MIGRATION])->assertSuccessful();
        $this->assertTrue(Schema::hasColumn('zones', 'product_type'));
        $this->assertFalse(Schema::hasColumn('zones', 'warehouse_id'));

        $now = now()->toDateTimeString();
        $operatorId = Role::where('name', 'Operator')->value('id');
        $adminId = Role::administrator()->id;
        DB::table('users')->insert([
            ['name' => 'Operator', 'email' => 'op@example.com', 'password' => 'x', 'role_id' => $operatorId, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'x', 'role_id' => $adminId, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $zoneId = DB::table('zones')->insertGetId([
            'name' => 'Zona A (Telur)', 'product_type' => 'telur', 'device_id' => 'gudang-zona-a',
            'device_token_hash' => str_repeat('a', 64), 'temp_min' => 10, 'temp_max' => 20,
            'created_at' => $now, 'updated_at' => $now,
        ]);
        DB::table('incidents')->insert(['zone_id' => $zoneId, 'reason' => 'temp_high', 'started_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('sensor_logs')->insert(['device_id' => 'gudang-zona-a', 'temperature' => 15, 'humidity' => 70, 'created_at' => $now, 'updated_at' => $now]);

        $this->artisan('migrate', ['--path' => self::MIGRATION])->assertSuccessful();

        $warehouse = DB::table('warehouses')->sole();
        $this->assertSame('Gudang Utama', $warehouse->name);
        $zone = DB::table('zones')->sole();
        $this->assertSame($warehouse->id, $zone->warehouse_id);
        $this->assertSame(str_repeat('a', 64), $zone->device_token_hash);
        $this->assertSame('gudang-zona-a', $zone->device_id);
        $this->assertFalse(Schema::hasColumn('zones', 'product_type'));
        $this->assertSame(['Telur'], DB::table('zone_products')->where('zone_id', $zoneId)->pluck('name')->all());
        $this->assertSame(1, DB::table('incidents')->where('zone_id', $zoneId)->count());
        $this->assertSame(1, DB::table('sensor_logs')->where('device_id', 'gudang-zona-a')->count());
        $this->assertSame(
            [DB::table('users')->where('email', 'op@example.com')->value('id')],
            DB::table('warehouse_user')->where('warehouse_id', $warehouse->id)->pluck('user_id')->all(),
        );
    }

    public function test_dashboard_snapshot_zone_has_warehouse_id_and_product_names(): void
    {
        $zone = Zone::factory()->create();
        $zone->syncProducts(['Telur A', 'Telur B']);

        $row = app(DashboardSnapshot::class)->build(User::factory()->create())['zones'][0];

        $this->assertSame($zone->warehouse_id, $row['warehouse_id']);
        $this->assertSame(['Telur A', 'Telur B'], $row['products']);
        $this->assertArrayNotHasKey('product_type', $row);
    }
}
