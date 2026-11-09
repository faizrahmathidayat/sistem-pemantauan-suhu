<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use App\Services\IncidentManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Database\Eloquent\Collection;
use Tests\TestCase;

class IncidentLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    /** @var Collection<int, User> */
    private Collection $users;

    protected function setUp(): void
    {
        parent::setUp();

        $this->zone = Zone::factory()->create([
            'name' => 'Zona B (Sayur Daun)', 'device_id' => 'gudang-zona-b',
            'temp_min' => 15, 'temp_max' => 25, 'warning_margin' => 2,
            'humidity_min' => 80, 'humidity_max' => 95, 'humidity_warning_margin' => 5,
        ]);
        $this->users = User::factory()->count(2)->create();
    }

    private function reading(float $temperature, float $humidity = 88): SensorLog
    {
        $log = SensorLog::factory()->create([
            'device_id' => $this->zone->device_id,
            'temperature' => $temperature,
            'humidity' => $humidity,
        ]);
        app(IncidentManager::class)->handleReading($this->zone->fresh(), $log);

        return $log;
    }

    private function tick(int $seconds = 30): void
    {
        $this->travel($seconds)->seconds();
    }

    private function dangerTwice(float $temperature = 26.0): void
    {
        $this->reading($temperature);
        $this->tick();
        $this->reading($temperature);
        $this->tick();
    }

    public function test_single_danger_reading_does_not_open_incident(): void
    {
        Notification::fake();

        $this->reading(26);

        $this->assertSame(0, Incident::count());
        Notification::assertNothingSent();
    }

    public function test_opens_incident_after_two_consecutive_danger_readings_and_notifies_all_users(): void
    {
        Notification::fake();

        $this->reading(26);
        $this->tick();
        $this->reading(26.5);

        $incident = Incident::sole();
        $this->assertSame(IncidentReason::SuhuTinggi, $incident->reason);
        $this->assertTrue($incident->isOpen());
        $this->assertEquals(26.5, $incident->peak_value);
        foreach ($this->users as $user) {
            Notification::assertSentToTimes($user, IncidentOpened::class, 1);
        }
    }

    public function test_gap_over_two_minutes_breaks_the_streak(): void
    {
        $this->reading(26);
        $this->tick(121);
        $this->reading(26);

        $this->assertSame(0, Incident::count());
    }

    public function test_no_extra_notification_while_incident_open(): void
    {
        Notification::fake();

        $this->dangerTwice();
        $this->dangerTwice();

        $this->assertSame(1, Incident::count());
        Notification::assertSentToTimes($this->users->first(), IncidentOpened::class, 1);
    }

    public function test_peak_value_updates_when_reading_is_worse(): void
    {
        $this->reading(26);
        $this->tick();
        $this->reading(26);
        $this->tick();
        $this->reading(28);
        $this->tick();
        $this->reading(27);

        $this->assertEquals(28, Incident::sole()->peak_value);
    }

    public function test_closes_after_two_non_danger_readings_and_waspada_counts_as_non_danger(): void
    {
        $this->dangerTwice();

        $this->reading(24); // waspada
        $this->assertTrue(Incident::sole()->isOpen(), 'satu pembacaan normal belum cukup menutup');
        $this->tick();
        $this->reading(20);

        $this->assertFalse(Incident::sole()->isOpen());
    }

    public function test_closing_sends_no_notification(): void
    {
        Notification::fake();

        $this->dangerTwice();
        $this->reading(20);
        $this->tick();
        $this->reading(20);

        $this->assertFalse(Incident::sole()->isOpen());
        Notification::assertSentToTimes($this->users->first(), IncidentOpened::class, 1);
    }

    public function test_reopens_with_new_notification_after_closing(): void
    {
        Notification::fake();

        $this->dangerTwice();
        $this->reading(20);
        $this->tick();
        $this->reading(20);
        $this->tick();
        $this->dangerTwice();

        $this->assertSame(2, Incident::count());
        $this->assertSame(1, Incident::open()->count());
        Notification::assertSentToTimes($this->users->first(), IncidentOpened::class, 2);
    }

    public function test_second_reason_opens_second_incident_and_notification(): void
    {
        Notification::fake();

        $this->dangerTwice(26); // suhu tinggi terbuka
        $this->reading(26, 70);
        $this->tick();
        $this->reading(26, 70);

        $this->assertEqualsCanonicalizing(
            [IncidentReason::SuhuTinggi, IncidentReason::LembapRendah],
            Incident::open()->get()->pluck('reason')->all(),
        );
        Notification::assertSentToTimes($this->users->first(), IncidentOpened::class, 2);
    }

    public function test_null_humidity_limits_never_open_humidity_incident(): void
    {
        $this->zone->update(['humidity_min' => null, 'humidity_max' => null]);

        $this->reading(20, 5);
        $this->tick();
        $this->reading(20, 5);
        $this->tick();
        $this->reading(20, 5);

        $this->assertSame(0, Incident::count());
    }

    public function test_new_reading_closes_open_offline_incident_without_notification(): void
    {
        Notification::fake();
        Incident::factory()->for($this->zone)->create([
            'reason' => IncidentReason::SensorOffline, 'peak_value' => null,
        ]);

        $this->reading(20);

        $this->assertFalse(Incident::sole()->isOpen());
        Notification::assertNothingSent();
    }

    public function test_notification_payload_contains_zone_temperature_limit_and_advice(): void
    {
        $this->reading(26);
        $this->tick();
        $this->reading(26.4);

        $data = $this->users->first()->notifications()->sole()->data;

        $this->assertSame('Zona B (Sayur Daun)', $data['zone_name']);
        $this->assertSame('suhu_tinggi', $data['reason']);
        $this->assertEquals(26.4, $data['temperature']);
        $this->assertEquals(25, $data['limit']);
        $this->assertEquals(88, $data['humidity']);
        $this->assertNotEmpty($data['advice']);
        $this->assertStringContainsString('Zona B', $data['title']);
        $this->assertStringEndsWith('/dashboard', $data['url']);
        $this->assertSame(Incident::sole()->id, $data['incident_id']);
    }

    public function test_telemetry_endpoint_triggers_incident_end_to_end(): void
    {
        Notification::fake();
        $this->zone->update(['device_token_hash' => hash('sha256', 'rahasia')]);
        $send = fn () => $this->postJson('/api/v1/telemetry', [
            'device_id' => 'gudang-zona-b', 'temperature' => 29.4, 'humidity' => 82,
        ], ['Authorization' => 'Bearer rahasia'])->assertCreated();

        $send();
        $this->assertSame(0, Incident::count());
        $this->tick();
        $send();

        $this->assertSame(1, Incident::open()->count());
        Notification::assertSentTo($this->users->first(), IncidentOpened::class);
    }
}
