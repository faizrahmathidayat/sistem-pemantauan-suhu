<?php

namespace Tests\Feature;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class OfflineDetectionTest extends TestCase
{
    use RefreshDatabase;

    private function zoneWithLastLog(int $secondsAgo, string $deviceId = 'gudang-zona-a'): Zone
    {
        $zone = Zone::factory()->create(['device_id' => $deviceId]);
        SensorLog::factory()->create(['device_id' => $deviceId, 'created_at' => now()->subSeconds($secondsAgo)]);

        return $zone;
    }

    public function test_zone_silent_for_over_two_minutes_gets_one_offline_incident_and_notification(): void
    {
        Notification::fake();
        $users = User::factory()->count(2)->create();
        $this->zoneWithLastLog(180);

        Artisan::call('zones:check-offline');

        $incident = Incident::sole();
        $this->assertSame(IncidentReason::SensorOffline, $incident->reason);
        $this->assertTrue($incident->isOpen());
        foreach ($users as $user) {
            Notification::assertSentToTimes($user, IncidentOpened::class, 1);
        }
    }

    public function test_running_twice_does_not_duplicate(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $this->zoneWithLastLog(180);

        Artisan::call('zones:check-offline');
        Artisan::call('zones:check-offline');

        $this->assertSame(1, Incident::count());
        Notification::assertSentToTimes($user, IncidentOpened::class, 1);
    }

    public function test_zone_with_fresh_log_is_not_offline(): void
    {
        $this->zoneWithLastLog(30);

        Artisan::call('zones:check-offline');

        $this->assertSame(0, Incident::count());
    }

    public function test_zone_with_no_logs_is_never_offline(): void
    {
        Zone::factory()->create();

        Artisan::call('zones:check-offline');

        $this->assertSame(0, Incident::count());
    }

    public function test_new_reading_via_api_closes_offline_incident_without_new_notification(): void
    {
        Notification::fake();
        $user = User::factory()->create();
        $zone = $this->zoneWithLastLog(180);
        $zone->update(['device_token_hash' => hash('sha256', 'rahasia')]);
        Artisan::call('zones:check-offline');

        $this->postJson('/api/v1/telemetry', [
            'device_id' => 'gudang-zona-a', 'temperature' => 20, 'humidity' => 85,
        ], ['Authorization' => 'Bearer rahasia'])->assertCreated();

        $this->assertFalse(Incident::sole()->isOpen());
        Notification::assertSentToTimes($user, IncidentOpened::class, 1);
    }

    public function test_command_records_last_check_time(): void
    {
        $this->assertNull(Cache::get('zones:last-offline-check'));

        Artisan::call('zones:check-offline');

        $this->assertNotNull(Cache::get('zones:last-offline-check'));
    }

    public function test_schedule_registers_both_commands(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('zones:check-offline', $output);
        $this->assertStringContainsString('model:prune', $output);
    }
}
