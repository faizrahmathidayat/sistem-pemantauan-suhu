<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserWarehouseAssignmentTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $a;

    private Warehouse $b;

    private Warehouse $c;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Warehouse::factory()->create(['name' => 'Gudang Cikokol']);
        $this->b = Warehouse::factory()->create(['name' => 'Gudang BSD']);
        $this->c = Warehouse::factory()->create(['name' => 'Gudang Tangerang']);
    }

    private function operatorRole(): Role
    {
        return Role::where('name', 'Operator')->sole();
    }

    private function manager(Warehouse ...$warehouses): User
    {
        return User::factory()->withPermissions(Permission::UserManage)->assignedTo(...$warehouses)->create(['name' => 'Pengelola']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sari Dewi', 'email' => 'sari@example.com', 'password' => 'rahasia123',
            'role_id' => $this->operatorRole()->id,
        ], $overrides);
    }

    /**
     * @return list<int>
     */
    private function assigned(User $user): array
    {
        return $user->warehouses()->pluck('warehouses.id')->sort()->values()->all();
    }

    public function test_store_assigns_selected_warehouses(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/users', $this->payload(['warehouses' => [$this->a->id, $this->b->id]]))
            ->assertSessionHasNoErrors();

        $user = User::where('email', 'sari@example.com')->sole();
        $this->assertSame([$this->a->id, $this->b->id], $this->assigned($user));
    }

    public function test_update_replaces_assignment_for_administrator_actor(): void
    {
        $user = User::factory()->withRole($this->operatorRole())->assignedTo($this->a, $this->b)->create();

        $this->actingAs(User::factory()->create())->put("/users/{$user->id}", $this->payload([
            'name' => $user->name, 'email' => $user->email, 'password' => '', 'warehouses' => [$this->c->id],
        ]))->assertSessionHasNoErrors();

        $this->assertSame([$this->c->id], $this->assigned($user));
    }

    public function test_unchecking_everything_removes_all_assignments(): void
    {
        $user = User::factory()->withRole($this->operatorRole())->assignedTo($this->a)->create();

        $this->actingAs(User::factory()->create())->put("/users/{$user->id}", $this->payload([
            'name' => $user->name, 'email' => $user->email, 'password' => '',
        ]))->assertSessionHasNoErrors();

        $this->assertSame([], $this->assigned($user));
    }

    public function test_non_administrator_can_only_assign_warehouses_they_have(): void
    {
        $actor = $this->manager($this->a);

        $this->actingAs($actor)->post('/users', $this->payload(['warehouses' => [$this->a->id, $this->b->id]]))
            ->assertSessionHasErrors('warehouses.1');

        $this->assertNull(User::where('email', 'sari@example.com')->first());

        $this->actingAs($actor)->post('/users', $this->payload(['warehouses' => [$this->a->id]]))->assertSessionHasNoErrors();
        $this->assertSame([$this->a->id], $this->assigned(User::where('email', 'sari@example.com')->sole()));
    }

    public function test_forged_warehouse_id_is_rejected(): void
    {
        $this->actingAs(User::factory()->create())
            ->post('/users', $this->payload(['warehouses' => [999999]]))
            ->assertSessionHasErrors('warehouses.0');
    }

    public function test_non_administrator_cannot_manage_users_assigned_to_warehouses_they_lack(): void
    {
        $actor = $this->manager($this->a);

        foreach ([[$this->b], [$this->a, $this->b]] as $warehouses) {
            $target = User::factory()->withRole($this->operatorRole())->assignedTo(...$warehouses)->create(['password' => 'asli123456']);
            $hash = $target->password;

            $this->actingAs($actor)->get("/users/{$target->id}/edit")->assertForbidden();
            $this->actingAs($actor)->put("/users/{$target->id}", $this->payload([
                'name' => $target->name, 'email' => $target->email, 'password' => 'diambilalih123', 'warehouses' => [$this->a->id],
            ]))->assertForbidden();
            $this->actingAs($actor)->delete("/users/{$target->id}")->assertForbidden();

            $this->assertSame($hash, $target->fresh()->password);
            $this->assertSame(array_map(fn (Warehouse $w) => $w->id, $warehouses), $this->assigned($target));
        }
    }

    public function test_non_administrator_can_manage_users_inside_own_warehouses_or_without_any(): void
    {
        $actor = $this->manager($this->a);
        $inside = User::factory()->withRole($this->operatorRole())->assignedTo($this->a)->create();
        $none = User::factory()->withRole($this->operatorRole())->create();

        foreach ([$inside, $none] as $target) {
            $this->actingAs($actor)->get("/users/{$target->id}/edit")->assertOk();
            $this->actingAs($actor)->put("/users/{$target->id}", $this->payload([
                'name' => 'Diubah', 'email' => $target->email, 'password' => '', 'warehouses' => [$this->a->id],
            ]))->assertSessionHasNoErrors();
            $this->assertSame('Diubah', $target->fresh()->name);
        }
    }

    public function test_user_list_hides_other_warehouse_users_from_non_administrators(): void
    {
        $actor = $this->manager($this->a);
        User::factory()->withRole($this->operatorRole())->assignedTo($this->a)->create(['name' => 'Orang Gudang A']);
        User::factory()->withRole($this->operatorRole())->assignedTo($this->b)->create(['name' => 'Orang Gudang B']);
        User::factory()->withRole($this->operatorRole())->create(['name' => 'Orang Tanpa Gudang']);
        User::factory()->create(['name' => 'Bos Besar']);

        $this->actingAs($actor)->get('/users')->assertOk()
            ->assertSee('Orang Gudang A')
            ->assertSee('Orang Tanpa Gudang')
            ->assertSee('Bos Besar')
            ->assertDontSee('Orang Gudang B');

        $this->actingAs(User::factory()->create())->get('/users')->assertSee('Orang Gudang B');
    }

    public function test_warehouse_checkboxes_show_only_assignable_warehouses(): void
    {
        $this->actingAs($this->manager($this->a))->get('/users/create')
            ->assertOk()
            ->assertSee('name="warehouses[]"', false)
            ->assertSee('Gudang Cikokol')
            ->assertDontSee('Gudang BSD')
            ->assertDontSee('Gudang Tangerang');

        $this->actingAs(User::factory()->create())->get('/users/create')
            ->assertSee('Gudang Cikokol')->assertSee('Gudang BSD')->assertSee('Gudang Tangerang');
    }

    public function test_edit_form_marks_current_assignments_as_checked(): void
    {
        $user = User::factory()->withRole($this->operatorRole())->assignedTo($this->b)->create();

        $this->actingAs(User::factory()->create())->get("/users/{$user->id}/edit")
            ->assertSee('value="'.$this->b->id.'" checked', false)
            ->assertDontSee('value="'.$this->a->id.'" checked', false);
    }

    public function test_assignment_changes_are_recorded_in_audit_log(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin)->post('/users', $this->payload(['warehouses' => [$this->a->id]]));
        $created = AuditLog::where('event', 'user.created')->sole();
        $this->assertSame(['Gudang Cikokol'], $created->diff['warehouses']['to']);
        $this->assertSame([], $created->diff['warehouses']['from']);

        $user = User::where('email', 'sari@example.com')->sole();
        $same = $this->payload(['password' => '', 'warehouses' => [$this->a->id]]);

        // Tanpa perubahan: tidak ada catatan baru.
        $this->actingAs($admin)->put("/users/{$user->id}", $same);
        $this->assertSame(0, AuditLog::where('event', 'user.updated')->count());

        $this->actingAs($admin)->put("/users/{$user->id}", $this->payload(['password' => '', 'warehouses' => [$this->a->id, $this->b->id]]));
        $updated = AuditLog::where('event', 'user.updated')->sole();
        $this->assertSame(['Gudang Cikokol'], $updated->diff['warehouses']['from']);
        $this->assertSame(['Gudang BSD', 'Gudang Cikokol'], $updated->diff['warehouses']['to']);
        $this->assertArrayNotHasKey('name', $updated->diff);
    }

    public function test_form_explains_that_administrators_see_every_warehouse(): void
    {
        $administrator = User::factory()->create();

        $this->actingAs(User::factory()->create())->get("/users/{$administrator->id}/edit")
            ->assertOk()
            ->assertSee('Administrator melihat semua gudang');
    }

    public function test_user_list_shows_warehouses_per_user(): void
    {
        User::factory()->withRole($this->operatorRole())->assignedTo($this->a, $this->b)->create(['name' => 'Budi Operator']);
        User::factory()->withRole($this->operatorRole())->create(['name' => 'Tanpa Gudang']);

        $this->actingAs(User::factory()->create())->get('/users')
            ->assertOk()
            ->assertSee('Gudang Cikokol')
            ->assertSee('Gudang BSD')
            ->assertSee('Belum ditugaskan')
            ->assertSee('Semua gudang');
    }
}
