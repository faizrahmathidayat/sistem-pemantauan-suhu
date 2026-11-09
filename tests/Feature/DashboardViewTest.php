<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardViewTest extends TestCase
{
    use RefreshDatabase;

    private function visit()
    {
        return $this->actingAs(User::factory()->create())->get('/dashboard');
    }

    public function test_dashboard_renders_for_logged_in_user_with_no_zones(): void
    {
        $this->visit()->assertOk()->assertSee('id="zones"', false)->assertSee('"zones":[]', false);
    }

    public function test_dashboard_embeds_initial_zone_data_for_first_paint(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona B (Sayur Daun)']);
        SensorLog::factory()->create(['device_id' => $zone->device_id, 'temperature' => 30, 'humidity' => 88]);

        $this->visit()->assertOk()
            ->assertSee('Zona B (Sayur Daun)')
            ->assertSee('"status":"bahaya"', false);
    }

    public function test_dashboard_page_contains_endpoints_for_the_script(): void
    {
        $this->visit()->assertOk()
            ->assertSee('data-data-url="'.url('/dashboard/data').'"', false)
            ->assertSee('data-readings-url="'.url('/zones/__ID__/readings').'"', false)
            ->assertSee('data-ack-url="'.url('/incidents/__ID__/acknowledge').'"', false);
    }

    public function test_dashboard_lists_recent_incidents_with_acknowledger(): void
    {
        $zone = Zone::factory()->create(['name' => 'Zona A']);
        $budi = User::factory()->create(['name' => 'Budi Santoso']);
        Incident::factory()->for($zone)->create(['acknowledged_by' => $budi->id, 'acknowledged_at' => now()]);

        $this->visit()->assertOk()->assertSee('Zona A')->assertSee('Budi Santoso');
    }

    public function test_dashboard_view_requests_the_dashboard_script_through_vite(): void
    {
        $source = file_get_contents(resource_path('views/dashboard.blade.php'));

        $this->assertStringContainsString("@vite(['resources/js/dashboard.js'])", $source);
    }
}
