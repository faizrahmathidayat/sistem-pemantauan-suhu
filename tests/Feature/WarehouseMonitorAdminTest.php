<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarehouseMonitorAdminTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang Layar']);
    }

    private function manager(Warehouse ...$warehouses): User
    {
        return User::factory()->withPermissions(Permission::WarehouseManage)->assignedTo(...$warehouses)->create();
    }

    private function issue(User $actor): string
    {
        $this->actingAs($actor)->post(route('warehouses.monitor.store', $this->warehouse))->assertRedirect(route('warehouses.edit', $this->warehouse));

        return (string) $this->warehouse->fresh()->monitorUrl();
    }

    private function path(string $url): string
    {
        return parse_url($url, PHP_URL_PATH);
    }

    public function test_store_issues_a_token_and_the_full_url_stays_visible_on_the_edit_page(): void
    {
        $actor = $this->manager($this->warehouse);
        $url = $this->issue($actor);
        $token = basename($url);

        $this->assertSame(route('monitor.show', $token, false), $this->path($url));
        $this->assertSame(40, strlen($token));

        $this->get(route('warehouses.edit', $this->warehouse))->assertOk()->assertSee($url, false);
        $this->get(route('warehouses.edit', $this->warehouse))->assertOk()->assertSee($url, false);
        $this->get(route('warehouses.index'))->assertOk()->assertDontSee($token, false);
    }

    public function test_edit_page_showing_the_url_is_not_cacheable(): void
    {
        $this->issue($this->manager($this->warehouse));

        $response = $this->get(route('warehouses.edit', $this->warehouse))->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_url_is_not_shown_to_someone_without_access_to_that_warehouse(): void
    {
        $this->warehouse->issueMonitorToken();
        $url = $this->warehouse->fresh()->monitorUrl();
        $noPermission = User::factory()->withPermissions(Permission::DashboardView)->assignedTo($this->warehouse)->create();
        $wrongWarehouse = $this->manager(Warehouse::factory()->create());

        foreach ([$noPermission, $wrongWarehouse] as $user) {
            $response = $this->actingAs($user)->get(route('warehouses.edit', $this->warehouse))->assertForbidden();
            $this->assertStringNotContainsString($url, $response->getContent());
        }
    }

    public function test_active_address_without_a_stored_copy_asks_to_be_recreated(): void
    {
        $this->warehouse->issueMonitorToken();
        DB::table('warehouses')->where('id', $this->warehouse->id)->update(['monitor_token' => null]);

        $this->actingAs($this->manager($this->warehouse))->get(route('warehouses.edit', $this->warehouse))->assertOk()
            ->assertSee('Alamat monitor aktif')
            ->assertSee('Buat ulang alamat untuk melihatnya');
    }

    public function test_url_works_and_old_one_stops_after_reissue(): void
    {
        $actor = $this->manager($this->warehouse);
        $first = $this->path($this->issue($actor));
        $this->get($first)->assertOk();

        $second = $this->path($this->issue($actor));

        $this->assertNotSame($first, $second);
        $this->get($first)->assertNotFound();
        $this->get($second)->assertOk();
    }

    public function test_destroy_revokes_and_a_second_destroy_is_a_harmless_noop(): void
    {
        $actor = $this->manager($this->warehouse);
        $path = $this->path($this->issue($actor));

        $this->actingAs($actor)->delete(route('warehouses.monitor.destroy', $this->warehouse))->assertRedirect(route('warehouses.edit', $this->warehouse));
        $this->get($path)->assertNotFound();
        $this->assertFalse($this->warehouse->fresh()->hasMonitorToken());

        $this->actingAs($actor)->delete(route('warehouses.monitor.destroy', $this->warehouse))->assertRedirect(route('warehouses.edit', $this->warehouse));
        $this->assertSame(1, AuditLog::where('event', 'warehouse.monitor_revoked')->count());
    }

    public function test_actions_are_recorded_in_the_audit_log_without_secrets(): void
    {
        $actor = $this->manager($this->warehouse);
        $token = basename($this->issue($actor));
        $this->actingAs($actor)->delete(route('warehouses.monitor.destroy', $this->warehouse));

        $issued = AuditLog::where('event', 'warehouse.monitor_issued')->sole();
        $revoked = AuditLog::where('event', 'warehouse.monitor_revoked')->sole();

        $this->assertSame('Gudang Layar', $issued->subject_label);
        $this->assertSame($actor->id, $issued->user_id);
        $this->assertArrayHasKey('monitor', $issued->diff);
        $this->assertArrayHasKey('monitor', $revoked->diff);
        $this->assertSame('Membuat alamat monitor', $issued->eventLabel());
        $this->assertSame('Mematikan alamat monitor', $revoked->eventLabel());

        $everything = json_encode([$issued->diff, $revoked->diff]);
        $this->assertStringNotContainsString($token, $everything);
        $this->assertStringNotContainsString(hash('sha256', $token), $everything);
    }

    public function test_requires_warehouse_manage_and_access_to_that_warehouse(): void
    {
        $noPermission = User::factory()->withPermissions(Permission::DashboardView)->assignedTo($this->warehouse)->create();
        $otherWarehouse = Warehouse::factory()->create();
        $wrongWarehouse = $this->manager($otherWarehouse);

        foreach ([$noPermission, $wrongWarehouse] as $user) {
            $this->actingAs($user)->post(route('warehouses.monitor.store', $this->warehouse))->assertForbidden();
            $this->actingAs($user)->delete(route('warehouses.monitor.destroy', $this->warehouse))->assertForbidden();
        }
        $this->assertFalse($this->warehouse->fresh()->hasMonitorToken());

        $this->actingAs(User::factory()->create())->post(route('warehouses.monitor.store', $this->warehouse))->assertRedirect();
        $this->assertTrue($this->warehouse->fresh()->hasMonitorToken());
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->post(route('warehouses.monitor.store', $this->warehouse))->assertRedirect('/login');
        $this->delete(route('warehouses.monitor.destroy', $this->warehouse))->assertRedirect('/login');
    }

    public function test_edit_page_shows_status_and_the_right_buttons(): void
    {
        $actor = $this->manager($this->warehouse);

        $this->actingAs($actor)->get(route('warehouses.edit', $this->warehouse))->assertOk()
            ->assertSee('Layar monitor')
            ->assertSee('Buat alamat monitor')
            ->assertDontSee('Buat ulang alamat')
            ->assertDontSee('Matikan alamat');

        $this->warehouse->issueMonitorToken();
        $hash = $this->warehouse->fresh()->monitor_token_hash;

        $this->actingAs($actor)->get(route('warehouses.edit', $this->warehouse))->assertOk()
            ->assertSee('Buat ulang alamat')
            ->assertSee('Matikan alamat')
            ->assertDontSee('Buat alamat monitor')
            ->assertDontSee($hash, false);
    }
}
