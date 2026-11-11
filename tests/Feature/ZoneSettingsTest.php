<?php

namespace Tests\Feature;

use App\Enums\ZoneStatus;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\ZoneStatusEvaluator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ZoneSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::factory()->create();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'temp_min' => 10,
            'temp_max' => 20,
            'humidity_min' => 60,
            'humidity_max' => 85,
            'warning_margin' => 2,
            'humidity_warning_margin' => 5,
        ], $overrides);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $zone = Zone::factory()->create();

        $this->get('/zones')->assertRedirect(route('login'));
        $this->get("/zones/{$zone->id}/edit")->assertRedirect(route('login'));
        $this->put("/zones/{$zone->id}", $this->payload())->assertRedirect(route('login'));
    }

    public function test_each_warehouse_section_can_be_collapsed_and_shows_its_zone_count(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Lipat']);
        Zone::factory()->count(2)->create(['warehouse_id' => $warehouse->id]);
        Warehouse::factory()->create(['name' => 'Gudang Kosong']);

        $html = $this->actingAs($this->user())->get('/zones')->assertOk()->getContent();

        // Satu tombol lipat per gudang; isinya tetap ada di HTML (dilipat di sisi peramban, jadi tetap terbaca tanpa JS).
        $this->assertSame(2, substr_count($html, 'data-warehouse-toggle'));
        $this->assertSame(2, substr_count($html, 'data-warehouse-toggle @click="toggle()" :aria-expanded="open"'));
        $this->assertStringContainsString('2 zona', $html);
        $this->assertStringContainsString('0 zona', $html);
        $this->assertStringContainsString('x-show="open"', $html);
    }

    public function test_index_lists_every_zone_with_its_limits(): void
    {
        Zone::factory()->create(['name' => 'Zona Telur', 'temp_min' => 10, 'temp_max' => 20, 'humidity_min' => 60, 'humidity_max' => 85]);
        Zone::factory()->create(['name' => 'Zona Buah', 'temp_min' => 8, 'temp_max' => 13, 'humidity_min' => null, 'humidity_max' => null]);

        $this->actingAs($this->user())->get('/zones')
            ->assertOk()
            ->assertSee('Zona Telur')
            ->assertSee('10–20')
            ->assertSee('60–85')
            ->assertSee('Zona Buah')
            ->assertSee('8–13')
            ->assertSee('Tidak diperiksa');
    }

    public function test_edit_form_shows_current_values(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona Telur', 'temp_min' => 10.5, 'temp_max' => 20, 'warning_margin' => 2]);

        $this->actingAs($this->user())->get("/zones/{$zone->id}/edit")
            ->assertOk()
            ->assertSee('Zona Telur')
            ->assertSee('name="temp_min"', false)
            ->assertSee('value="10.5"', false)
            ->assertSee('name="humidity_warning_margin"', false);
    }

    public function test_update_saves_limits_and_redirects_with_confirmation(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona Telur']);

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['temp_min' => 11.5, 'temp_max' => 19, 'humidity_min' => 55, 'humidity_max' => 90, 'warning_margin' => 1.5, 'humidity_warning_margin' => 4]))
            ->assertRedirect(route('zones.index'))
            ->assertSessionHas('status');

        $zone->refresh();
        $this->assertSame(11.5, $zone->temp_min);
        $this->assertSame(19.0, $zone->temp_max);
        $this->assertSame(55.0, $zone->humidity_min);
        $this->assertSame(90.0, $zone->humidity_max);
        $this->assertSame(1.5, $zone->warning_margin);
        $this->assertSame(4.0, $zone->humidity_warning_margin);
    }

    public function test_confirmation_is_shown_on_index_after_saving(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona Telur']);

        $this->actingAs($this->user())
            ->followingRedirects()
            ->put("/zones/{$zone->id}", $this->payload())
            ->assertOk()
            ->assertSee('Batas Zona Telur disimpan');
    }

    public function test_only_limits_can_be_changed(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona Telur', 'device_id' => 'gudang-zona-a', 'device_token_hash' => str_repeat('a', 64)]);

        $this->actingAs($this->user())->put("/zones/{$zone->id}", $this->payload([
            'name' => 'Diubah', 'device_id' => 'gudang-lain', 'device_token_hash' => 'x',
        ]))->assertRedirect(route('zones.index'));

        $zone->refresh();
        $this->assertSame('Zona Telur', $zone->name);
        $this->assertSame('gudang-zona-a', $zone->device_id);
        $this->assertSame(str_repeat('a', 64), $zone->device_token_hash);
    }

    public function test_max_temperature_must_be_above_min(): void
    {
        $zone = Zone::factory()->create(['temp_min' => 15, 'temp_max' => 25]);

        $this->actingAs($this->user())->from("/zones/{$zone->id}/edit")
            ->put("/zones/{$zone->id}", $this->payload(['temp_min' => 20, 'temp_max' => 20]))
            ->assertRedirect("/zones/{$zone->id}/edit")
            ->assertSessionHasErrors('temp_max');

        $this->assertSame(25.0, $zone->refresh()->temp_max);
    }

    public function test_temperature_is_required_and_within_sensor_range(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['temp_min' => null, 'temp_max' => 81]))
            ->assertSessionHasErrors(['temp_min', 'temp_max']);
    }

    public function test_humidity_limits_may_both_be_left_empty(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_min' => '', 'humidity_max' => '']))
            ->assertSessionHasNoErrors();

        $zone->refresh();
        $this->assertNull($zone->humidity_min);
        $this->assertNull($zone->humidity_max);
    }

    public function test_humidity_limits_cannot_be_half_filled(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_min' => 60, 'humidity_max' => '']))
            ->assertSessionHasErrors('humidity_max');

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_min' => '', 'humidity_max' => 85]))
            ->assertSessionHasErrors('humidity_min');
    }

    public function test_humidity_max_must_be_above_min_and_within_0_to_100(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_min' => 80, 'humidity_max' => 70]))
            ->assertSessionHasErrors('humidity_max');

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_min' => -1, 'humidity_max' => 101]))
            ->assertSessionHasErrors(['humidity_min', 'humidity_max']);
    }

    public function test_warning_margin_that_leaves_no_safe_range_is_rejected(): void
    {
        $zone = Zone::factory()->create();

        // Rentang 10–20 (lebar 10): margin 5 di tiap sisi menghabiskan seluruh rentang.
        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['warning_margin' => 5]))
            ->assertSessionHasErrors('warning_margin');

        // Rentang kelembapan 60–85 (lebar 25): margin 12,5 menghabiskan seluruh rentang.
        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_warning_margin' => 12.5]))
            ->assertSessionHasErrors('humidity_warning_margin');
    }

    public function test_warning_margin_cannot_be_negative(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['warning_margin' => -1]))
            ->assertSessionHasErrors('warning_margin');
    }

    public function test_humidity_margin_is_not_checked_against_an_unchecked_humidity_range(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['humidity_min' => '', 'humidity_max' => '', 'humidity_warning_margin' => 30]))
            ->assertSessionHasNoErrors();
    }

    public function test_comma_is_accepted_as_decimal_separator(): void
    {
        $zone = Zone::factory()->create();

        $this->actingAs($this->user())
            ->put("/zones/{$zone->id}", $this->payload(['temp_min' => '10,5', 'temp_max' => '19,5', 'warning_margin' => '1,5']))
            ->assertSessionHasNoErrors();

        $zone->refresh();
        $this->assertSame(10.5, $zone->temp_min);
        $this->assertSame(19.5, $zone->temp_max);
        $this->assertSame(1.5, $zone->warning_margin);
    }

    public function test_new_limits_take_effect_on_the_next_status_evaluation(): void
    {
        $zone = Zone::factory()->create(['device_id' => 'gudang-zona-a', 'temp_min' => 15, 'temp_max' => 25, 'humidity_min' => null, 'humidity_max' => null]);
        SensorLog::factory()->create(['device_id' => 'gudang-zona-a', 'temperature' => 22, 'humidity' => 70, 'created_at' => now()]);
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(ZoneStatus::Aman, $evaluator->forZone($zone->fresh()));

        $this->actingAs($this->user())->put("/zones/{$zone->id}", $this->payload(['temp_min' => 10, 'temp_max' => 20, 'humidity_min' => '', 'humidity_max' => '']));

        $this->assertSame(ZoneStatus::Bahaya, $evaluator->forZone($zone->fresh()));
    }

    public function test_unknown_zone_returns_404(): void
    {
        $this->actingAs($this->user())->get('/zones/999/edit')->assertNotFound();
        $this->actingAs($this->user())->put('/zones/999', $this->payload())->assertNotFound();
    }

    public function test_navigation_links_to_zone_settings(): void
    {
        $this->actingAs($this->user())->get('/dashboard')->assertSee('/zones', false);
    }
}
