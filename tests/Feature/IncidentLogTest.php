<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\User;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentLogTest extends TestCase
{
    use RefreshDatabase;

    private function visit(string $query = '')
    {
        return $this->actingAs(User::factory()->create())->get('/incidents'.$query);
    }

    public function test_incident_log_lists_newest_first_with_duration_and_acknowledger(): void
    {
        $budi = User::factory()->create(['name' => 'Budi Santoso']);
        $old = Zone::factory()->create(['name' => 'Zona Lama']);
        $new = Zone::factory()->create(['name' => 'Zona Baru']);
        Incident::factory()->for($old)->create([
            'started_at' => now()->subDays(2), 'ended_at' => now()->subDays(2)->addMinutes(29), 'peak_value' => 21.8,
        ]);
        Incident::factory()->for($new)->create([
            'started_at' => now()->subHours(3), 'ended_at' => now()->subHours(1), 'peak_value' => 28.5,
            'acknowledged_by' => $budi->id, 'acknowledged_at' => now()->subHours(2),
        ]);

        $this->visit()->assertOk()
            ->assertSeeInOrder(['Zona Baru', 'Zona Lama'])
            ->assertSee('2 jam')
            ->assertSee('29 mnt')
            ->assertSee('28,5')
            ->assertSee('Budi Santoso');
    }

    public function test_open_incident_shows_berlangsung_and_belum_ditangani(): void
    {
        Incident::factory()->for(Zone::factory()->create(['name' => 'Zona Aktif']))->create(['ended_at' => null]);

        $this->visit()->assertOk()->assertSee('berlangsung')->assertSee('Belum ditangani');
    }

    public function test_offline_incident_has_no_peak_value(): void
    {
        Incident::factory()->create(['reason' => IncidentReason::SensorOffline, 'peak_value' => null]);

        $this->visit()->assertOk()->assertSee('Sensor tidak mengirim data');
    }

    public function test_incident_log_paginates_at_15(): void
    {
        $zone = Zone::factory()->create();
        Incident::factory()->count(16)->for($zone)->create();

        $first = $this->visit()->viewData('incidents');
        $this->assertSame(15, $first->count());
        $this->assertSame(16, $first->total());

        $this->assertSame(1, $this->visit('?page=2')->viewData('incidents')->count());
    }

    public function test_empty_incident_log_renders(): void
    {
        $this->visit()->assertOk()->assertSee('Belum ada insiden');
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/incidents')->assertRedirect(route('login'));
    }
}
