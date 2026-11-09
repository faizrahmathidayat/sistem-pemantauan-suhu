<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->withPermissions(Permission::UserManage)->create(['name' => 'Pengelola']);
    }

    private function role(string $name): Role
    {
        return Role::where('name', $name)->sole();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sari Dewi',
            'email' => 'sari@example.com',
            'password' => 'rahasia123',
            'role_id' => $this->role('Operator')->id,
        ], $overrides);
    }

    public function test_index_lists_users_with_their_roles(): void
    {
        User::factory()->withRole($this->role('Operator'))->create(['name' => 'Sari Dewi', 'email' => 'sari@example.com']);

        $this->actingAs($this->actor())->get('/users')
            ->assertOk()
            ->assertSee('Sari Dewi')
            ->assertSee('sari@example.com')
            ->assertSee('Operator');
    }

    public function test_store_creates_user_with_role_and_hashed_password(): void
    {
        $this->actingAs($this->actor())->post('/users', $this->payload())
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $user = User::where('email', 'sari@example.com')->sole();
        $this->assertSame('Sari Dewi', $user->name);
        $this->assertSame($this->role('Operator')->id, $user->role_id);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
        $this->assertNotSame('rahasia123', $user->password);
    }

    public function test_store_validates_name_email_unique_password_min_8_and_existing_role(): void
    {
        User::factory()->create(['email' => 'ada@example.com']);
        $actor = $this->actor();

        $this->actingAs($actor)->post('/users', $this->payload(['name' => '']))->assertSessionHasErrors('name');
        $this->actingAs($actor)->post('/users', $this->payload(['email' => 'bukan-email']))->assertSessionHasErrors('email');
        $this->actingAs($actor)->post('/users', $this->payload(['email' => 'ada@example.com']))->assertSessionHasErrors('email');
        $this->actingAs($actor)->post('/users', $this->payload(['password' => 'pendek']))->assertSessionHasErrors('password');
        $this->actingAs($actor)->post('/users', $this->payload(['password' => '']))->assertSessionHasErrors('password');
        $this->actingAs($actor)->post('/users', $this->payload(['role_id' => 999999]))->assertSessionHasErrors('role_id');
        $this->actingAs($actor)->post('/users', $this->payload(['role_id' => '']))->assertSessionHasErrors('role_id');
    }

    public function test_update_changes_role_and_leaves_password_when_blank(): void
    {
        $user = User::factory()->withRole($this->role('Operator'))->create(['password' => 'lama12345']);
        $hash = $user->password;

        $this->actingAs($this->actor())->put("/users/{$user->id}", $this->payload([
            'name' => 'Nama Baru', 'email' => 'baru@example.com', 'password' => '', 'role_id' => $this->role('Supervisor')->id,
        ]))->assertRedirect(route('users.index'));

        $user->refresh();
        $this->assertSame('Nama Baru', $user->name);
        $this->assertSame('baru@example.com', $user->email);
        $this->assertSame($this->role('Supervisor')->id, $user->role_id);
        $this->assertSame($hash, $user->password);
    }

    public function test_update_can_change_password(): void
    {
        $user = User::factory()->withRole($this->role('Operator'))->create(['password' => 'lama12345']);

        $this->actingAs($this->actor())->put("/users/{$user->id}", $this->payload([
            'name' => $user->name, 'email' => $user->email, 'password' => 'baru123456',
        ]))->assertSessionHasNoErrors();

        $this->assertTrue(Hash::check('baru123456', $user->refresh()->password));
    }

    public function test_cannot_delete_own_account(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->delete("/users/{$actor->id}")->assertRedirect()->assertSessionHas('error');

        $this->assertNotNull($actor->fresh());
    }

    public function test_sole_administrator_cannot_delete_or_demote_themselves(): void
    {
        $administrator = User::factory()->create();

        $this->actingAs($administrator)->delete("/users/{$administrator->id}")
            ->assertRedirect()
            ->assertSessionHas('error');
        $this->assertNotNull($administrator->fresh());

        $this->actingAs($administrator)->put("/users/{$administrator->id}", $this->payload([
            'name' => $administrator->name, 'email' => $administrator->email, 'password' => '',
            'role_id' => $this->role('Operator')->id,
        ]))->assertSessionHasErrors('role_id');
        $this->assertSame(Role::administrator()->id, $administrator->fresh()->role_id);
    }

    public function test_administrator_can_demote_or_delete_another_administrator_while_one_remains(): void
    {
        $actor = User::factory()->create();
        $second = User::factory()->create();
        $third = User::factory()->create();

        $this->actingAs($actor)->put("/users/{$second->id}", $this->payload([
            'name' => $second->name, 'email' => $second->email, 'password' => '',
            'role_id' => $this->role('Operator')->id,
        ]))->assertSessionHasNoErrors();
        $this->assertSame($this->role('Operator')->id, $second->fresh()->role_id);

        $this->actingAs($actor)->delete("/users/{$third->id}")->assertSessionHas('status');
        $this->assertNull(User::find($third->id));
        $this->assertSame(1, User::whereHas('role', fn ($role) => $role->where('is_system', true))->count());
    }

    public function test_delete_removes_user_and_keeps_audit_history_name(): void
    {
        $budi = User::factory()->withRole($this->role('Operator'))->create(['name' => 'Budi Santoso']);
        $log = AuditLog::factory()->create(['user_id' => $budi->id, 'user_name' => 'Budi Santoso']);

        $this->actingAs($this->actor())->delete("/users/{$budi->id}")
            ->assertRedirect(route('users.index'))
            ->assertSessionHas('status');

        $this->assertNull(User::find($budi->id));
        $log->refresh();
        $this->assertNull($log->user_id);
        $this->assertSame('Budi Santoso', $log->user_name);
    }

    public function test_pages_require_user_manage_permission(): void
    {
        $user = User::factory()->withPermissions(Permission::ZoneView)->create();
        $other = User::factory()->withRole($this->role('Operator'))->create();

        $this->actingAs($user)->get('/users')->assertForbidden();
        $this->actingAs($user)->get('/users/create')->assertForbidden();
        $this->actingAs($user)->post('/users', $this->payload())->assertForbidden();
        $this->actingAs($user)->get("/users/{$other->id}/edit")->assertForbidden();
        $this->actingAs($user)->put("/users/{$other->id}", $this->payload())->assertForbidden();
        $this->actingAs($user)->delete("/users/{$other->id}")->assertForbidden();
    }

    public function test_password_never_appears_in_audit_log(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/users', $this->payload(['password' => 'RahasiaBanget123']));
        $user = User::where('email', 'sari@example.com')->sole();
        $this->actingAs($actor)->put("/users/{$user->id}", $this->payload(['password' => 'GantiLagi456']));

        $dump = AuditLog::all()->toJson();
        $this->assertStringNotContainsString('RahasiaBanget123', $dump);
        $this->assertStringNotContainsString('GantiLagi456', $dump);
        $this->assertStringNotContainsString($user->fresh()->password, $dump);
        $this->assertStringNotContainsString('$2y$', $dump);

        $created = AuditLog::where('event', 'user.created')->sole();
        $this->assertSame('diubah', $created->diff['password']['to']);
        $this->assertArrayNotHasKey('remember_token', $created->diff);
        $this->assertArrayNotHasKey('role_id', $created->diff);
    }

    public function test_user_events_are_recorded(): void
    {
        $actor = $this->actor();

        $this->actingAs($actor)->post('/users', $this->payload());
        $created = AuditLog::where('event', 'user.created')->sole();
        $this->assertSame('Pengelola', $created->user_name);
        $this->assertSame('Sari Dewi', $created->subject_label);
        $this->assertSame('Operator', $created->diff['role']['to']);
        $this->assertSame('sari@example.com', $created->diff['email']['to']);

        $user = User::where('email', 'sari@example.com')->sole();

        // Tanpa perubahan (kata sandi kosong): tidak ada catatan baru.
        $this->actingAs($actor)->put("/users/{$user->id}", $this->payload(['password' => '']));
        $this->assertSame(0, AuditLog::where('event', 'user.updated')->count());

        $this->actingAs($actor)->put("/users/{$user->id}", $this->payload(['password' => '', 'role_id' => $this->role('Supervisor')->id]));
        $updated = AuditLog::where('event', 'user.updated')->sole();
        $this->assertSame('Operator', $updated->diff['role']['from']);
        $this->assertSame('Supervisor', $updated->diff['role']['to']);
        $this->assertArrayNotHasKey('name', $updated->diff);

        $this->actingAs($actor)->delete("/users/{$user->id}");
        $deleted = AuditLog::where('event', 'user.deleted')->sole();
        $this->assertSame('Sari Dewi', $deleted->subject_label);
        $this->assertSame('sari@example.com', $deleted->diff['email']['from']);
    }

    public function test_non_administrator_cannot_assign_the_administrator_role(): void
    {
        $actor = $this->actor();
        $victim = User::factory()->withRole($this->role('Operator'))->create();

        $this->actingAs($actor)->post('/users', $this->payload(['role_id' => Role::administrator()->id]))
            ->assertSessionHasErrors('role_id');
        $this->assertNull(User::where('email', 'sari@example.com')->first());

        $this->actingAs($actor)->put("/users/{$victim->id}", $this->payload([
            'name' => $victim->name, 'email' => $victim->email, 'password' => '', 'role_id' => Role::administrator()->id,
        ]))->assertSessionHasErrors('role_id');
        $this->assertSame($this->role('Operator')->id, $victim->fresh()->role_id);
    }

    public function test_non_administrator_cannot_edit_or_delete_an_administrator(): void
    {
        $actor = $this->actor();
        $administrator = User::factory()->create(['password' => 'asli123456']);
        User::factory()->create(); // Administrator lain, agar penghapusan tidak ditolak karena "terakhir"
        $hash = $administrator->password;

        $this->actingAs($actor)->get("/users/{$administrator->id}/edit")->assertForbidden();
        $this->actingAs($actor)->put("/users/{$administrator->id}", $this->payload([
            'name' => $administrator->name, 'email' => $administrator->email, 'password' => 'diambilalih123',
            'role_id' => Role::administrator()->id,
        ]))->assertForbidden();
        $this->actingAs($actor)->delete("/users/{$administrator->id}")->assertForbidden();

        $administrator->refresh();
        $this->assertSame($hash, $administrator->password);
        $this->assertNotNull(User::find($administrator->id));
    }

    public function test_non_administrator_cannot_change_their_own_role(): void
    {
        $actor = $this->actor();
        $other = Role::factory()->withPermissions(...Permission::cases())->create();

        $this->actingAs($actor)->put("/users/{$actor->id}", $this->payload([
            'name' => $actor->name, 'email' => $actor->email, 'password' => '', 'role_id' => $other->id,
        ]))->assertSessionHasErrors('role_id');

        $this->assertNotSame($other->id, $actor->fresh()->role_id);
    }

    public function test_administrator_can_still_assign_the_administrator_role(): void
    {
        $this->actingAs(User::factory()->create())->post('/users', $this->payload(['role_id' => Role::administrator()->id]))
            ->assertSessionHasNoErrors();

        $this->assertSame(Role::administrator()->id, User::where('email', 'sari@example.com')->sole()->role_id);
    }

    public function test_forms_and_list_hide_administrator_options_from_non_administrators(): void
    {
        $actor = $this->actor();
        $administrator = User::factory()->create(['name' => 'Bos Besar']);

        $this->actingAs($actor)->get('/users/create')->assertOk()->assertDontSee('>Administrator</option>', false);
        $this->actingAs($actor)->get('/users')->assertOk()
            ->assertSee('Bos Besar')
            ->assertDontSee("/users/{$administrator->id}/edit", false);

        $this->actingAs(User::factory()->create())->get('/users/create')->assertSee('>Administrator</option>', false);
    }
}
