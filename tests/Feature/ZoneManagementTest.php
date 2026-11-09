<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ZoneManagementTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $a;

    private Warehouse $b;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Warehouse::factory()->create(['name' => 'Gudang Cikokol']);
        $this->b = Warehouse::factory()->create(['name' => 'Gudang BSD']);
    }

    private function actor(): User
    {
        return User::factory()->withPermissions(Permission::ZoneManage)->assignedTo($this->a)->create(['name' => 'Pengelola']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Zona FG',
            'device_id' => 'cikokol-fg',
            'temp_min' => 10, 'temp_max' => 20,
            'humidity_min' => 60, 'humidity_max' => 85,
            'warning_margin' => 2, 'humidity_warning_margin' => 5,
            'products' => "Telur Ayam Grade A\nTelur Bebek",
        ], $overrides);
    }

    private function telemetry(string $deviceId, ?string $token)
    {
        return $this->postJson('/api/v1/telemetry', ['device_id' => $deviceId, 'temperature' => 15, 'humidity' => 70],
            $token === null ? [] : ['Authorization' => 'Bearer '.$token]);
    }

    private function createZone(User $actor, array $overrides = []): array
    {
        $this->actingAs($actor)->post("/warehouses/{$this->a->id}/zones", $this->payload($overrides))->assertSessionHasNoErrors();

        return [Zone::where('device_id', $overrides['device_id'] ?? 'cikokol-fg')->sole(), session('new_token')];
    }

    public function test_store_creates_zone_with_limits_products_and_a_one_time_token(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post("/warehouses/{$this->a->id}/zones", $this->payload())
            ->assertRedirect();

        $zone = Zone::where('device_id', 'cikokol-fg')->sole();
        $token = session('new_token');
        $this->assertSame($this->a->id, $zone->warehouse_id);
        $this->assertSame(['Telur Ayam Grade A', 'Telur Bebek'], $zone->products->pluck('name')->all());
        $this->assertEquals(20, $zone->temp_max);
        $this->assertSame(48, strlen($token));
        $this->assertSame(hash('sha256', $token), $zone->device_token_hash);

        $this->actingAs($actor)->get("/zones/{$zone->id}/manage")->assertOk()->assertSee($token);
        $this->actingAs($actor)->get("/zones/{$zone->id}/manage")->assertOk()->assertDontSee($token);
    }

    public function test_new_token_authenticates_telemetry_for_the_new_zone(): void
    {
        [, $token] = $this->createZone($this->actor());

        $this->telemetry('cikokol-fg', $token)->assertCreated();
        $this->assertSame(1, SensorLog::where('device_id', 'cikokol-fg')->count());
    }

    public function test_store_validates_name_unique_per_warehouse_device_id_format_unique_and_limits(): void
    {
        Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona FG', 'device_id' => 'sudah-ada']);
        $actor = $this->actor();
        $post = fn (array $overrides) => $this->actingAs($actor)->post("/warehouses/{$this->a->id}/zones", $this->payload($overrides));

        $post(['name' => ''])->assertSessionHasErrors('name');
        $post(['name' => 'Zona FG', 'device_id' => 'baru-1'])->assertSessionHasErrors('name');
        $post(['device_id' => 'sudah-ada', 'name' => 'Lain'])->assertSessionHasErrors('device_id');
        $post(['device_id' => 'Pakai Spasi!'])->assertSessionHasErrors('device_id');
        $post(['device_id' => ''])->assertSessionHasErrors('device_id');
        $post(['temp_min' => 20, 'temp_max' => 20, 'device_id' => 'baru-2'])->assertSessionHasErrors('temp_max');
        $post(['warning_margin' => 5, 'device_id' => 'baru-3'])->assertSessionHasErrors('warning_margin');
        $post(['humidity_min' => 60, 'humidity_max' => '', 'device_id' => 'baru-4'])->assertSessionHasErrors('humidity_max');
        $post(['products' => implode("\n", array_map(fn ($i) => "Produk {$i}", range(1, 21))), 'device_id' => 'baru-5'])->assertSessionHasErrors('products');
        $post(['products' => str_repeat('x', 81), 'device_id' => 'baru-6'])->assertSessionHasErrors('products');

        $this->assertSame(1, Zone::count());
    }

    public function test_same_zone_name_is_allowed_in_another_warehouse(): void
    {
        Zone::factory()->create(['warehouse_id' => $this->b->id, 'name' => 'Zona FG', 'device_id' => 'bsd-fg']);

        $this->createZone($this->actor());

        $this->assertSame(2, Zone::where('name', 'Zona FG')->count());
    }

    public function test_device_id_cannot_be_changed_after_creation(): void
    {
        [$zone] = $this->createZone($actor = $this->actor());

        $this->actingAs($actor)->put("/zones/{$zone->id}/manage", ['name' => 'Zona FG Baru', 'device_id' => 'dibajak', 'products' => 'Telur'])
            ->assertSessionHasNoErrors();

        $this->assertSame('cikokol-fg', $zone->fresh()->device_id);
        $this->assertSame('Zona FG Baru', $zone->fresh()->name);
    }

    public function test_update_renames_zone_and_replaces_products(): void
    {
        [$zone] = $this->createZone($actor = $this->actor());

        $this->actingAs($actor)->put("/zones/{$zone->id}/manage", ['name' => 'Zona Final', 'products' => "Telur Puyuh\n\nTelur Ayam"])
            ->assertRedirect(route('zones.manage', $zone));

        $zone->refresh();
        $this->assertSame('Zona Final', $zone->name);
        $this->assertSame(['Telur Puyuh', 'Telur Ayam'], $zone->products->pluck('name')->all());
    }

    public function test_rotating_token_invalidates_the_old_one_and_never_reaches_the_audit_log(): void
    {
        [$zone, $old] = $this->createZone($actor = $this->actor());

        $this->actingAs($actor)->post("/zones/{$zone->id}/token")->assertRedirect(route('zones.manage', $zone));
        $new = session('new_token');

        $this->assertNotSame($old, $new);
        $this->telemetry('cikokol-fg', $old)->assertUnauthorized();
        $this->telemetry('cikokol-fg', $new)->assertCreated();

        $dump = AuditLog::all()->toJson();
        $this->assertStringNotContainsString($old, $dump);
        $this->assertStringNotContainsString($new, $dump);
        $this->assertStringNotContainsString(hash('sha256', $new), $dump);
        $this->assertSame(1, AuditLog::where('event', 'zone.token_rotated')->count());
    }

    public function test_delete_removes_zone_logs_incidents_and_products_but_not_other_zones(): void
    {
        [$zone, $token] = $this->createZone($actor = $this->actor());
        $this->telemetry('cikokol-fg', $token);
        Incident::factory()->for($zone)->create();
        $other = Zone::factory()->create(['warehouse_id' => $this->a->id, 'device_id' => 'cikokol-lain']);
        $other->syncProducts(['Telur']);
        SensorLog::factory()->create(['device_id' => 'cikokol-lain']);

        $this->actingAs($actor)->delete("/zones/{$zone->id}")
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertNull(Zone::find($zone->id));
        $this->assertSame(0, SensorLog::where('device_id', 'cikokol-fg')->count());
        $this->assertSame(0, Incident::where('zone_id', $zone->id)->count());
        $this->assertSame(0, DB::table('zone_products')->where('zone_id', $zone->id)->count());
        $this->assertNotNull(Zone::find($other->id));
        $this->assertSame(1, SensorLog::where('device_id', 'cikokol-lain')->count());
        $this->assertSame(1, $other->products()->count());
        $this->telemetry('cikokol-fg', $token)->assertUnauthorized();
    }

    public function test_cannot_manage_zones_of_another_warehouse(): void
    {
        $actor = $this->actor();
        $foreign = Zone::factory()->create(['warehouse_id' => $this->b->id, 'name' => 'Milik BSD', 'device_id' => 'bsd-1']);
        $hash = $foreign->device_token_hash;

        $this->actingAs($actor)->get("/warehouses/{$this->b->id}/zones/create")->assertForbidden();
        $this->actingAs($actor)->post("/warehouses/{$this->b->id}/zones", $this->payload(['device_id' => 'masuk-bsd']))->assertForbidden();
        $this->actingAs($actor)->get("/zones/{$foreign->id}/manage")->assertForbidden();
        $this->actingAs($actor)->put("/zones/{$foreign->id}/manage", ['name' => 'Diambil', 'products' => ''])->assertForbidden();
        $this->actingAs($actor)->post("/zones/{$foreign->id}/token")->assertForbidden();
        $this->actingAs($actor)->delete("/zones/{$foreign->id}")->assertForbidden();

        $foreign->refresh();
        $this->assertSame('Milik BSD', $foreign->name);
        $this->assertSame($hash, $foreign->device_token_hash);
        $this->assertNull(Zone::where('device_id', 'masuk-bsd')->first());
    }

    public function test_pages_require_zone_manage_permission(): void
    {
        $user = User::factory()->withPermissions(Permission::ZoneUpdate)->assignedTo($this->a)->create();
        $zone = Zone::factory()->create(['warehouse_id' => $this->a->id]);

        $this->actingAs($user)->get("/warehouses/{$this->a->id}/zones/create")->assertForbidden();
        $this->actingAs($user)->post("/warehouses/{$this->a->id}/zones", $this->payload())->assertForbidden();
        $this->actingAs($user)->get("/zones/{$zone->id}/manage")->assertForbidden();
        $this->actingAs($user)->put("/zones/{$zone->id}/manage", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->post("/zones/{$zone->id}/token")->assertForbidden();
        $this->actingAs($user)->delete("/zones/{$zone->id}")->assertForbidden();
    }

    public function test_zone_events_are_recorded_without_token_value(): void
    {
        [$zone, $token] = $this->createZone($actor = $this->actor());

        $created = AuditLog::where('event', 'zone.created')->sole();
        $this->assertSame('Pengelola', $created->user_name);
        $this->assertSame('Zona FG', $created->subject_label);
        $this->assertSame('cikokol-fg', $created->diff['device_id']['to']);
        $this->assertSame('Gudang Cikokol', $created->diff['warehouse']['to']);
        $this->assertSame(['Telur Ayam Grade A', 'Telur Bebek'], $created->diff['products']['to']);

        // Tanpa perubahan: tidak ada catatan baru.
        $this->actingAs($actor)->put("/zones/{$zone->id}/manage", ['name' => 'Zona FG', 'products' => "Telur Ayam Grade A\nTelur Bebek"]);
        $this->assertSame(0, AuditLog::where('event', 'zone.updated')->count());

        $this->actingAs($actor)->put("/zones/{$zone->id}/manage", ['name' => 'Zona FG', 'products' => 'Telur Puyuh']);
        $updated = AuditLog::where('event', 'zone.updated')->sole();
        $this->assertSame(['Telur Ayam Grade A', 'Telur Bebek'], $updated->diff['products']['from']);
        $this->assertSame(['Telur Puyuh'], $updated->diff['products']['to']);
        $this->assertArrayNotHasKey('name', $updated->diff);

        $this->actingAs($actor)->delete("/zones/{$zone->id}");
        $deleted = AuditLog::where('event', 'zone.deleted')->sole();
        $this->assertSame('Zona FG', $deleted->subject_label);

        $this->assertStringNotContainsString($token, AuditLog::all()->toJson());
    }

    public function test_manage_page_is_never_cached_so_the_token_cannot_reappear_via_back_navigation(): void
    {
        [$zone] = $this->createZone($actor = $this->actor());

        $response = $this->actingAs($actor)->get("/zones/{$zone->id}/manage")->assertOk();

        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_zone_list_offers_zone_creation_to_zone_managers_without_warehouse_manage(): void
    {
        $empty = Warehouse::factory()->create(['name' => 'Gudang Kosong']);
        $user = User::factory()->withPermissions(Permission::ZoneView, Permission::ZoneManage)->assignedTo($empty)->create();

        $this->actingAs($user)->get('/zones')
            ->assertOk()
            ->assertSee('Gudang Kosong')
            ->assertSee("/warehouses/{$empty->id}/zones/create", false);

        $viewOnly = User::factory()->withPermissions(Permission::ZoneView)->assignedTo($empty)->create();
        $this->actingAs($viewOnly)->get('/zones')->assertOk()->assertDontSee('/zones/create', false);
    }
}
