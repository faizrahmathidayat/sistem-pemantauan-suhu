<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_root_redirects_to_home(): void
    {
        $this->get('/')->assertRedirect('/home');
    }

    public function test_login_page_renders_for_guests(): void
    {
        $this->get('/login')->assertOk()->assertSee('Masuk');
    }

    public function test_valid_login_redirects_to_home(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->post('/login', ['email' => $user->email, 'password' => 'rahasia123'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
    }

    public function test_invalid_login_shows_error(): void
    {
        $user = User::factory()->create(['password' => 'rahasia123']);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'salah'])
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_logout_signs_the_user_out(): void
    {
        $this->actingAs(User::factory()->create())->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_register_route_does_not_exist(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
    }

    public function test_user_create_command_creates_hashed_user(): void
    {
        $this->assertSame(0, Artisan::call('user:create', [
            'name' => 'Budi', 'email' => 'budi@example.com', '--password' => 'rahasia123',
        ]));

        $user = User::where('email', 'budi@example.com')->sole();
        $this->assertSame('Budi', $user->name);
        $this->assertTrue(Hash::check('rahasia123', $user->password));
    }

    public function test_user_create_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'budi@example.com']);

        $this->assertSame(1, Artisan::call('user:create', [
            'name' => 'Budi', 'email' => 'budi@example.com', '--password' => 'rahasia123',
        ]));
        $this->assertSame(1, User::count());
    }

    public function test_seeder_creates_admin_from_config(): void
    {
        config(['monitoring.admin' => ['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'rahasia123']]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@example.com')->sole();
        $this->assertTrue(Hash::check('rahasia123', $admin->password));
    }

    public function test_seeder_skips_admin_when_credentials_empty(): void
    {
        config(['monitoring.admin' => ['name' => 'Admin', 'email' => null, 'password' => null]]);

        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, User::count());
    }
}
