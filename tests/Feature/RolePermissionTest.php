<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\StarterRoles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<Permission>  $permissions
     * @return list<string>
     */
    private function values(array $permissions): array
    {
        $values = array_map(fn (Permission $p) => $p->value, $permissions);
        sort($values);

        return $values;
    }

    public function test_migration_creates_starter_roles_with_expected_permissions(): void
    {
        $administrator = Role::where('name', 'Administrator')->sole();
        $this->assertTrue($administrator->is_system);

        $supervisor = Role::where('name', 'Supervisor')->sole();
        $this->assertFalse($supervisor->is_system);
        $this->assertSame([
            'auditlog.view', 'dashboard.view', 'incident.acknowledge', 'incident.view',
            'notification.view', 'zone.update', 'zone.view',
        ], $this->values($supervisor->permissions()));

        $operator = Role::where('name', 'Operator')->sole();
        $this->assertSame([
            'dashboard.view', 'incident.acknowledge', 'incident.view', 'notification.view',
        ], $this->values($operator->permissions()));
    }

    public function test_install_assigns_users_without_role_to_administrator(): void
    {
        $orphan = User::factory()->withoutRole()->create();
        $this->assertNull($orphan->role_id);

        StarterRoles::install();
        StarterRoles::install();

        $this->assertSame(Role::where('name', 'Administrator')->sole()->id, $orphan->refresh()->role_id);
        $this->assertSame(1, Role::where('name', 'Administrator')->count());
        $this->assertSame(1, Role::where('name', 'Supervisor')->count());
    }

    public function test_administrator_grants_every_permission(): void
    {
        $administrator = Role::administrator();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue($administrator->grants($permission), $permission->value);
        }
    }

    public function test_regular_role_grants_only_its_permissions(): void
    {
        $role = Role::factory()->create();
        $role->syncPermissions([Permission::ZoneView, Permission::DashboardView]);

        $this->assertTrue($role->grants(Permission::ZoneView));
        $this->assertTrue($role->grants(Permission::DashboardView));
        $this->assertFalse($role->grants(Permission::ZoneUpdate));
        $this->assertFalse($role->grants(Permission::RoleManage));
    }

    public function test_user_without_role_has_no_permission(): void
    {
        $user = User::factory()->withoutRole()->create();

        foreach (Permission::cases() as $permission) {
            $this->assertFalse($user->hasPermission($permission), $permission->value);
        }
        $this->assertFalse($user->isAdministrator());
    }

    public function test_gates_follow_the_users_role(): void
    {
        $viewer = User::factory()->withPermissions(Permission::ZoneView)->create();

        $this->assertTrue($viewer->can('zone.view'));
        $this->assertFalse($viewer->can('zone.update'));
        $this->assertFalse($viewer->isAdministrator());
        $this->assertTrue(User::factory()->create()->can('role.manage'));
        $this->assertTrue(User::factory()->create()->isAdministrator());
    }

    public function test_with_permission_scope_returns_administrators_and_matching_roles_only(): void
    {
        User::query()->delete();
        $admin = User::factory()->create();
        $match = User::factory()->withPermissions(Permission::NotificationView, Permission::ZoneView)->create();
        User::factory()->withPermissions(Permission::ZoneView)->create();
        User::factory()->withoutRole()->create();

        $ids = User::withPermission(Permission::NotificationView)->pluck('id')->sort()->values()->all();

        $this->assertSame(collect([$admin->id, $match->id])->sort()->values()->all(), $ids);
    }

    public function test_sync_permissions_replaces_the_previous_set(): void
    {
        $role = Role::factory()->create();
        $role->syncPermissions([Permission::ZoneView, Permission::ZoneUpdate]);
        $role->syncPermissions([Permission::AuditLogView]);

        $this->assertSame(['auditlog.view'], $this->values($role->permissions()));
        $this->assertSame(['auditlog.view'], $this->values($role->fresh()->permissions()));
    }

    public function test_factory_default_user_is_administrator(): void
    {
        $user = User::factory()->create();

        $this->assertSame(Role::administrator()->id, $user->role_id);
    }
}
