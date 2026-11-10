<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LayoutTest extends TestCase
{
    use RefreshDatabase;

    private function operator(): User
    {
        return User::factory()->withRole(Role::where('name', 'Operator')->sole())->create();
    }

    /** Isi sidebar saja (tautan di header, mis. lonceng notifikasi, tidak ikut dihitung). */
    private function sidebarOf(string $html): string
    {
        preg_match('~<aside id="sidebar".*?</aside>~s', $html, $match);

        return $match[0] ?? '';
    }

    public function test_sidebar_shows_only_permitted_links(): void
    {
        $sidebar = $this->sidebarOf($this->actingAs($this->operator())->get('/dashboard')->assertOk()->getContent());
        foreach (['/dashboard', '/incidents', '/reports', '/notifications'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $sidebar, $path);
        }
        foreach (['/zones', '/warehouses', '/users', '/roles', '/audit-logs'] as $path) {
            $this->assertStringNotContainsString('href="'.url($path).'"', $sidebar, $path);
        }

        $admin = $this->sidebarOf($this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent());
        foreach (['/dashboard', '/incidents', '/reports', '/notifications', '/zones', '/warehouses', '/users', '/roles', '/audit-logs'] as $path) {
            $this->assertStringContainsString('href="'.url($path).'"', $admin, $path);
        }
    }

    public function test_sidebar_marks_the_current_page(): void
    {
        $html = $this->actingAs($this->operator())->get('/incidents')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<a[^>]*href="[^"]*/incidents"[^>]*aria-current="page"~', $html);
        $this->assertDoesNotMatchRegularExpression('~<a[^>]*href="[^"]*/dashboard"[^>]*aria-current~', $html);
    }

    public function test_header_shows_user_name_role_logout_and_unread_badge(): void
    {
        $user = $this->operator();
        foreach (range(1, 2) as $n) {
            DB::table('notifications')->insert([
                'id' => (string) Str::uuid(), 'type' => 'x', 'notifiable_type' => User::class, 'notifiable_id' => $user->id,
                'data' => json_encode(['title' => "Notifikasi {$n}"]), 'read_at' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $page = $this->actingAs($user)->get('/dashboard')->assertOk();

        $page->assertSee($user->name)->assertSee('Operator');
        $page->assertSee('action="'.route('logout').'"', false);
        $this->assertMatchesRegularExpression('~data-unread-badge[^>]*>\s*2\s*<~', $page->getContent());
    }

    public function test_mobile_menu_button_and_banner_container_exist(): void
    {
        $html = $this->actingAs($this->operator())->get('/dashboard')->assertOk()->assertSee('aria-label="Buka menu"', false)->getContent();

        $sticky = strpos($html, 'sticky top-0');
        $header = strpos($html, '<header');
        $banners = strpos($html, 'id="banners"');
        $this->assertNotFalse($sticky);
        $this->assertTrue($sticky < $header && $header < $banners, 'banner berada di wadah sticky yang sama, di bawah header');
    }

    public function test_login_403_and_no_access_pages_render(): void
    {
        $this->get('/login')->assertOk()->assertSee('name="email"', false)->assertSee('name="password"', false)->assertSee('Masuk');

        $viewer = User::factory()->withPermissions(Permission::DashboardView)->create();
        $this->actingAs($viewer)->get('/users')->assertForbidden()->assertSee('tidak punya izin');

        $this->actingAs(User::factory()->withoutRole()->create())->get('/home')->assertOk()->assertSee('belum punya hak akses');
    }

    public function test_page_title_and_brand_come_from_config(): void
    {
        config(['app.name' => 'FreshGuard IoT']);

        $html = $this->actingAs($this->operator())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('<title>Dashboard · FreshGuard IoT</title>', $html);
        $this->assertGreaterThanOrEqual(2, substr_count($html, 'FreshGuard IoT'), 'judul dan merek di sidebar');
    }
}
