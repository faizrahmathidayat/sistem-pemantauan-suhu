<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ManagementUiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Warehouse $warehouse;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['name' => 'Admin Uji']);
        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang Kelola']);
        $this->zone = Zone::factory()->create(['warehouse_id' => $this->warehouse->id, 'name' => 'Zona Kelola', 'device_id' => 'dev-kelola']);
    }

    private function section(string $html, string $pattern): string
    {
        preg_match($pattern, $html, $match);

        return $match[0] ?? '';
    }

    /**
     * @return array<string, array{0: string, 1: list<string>}>
     */
    public static function forms(): array
    {
        return [
            'gudang' => ['/warehouses/create', ['name']],
            'zona' => ['zone-create', ['name', 'device_id', 'products', 'temp_min', 'temp_max', 'warning_margin', 'humidity_min', 'humidity_max', 'humidity_warning_margin']],
            'batas zona' => ['zone-edit', ['temp_min', 'temp_max', 'warning_margin', 'humidity_min', 'humidity_max', 'humidity_warning_margin']],
            'user' => ['/users/create', ['name', 'email', 'password', 'role_id', 'warehouses[]']],
            'role' => ['/roles/create', ['name', 'permissions[]']],
        ];
    }

    /**
     * @param  list<string>  $fields
     */
    #[DataProvider('forms')]
    public function test_forms_use_shared_form_classes_and_keep_field_names(string $url, array $fields): void
    {
        $url = match ($url) {
            'zone-create' => "/warehouses/{$this->warehouse->id}/zones/create",
            'zone-edit' => "/zones/{$this->zone->id}/edit",
            default => $url,
        };

        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

        foreach ($fields as $field) {
            $this->assertMatchesRegularExpression('~<(input|select|textarea)(?=[^>]*name="'.preg_quote($field, '~').'")[^>]*>~', $html, "field {$field}");
        }
        $this->assertMatchesRegularExpression('~<(input|select|textarea)(?=[^>]*name="'.preg_quote($fields[0], '~').'")(?=[^>]*form-input)[^>]*>~', $html, 'kelas form-input');
        $this->assertStringContainsString('btn btn-primary', $html);
    }

    public function test_validation_errors_are_shown_with_the_alert_component(): void
    {
        $this->actingAs($this->admin)->from('/warehouses/create')->post('/warehouses', ['name' => ''])
            ->assertRedirect('/warehouses/create');

        $html = $this->get('/warehouses/create')->assertOk()->getContent();

        $this->assertStringContainsString('role="alert"', $html);
        $this->assertStringContainsString('Perubahan belum disimpan', $html);
        $this->assertStringContainsString('border-error-100', $html, 'gaya komponen alert galat');
    }

    public function test_zone_manage_page_marks_dangerous_actions_and_shows_token_once(): void
    {
        $token = str_repeat('a1', 24);

        $html = $this->actingAs($this->admin)->withSession(['new_token' => $token])->get("/zones/{$this->zone->id}/manage")->assertOk()
            ->assertSee($token)->assertSee('hanya tampil sekali')->getContent();

        $danger = $this->section($html, '~<section[^>]*data-danger-zone.*?</section>~s');
        $this->assertStringContainsString('Hapus zona', $danger);
        $this->assertStringContainsString('btn-danger', $danger);
        $this->assertStringContainsString('confirm(', $danger);
        $this->assertStringNotContainsString('Buat token baru', $danger, 'token baru dipisah dari blok hapus');
    }

    public function test_warehouse_list_renders_table_and_mobile_cards(): void
    {
        $html = $this->actingAs($this->admin)->get('/warehouses')->assertOk()->getContent();

        foreach ([$this->section($html, '~<table.*?</table>~s'), $this->section($html, '~<ul[^>]*data-mobile-list.*?</ul>~s')] as $layout) {
            $this->assertStringContainsString('Gudang Kelola', $layout);
            $this->assertStringContainsString('1 zona', $layout);
        }
    }

    public function test_users_list_renders_table_and_mobile_cards(): void
    {
        User::factory()->withRole(Role::where('name', 'Operator')->sole())->assignedTo($this->warehouse)->create(['name' => 'Siti Operator', 'email' => 'siti@example.com']);

        $html = $this->actingAs($this->admin)->get('/users')->assertOk()->getContent();

        foreach ([$this->section($html, '~<table.*?</table>~s'), $this->section($html, '~<ul[^>]*data-mobile-list.*?</ul>~s')] as $layout) {
            $this->assertStringContainsString('Siti Operator', $layout);
            $this->assertStringContainsString('siti@example.com', $layout);
            $this->assertStringContainsString('Operator', $layout);
            $this->assertStringContainsString('Gudang Kelola', $layout);
        }
    }

    public function test_roles_list_renders_table_and_mobile_cards(): void
    {
        $html = $this->actingAs($this->admin)->get('/roles')->assertOk()->getContent();

        foreach ([$this->section($html, '~<table.*?</table>~s'), $this->section($html, '~<ul[^>]*data-mobile-list.*?</ul>~s')] as $layout) {
            $this->assertStringContainsString('Administrator', $layout);
            $this->assertStringContainsString('Sistem', $layout);
            $this->assertStringContainsString('Operator', $layout);
        }
    }
}
