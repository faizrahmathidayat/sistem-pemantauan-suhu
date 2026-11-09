<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationPageTest extends TestCase
{
    use RefreshDatabase;

    private function notify(User $user, string $zoneName): string
    {
        $zone = Zone::factory()->create(['name' => $zoneName]);
        $incident = Incident::factory()->for($zone)->create();
        $log = SensorLog::factory()->create(['device_id' => $zone->device_id, 'temperature' => 29.4]);
        $user->notify(new IncidentOpened($incident, $log));

        return $user->notifications()->latest()->first()->id;
    }

    public function test_user_sees_only_own_notifications_and_unread_count(): void
    {
        $budi = User::factory()->create();
        $sari = User::factory()->create();
        $this->notify($budi, 'Zona Satu');
        $readId = $this->notify($budi, 'Zona Tiga');
        $budi->notifications()->whereKey($readId)->first()->markAsRead();
        $this->notify($sari, 'Zona Dua');

        $response = $this->actingAs($budi)->get('/notifications');

        $response->assertOk()->assertSee('Zona Satu')->assertSee('Zona Tiga')->assertDontSee('Zona Dua');
        $response->assertSee('data-unread-badge', false);
        $this->assertSame(1, $budi->unreadNotifications()->count());
    }

    public function test_notification_page_shows_title_and_advice(): void
    {
        $user = User::factory()->create();
        $this->notify($user, 'Zona Sayur');

        $this->actingAs($user)->get('/notifications')
            ->assertSee('Suhu terlalu tinggi di Zona Sayur')
            ->assertSee('exhaust');
    }

    public function test_mark_as_read_and_read_all(): void
    {
        $user = User::factory()->create();
        $first = $this->notify($user, 'Zona A');
        $this->notify($user, 'Zona B');

        $this->actingAs($user)->post("/notifications/{$first}/read")->assertRedirect();
        $this->assertSame(1, $user->unreadNotifications()->count());

        $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();
        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_cannot_mark_another_users_notification(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $id = $this->notify($owner, 'Zona A');

        $this->actingAs($other)->post("/notifications/{$id}/read")->assertNotFound();

        $this->assertSame(1, $owner->unreadNotifications()->count());
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/notifications')->assertRedirect(route('login'));
    }
}
