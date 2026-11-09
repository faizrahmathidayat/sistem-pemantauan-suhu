<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Incident;
use App\Models\Role;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use App\Services\DashboardSnapshot;
use App\Services\IncidentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    private Incident $incident;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::factory()->create([
            'device_id' => 'gudang-zona-b', 'temp_min' => 15, 'temp_max' => 25,
            'humidity_min' => 80, 'humidity_max' => 95,
        ]);
        $this->incident = Incident::factory()->for($this->zone)->create(['ended_at' => null]);
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: Permission}>
     */
    public static function protectedRoutes(): array
    {
        return [
            'dashboard' => ['GET', '/dashboard', Permission::DashboardView],
            'dashboard data' => ['GET', '/dashboard/data', Permission::DashboardView],
            'zone readings' => ['GET', '/zones/{zone}/readings', Permission::DashboardView],
            'incidents' => ['GET', '/incidents', Permission::IncidentView],
            'acknowledge' => ['POST', '/incidents/{incident}/acknowledge', Permission::IncidentAcknowledge],
            'notifications' => ['GET', '/notifications', Permission::NotificationView],
            'notifications read all' => ['POST', '/notifications/read-all', Permission::NotificationView],
            'notification read one' => ['POST', '/notifications/abc/read', Permission::NotificationView],
            'zones' => ['GET', '/zones', Permission::ZoneView],
            'zone edit' => ['GET', '/zones/{zone}/edit', Permission::ZoneUpdate],
            'zone update' => ['PUT', '/zones/{zone}', Permission::ZoneUpdate],
            'audit logs' => ['GET', '/audit-logs', Permission::AuditLogView],
        ];
    }

    private function requestAs(User $user, string $method, string $uri)
    {
        $uri = str_replace(['{zone}', '{incident}'], [$this->zone->id, $this->incident->id], $uri);

        return $this->actingAs($user)->call($method, $uri);
    }

    #[DataProvider('protectedRoutes')]
    public function test_each_route_requires_its_permission(string $method, string $uri, Permission $required): void
    {
        $allowed = User::factory()->withPermissions($required)->create();
        $other = collect(Permission::cases())->first(fn (Permission $p) => $p !== $required);
        $denied = User::factory()->withPermissions($other)->create();

        $this->assertNotSame(403, $this->requestAs($allowed, $method, $uri)->getStatusCode(), 'dengan izin');
        $this->assertSame(403, $this->requestAs($denied, $method, $uri)->getStatusCode(), 'tanpa izin');
    }

    public function test_polling_endpoints_return_json_403_without_permission(): void
    {
        $user = User::factory()->withPermissions(Permission::ZoneView)->create();

        $this->actingAs($user)->getJson('/dashboard/data')->assertForbidden()->assertJsonStructure(['message']);
        $this->actingAs($user)->getJson("/zones/{$this->zone->id}/readings")->assertForbidden();
    }

    public function test_user_without_role_sees_no_access_page_and_everything_else_is_forbidden(): void
    {
        $user = User::factory()->withoutRole()->create();

        $this->actingAs($user)->get('/home')->assertOk()->assertSee('belum punya hak akses');
        $this->actingAs($user)->get('/dashboard')->assertForbidden();
        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));
    }

    public function test_home_redirects_to_the_first_permitted_page(): void
    {
        $operator = User::factory()->withRole(Role::where('name', 'Operator')->sole())->create();
        $this->actingAs($operator)->get('/home')->assertRedirect(route('dashboard'));

        $zoneOnly = User::factory()->withPermissions(Permission::ZoneView)->create();
        $this->actingAs($zoneOnly)->get('/home')->assertRedirect(route('zones.index'));
    }

    public function test_navigation_only_shows_permitted_links(): void
    {
        $operator = User::factory()->withRole(Role::where('name', 'Operator')->sole())->create();
        $this->actingAs($operator)->get('/dashboard')
            ->assertOk()
            ->assertSee('Riwayat Insiden')
            ->assertDontSee('Batas Zona')
            ->assertDontSee('Log Perubahan');

        $this->actingAs(User::factory()->create())->get('/dashboard')
            ->assertSee('Batas Zona')
            ->assertSee('Log Perubahan');
    }

    public function test_bell_is_hidden_without_notification_permission(): void
    {
        $user = User::factory()->withPermissions(Permission::DashboardView)->create();

        $this->actingAs($user)->get('/dashboard')->assertOk()->assertDontSee('aria-label="Notifikasi"', false);
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertSee('aria-label="Notifikasi"', false);
    }

    public function test_forbidden_page_is_in_indonesian(): void
    {
        $user = User::factory()->withPermissions(Permission::DashboardView)->create();

        $this->actingAs($user)->get('/zones')->assertForbidden()->assertSee('tidak punya izin');
    }

    public function test_snapshot_reports_whether_user_can_acknowledge(): void
    {
        $admin = User::factory()->create();
        $viewer = User::factory()->withPermissions(Permission::DashboardView)->create();

        $this->assertTrue(app(DashboardSnapshot::class)->build($admin)['can_acknowledge']);
        $this->assertFalse(app(DashboardSnapshot::class)->build($viewer)['can_acknowledge']);
        $this->actingAs($viewer)->getJson('/dashboard/data')->assertOk()->assertJsonPath('can_acknowledge', false);
    }

    public function test_alerts_reach_only_users_with_notification_permission(): void
    {
        $admin = User::factory()->create();
        $operator = User::factory()->withRole(Role::where('name', 'Operator')->sole())->create();
        $silent = User::factory()->withPermissions(Permission::DashboardView)->create();
        $this->incident->delete();

        foreach ([0, 30] as $offset) {
            $this->travel($offset)->seconds();
            $log = SensorLog::factory()->create(['device_id' => 'gudang-zona-b', 'temperature' => 26, 'humidity' => 88]);
            app(IncidentManager::class)->handleReading($this->zone->fresh(), $log);
        }

        $this->assertSame(1, $admin->notifications()->count());
        $this->assertSame(1, $operator->notifications()->count());
        $this->assertSame(0, $silent->notifications()->count());
    }
}
