<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->withPermissions(Permission::RoleManage)->create();
    }

    /**
     * @return list<string>
     */
    private function permissionValues(Role $role): array
    {
        $values = array_map(fn (Permission $p) => $p->value, $role->permissions());
        sort($values);

        return $values;
    }

    public function test_index_lists_roles_with_user_counts(): void
    {
        $role = Role::factory()->create(['name' => 'Staf Cikokol']);
        User::factory()->count(2)->withRole($role)->create();

        $this->actingAs($this->actor())->get('/roles')
            ->assertOk()
            ->assertSee('Staf Cikokol')
            ->assertSee('2 user')
            ->assertSee('Administrator')
            ->assertSee('Sistem');
    }

    public function test_store_creates_role_with_selected_permissions(): void
    {
        $this->actingAs($this->actor())
            ->post('/roles', ['name' => 'Kepala Gudang', 'permissions' => ['zone.view', 'dashboard.view']])
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('status');

        $role = Role::where('name', 'Kepala Gudang')->sole();
        $this->assertFalse($role->is_system);
        $this->assertSame(['dashboard.view', 'zone.view'], $this->permissionValues($role));
    }

    public function test_name_must_be_unique_and_required(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/roles', ['name' => 'Operator', 'permissions' => []])
            ->assertSessionHasErrors('name');
        $this->actingAs($actor)->post('/roles', ['name' => '', 'permissions' => []])
            ->assertSessionHasErrors('name');
    }

    public function test_forged_permission_values_are_rejected(): void
    {
        $this->actingAs($this->actor())
            ->post('/roles', ['name' => 'Palsu', 'permissions' => ['bukan.izin']])
            ->assertSessionHasErrors('permissions.0');

        $this->assertNull(Role::where('name', 'Palsu')->first());
    }

    public function test_update_changes_name_and_replaces_permissions(): void
    {
        $role = Role::factory()->withPermissions(Permission::ZoneView, Permission::ZoneUpdate)->create(['name' => 'Lama']);

        $this->actingAs($this->actor())
            ->put("/roles/{$role->id}", ['name' => 'Baru', 'permissions' => ['auditlog.view']])
            ->assertRedirect(route('roles.index'));

        $role->refresh();
        $this->assertSame('Baru', $role->name);
        $this->assertSame(['auditlog.view'], $this->permissionValues($role));
    }

    public function test_system_role_cannot_be_edited_or_deleted(): void
    {
        $administrator = Role::administrator();
        $actor = $this->actor();

        $this->actingAs($actor)->get("/roles/{$administrator->id}/edit")->assertForbidden();
        $this->actingAs($actor)->put("/roles/{$administrator->id}", ['name' => 'Diubah', 'permissions' => []])->assertForbidden();
        $this->actingAs($actor)->delete("/roles/{$administrator->id}")->assertForbidden();

        $this->assertSame('Administrator', $administrator->fresh()->name);
    }

    public function test_role_in_use_cannot_be_deleted(): void
    {
        $role = Role::factory()->create();
        User::factory()->withRole($role)->create();

        $this->actingAs($this->actor())->delete("/roles/{$role->id}")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertStringContainsString('1 user', session('error'));
        $this->assertNotNull($role->fresh());
    }

    public function test_unused_role_can_be_deleted(): void
    {
        $role = Role::factory()->withPermissions(Permission::ZoneView)->create();

        $this->actingAs($this->actor())->delete("/roles/{$role->id}")
            ->assertRedirect(route('roles.index'))
            ->assertSessionHas('status');

        $this->assertNull(Role::find($role->id));
        $this->assertSame(0, DB::table('role_permissions')->where('role_id', $role->id)->count());
    }

    public function test_edit_form_shows_permission_checkboxes_grouped_by_feature(): void
    {
        $role = Role::factory()->withPermissions(Permission::ZoneView)->create();

        $response = $this->actingAs($this->actor())->get("/roles/{$role->id}/edit")->assertOk();
        $response->assertSee('name="permissions[]"', false)
            ->assertSee('value="zone.view" checked', false)
            ->assertDontSee('value="zone.update" checked', false)
            ->assertSee('Insiden')
            ->assertSee('Zona');
        foreach (Permission::cases() as $permission) {
            $response->assertSee($permission->label());
        }
    }

    public function test_pages_require_role_manage_permission(): void
    {
        $user = User::factory()->withPermissions(Permission::ZoneView)->create();
        $role = Role::factory()->create();

        $this->actingAs($user)->get('/roles')->assertForbidden();
        $this->actingAs($user)->get('/roles/create')->assertForbidden();
        $this->actingAs($user)->post('/roles', ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->get("/roles/{$role->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/roles/{$role->id}", ['name' => 'X'])->assertForbidden();
        $this->actingAs($user)->delete("/roles/{$role->id}")->assertForbidden();
    }
}
