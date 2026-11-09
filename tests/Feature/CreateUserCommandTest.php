<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_defaults_to_administrator_role(): void
    {
        $this->assertSame(0, Artisan::call('user:create', [
            'name' => 'Budi', 'email' => 'budi@example.com', '--password' => 'rahasia123',
        ]));

        $this->assertSame(Role::administrator()->id, User::where('email', 'budi@example.com')->sole()->role_id);
    }

    public function test_role_option_assigns_that_role(): void
    {
        $this->assertSame(0, Artisan::call('user:create', [
            'name' => 'Sari', 'email' => 'sari@example.com', '--password' => 'rahasia123', '--role' => 'Operator',
        ]));

        $this->assertSame(Role::where('name', 'Operator')->sole()->id, User::where('email', 'sari@example.com')->sole()->role_id);
    }

    public function test_unknown_role_fails_and_creates_no_user(): void
    {
        $this->assertSame(1, Artisan::call('user:create', [
            'name' => 'Sari', 'email' => 'sari@example.com', '--password' => 'rahasia123', '--role' => 'Tidak Ada',
        ]));

        $this->assertStringContainsString('Tidak Ada', Artisan::output());
        $this->assertSame(0, User::count());
    }

    public function test_seeder_gives_the_admin_the_administrator_role_and_installs_starter_roles(): void
    {
        config(['monitoring.admin' => ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'rahasia123']]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(Role::administrator()->id, User::where('email', 'admin@example.com')->sole()->role_id);
        $this->assertSame(1, Role::where('name', 'Supervisor')->count());
    }
}
