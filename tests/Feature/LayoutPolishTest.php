<?php

namespace Tests\Feature;

use App\Enums\Permission;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

/** Perbaikan hasil review akhir perombakan tampilan, dan perilaku sidebar yang bisa diciutkan. */
class LayoutPolishTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang Poles']);
        $this->zone = Zone::factory()->create(['warehouse_id' => $this->warehouse->id, 'name' => 'Zona Poles', 'device_id' => 'dev-poles']);
    }

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function sidebar(string $html): string
    {
        preg_match('~<aside id="sidebar".*?</aside>~s', $html, $match);

        return $match[0] ?? '';
    }

    // ---- review: navigasi

    public function test_sub_pages_show_a_back_link_below_md(): void
    {
        $manage = $this->actingAs($this->admin())->get("/zones/{$this->zone->id}/manage")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<a[^>]*md:hidden[^>]*>\s*←\s*Batas Zona~', $manage);

        $edit = $this->actingAs($this->admin())->get("/warehouses/{$this->warehouse->id}/edit")->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~<a[^>]*md:hidden[^>]*>\s*←\s*Gudang~', $edit);
    }

    public function test_zone_create_back_link_respects_permissions(): void
    {
        $zoneManager = User::factory()->withPermissions(Permission::ZoneManage, Permission::ZoneView)->assignedTo($this->warehouse)->create();

        $this->actingAs($zoneManager)->get("/warehouses/{$this->warehouse->id}/zones/create")->assertOk()
            ->assertDontSee('href="'.route('warehouses.edit', $this->warehouse).'"', false)
            ->assertSee('href="'.route('zones.index').'"', false);

        $this->actingAs($this->admin())->get("/warehouses/{$this->warehouse->id}/zones/create")->assertOk()
            ->assertSee('href="'.route('warehouses.edit', $this->warehouse).'"', false);
    }

    // ---- review: tampilan

    public function test_icon_size_class_is_not_overridden_by_the_default(): void
    {
        $html = Blade::render('<x-icon name="plus" class="size-4" /><x-icon name="plus" />');

        $this->assertStringContainsString('class="size-4"', $html);
        $this->assertStringNotContainsString('size-5 size-4', $html);
        $this->assertSame(1, substr_count($html, 'class="size-5"'), 'tanpa class: ukuran bawaan size-5');
    }

    public function test_unread_notification_is_tinted_not_white(): void
    {
        $user = $this->admin();
        $incident = Incident::factory()->for($this->zone)->create();
        $log = SensorLog::factory()->create(['device_id' => 'dev-poles', 'temperature' => 29.4]);
        $user->notify(new IncidentOpened($incident, $log));

        $html = $this->actingAs($user)->get('/notifications')->assertOk()->getContent();

        preg_match('~<li\s+data-unread="true"[^>]*>~', $html, $tag);
        $this->assertNotEmpty($tag, 'item belum dibaca ditemukan');
        $this->assertStringContainsString('bg-error-50/40', $tag[0]);
        $this->assertStringNotContainsString('bg-white', $tag[0]);
    }

    public function test_sidebar_labels_use_readable_contrast(): void
    {
        $sidebar = $this->sidebar($this->actingAs($this->admin())->get('/dashboard')->getContent());

        $this->assertNotSame('', $sidebar);
        $this->assertStringNotContainsString('text-gray-400', $sidebar);
    }

    public function test_input_focus_ring_is_visible_with_the_dark_brand_colour(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));

        $this->assertStringContainsString('focus:ring-brand-400/30', $css);
        $this->assertStringNotContainsString('focus:ring-brand-500/10', $css);
    }

    public function test_report_table_can_overflow_when_printed(): void
    {
        $css = file_get_contents(resource_path('css/app.css'));
        $this->assertStringContainsString('print:whitespace-normal', $css);
        $this->assertStringContainsString('print:overflow-visible', file_get_contents(resource_path('views/components/table.blade.php')));
    }

    // ---- review: aksesibilitas dan ketahanan

    public function test_closed_mobile_sidebar_is_not_focusable_and_button_reports_state(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('max-lg:invisible', $this->sidebar($html));
        $this->assertStringContainsString('max-lg:data-[open=true]:visible', $this->sidebar($html));
        $this->assertMatchesRegularExpression('~<button[^>]*:aria-expanded="sidebarOpen"[^>]*aria-label="Buka menu"|<button[^>]*aria-label="Buka menu"[^>]*:aria-expanded="sidebarOpen"~', $html);
    }

    public function test_header_partial_does_not_crash_for_guests(): void
    {
        $this->assertSame('', trim(view('layouts.partials.header')->render()));
    }

    // ---- sidebar yang bisa diciutkan

    public function test_header_has_a_desktop_toggle_for_the_sidebar(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<button[^>]*aria-label="Ciutkan sidebar"[^>]*>~', $html);
        $this->assertStringContainsString('@click="toggleSidebar()"', $html);
        $this->assertStringContainsString(':aria-expanded="sidebarExpanded"', $html);
        $this->assertMatchesRegularExpression('~<button[^>]*aria-label="Ciutkan sidebar"[^>]*class="[^"]*\bhidden\b[^"]*\blg:inline-flex\b~', $html, 'hanya tampil di layar lebar');
    }

    public function test_collapsed_state_is_applied_before_first_paint_and_persisted(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('~<html[^>]*class="[^"]*\bgroup/shell\b~', $html);
        $this->assertStringContainsString("localStorage.getItem('sidebarExpanded')", $html, 'skrip di <head> membaca pilihan sebelum halaman digambar');
        $this->assertStringContainsString('x-data="shell"', $html);

        $js = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString("Alpine.data('shell'", $js);
        $this->assertStringContainsString('sidebarExpanded', $js);
        $this->assertStringContainsString('localStorage.setItem', $js);
    }

    public function test_sidebar_and_content_change_width_when_collapsed(): void
    {
        $html = $this->actingAs($this->admin())->get('/dashboard')->assertOk()->getContent();
        $sidebar = $this->sidebar($html);

        $this->assertStringContainsString('lg:group-data-[collapsed=true]/shell:w-20', $sidebar);
        $this->assertStringContainsString('sidebar-anim', $sidebar);
        $this->assertStringContainsString('lg:group-data-[collapsed=true]/shell:pl-20', $html);
        $this->assertStringContainsString('print:group-data-[collapsed=true]/shell:pl-0', $html);
        // Teks menu diciutkan dengan transisi (bukan disembunyikan mendadak) dan tiap menu punya tooltip.
        $this->assertStringContainsString('group-data-[collapsed=true]/shell:max-w-0', $sidebar);
        $this->assertMatchesRegularExpression('~<a href="[^"]*/dashboard"[^>]*title="Dashboard"~', $sidebar);
        $this->assertMatchesRegularExpression('~<a href="[^"]*/audit-logs"[^>]*title="Log Perubahan"~', $sidebar);
    }
}
