<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListPagesUiTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create();
    }

    private function table(string $html): string
    {
        preg_match('~<table.*?</table>~s', $html, $match);

        return $match[0] ?? '';
    }

    private function mobileList(string $html): string
    {
        preg_match('~<ul[^>]*data-mobile-list.*?</ul>~s', $html, $match);

        return $match[0] ?? '';
    }

    public function test_incidents_page_renders_table_and_mobile_cards_with_same_data(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Uji']);
        $zone = Zone::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Zona Uji']);
        $handler = User::factory()->create(['name' => 'Siti Penangan']);
        Incident::factory()->for($zone)->create(['acknowledged_by' => $handler->id, 'acknowledged_at' => now()]);
        Incident::factory()->for($zone)->create(['started_at' => now()->subDay(), 'ended_at' => now()->subDay()->addHour()]);

        $html = $this->actingAs($this->admin())->get('/incidents')->assertOk()->getContent();

        foreach ([$this->table($html), $this->mobileList($html)] as $layout) {
            $this->assertStringContainsString('Zona Uji', $layout);
            $this->assertStringContainsString('Gudang Uji', $layout);
            $this->assertStringContainsString('Ditangani oleh Siti Penangan', $layout);
            $this->assertStringContainsString('Belum ditangani', $layout);
        }
    }

    public function test_incidents_pagination_links_are_rendered(): void
    {
        Incident::factory()->count(18)->create();

        $this->actingAs($this->admin())->get('/incidents')->assertOk()->assertSee('page=2', false);
    }

    public function test_empty_states_keep_their_text(): void
    {
        $user = $this->admin();

        $this->actingAs($user)->get('/incidents')->assertSee('Belum ada insiden.');
        $this->actingAs($user)->get('/notifications')->assertSee('Belum ada notifikasi.');
        $this->actingAs($user)->get('/audit-logs')->assertSee('Belum ada perubahan tercatat.');
    }

    public function test_audit_log_page_lists_changes_in_both_layouts(): void
    {
        $log = AuditLog::factory()->create(['user_name' => 'Budi Pengubah', 'subject_label' => 'Zona Audit']);
        $line = $log->changeLines()[0];

        $html = $this->actingAs($this->admin())->get('/audit-logs')->assertOk()->getContent();

        foreach ([$this->table($html), $this->mobileList($html)] as $layout) {
            $this->assertStringContainsString('Budi Pengubah', $layout);
            $this->assertStringContainsString('Zona Audit', $layout);
            $this->assertStringContainsString($line['label'], $layout);
            $this->assertStringContainsString($line['from'], $layout);
            $this->assertStringContainsString($line['to'], $layout);
        }
    }

    public function test_notification_page_marks_unread_items_and_offers_read_all(): void
    {
        $user = $this->admin();
        foreach (['Zona Baru', 'Zona Lama'] as $name) {
            $zone = Zone::factory()->create(['name' => $name]);
            $incident = Incident::factory()->for($zone)->create();
            $log = SensorLog::factory()->create(['device_id' => $zone->device_id, 'temperature' => 29.4]);
            $user->notify(new IncidentOpened($incident, $log));
        }
        $user->notifications()->latest()->first()->markAsRead();

        $html = $this->actingAs($user)->get('/notifications')->assertOk()->assertSee('Tandai semua terbaca')->getContent();

        $this->assertSame(1, substr_count($html, 'data-unread="true"'));
    }

    public function test_zone_settings_lists_each_warehouse_with_zone_cards(): void
    {
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Batas']);
        Zone::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Zona Batas', 'device_id' => 'dev-batas']);

        $this->actingAs($this->admin())->get('/zones')->assertOk()
            ->assertSee('Gudang Batas')->assertSee('Zona Batas')->assertSee('dev-batas')
            ->assertSee('Ubah batas')->assertSee('Kelola zona')->assertSee('+ Zona');
    }
}
