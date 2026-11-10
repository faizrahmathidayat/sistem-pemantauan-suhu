<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\Role;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardUiTest extends TestCase
{
    use RefreshDatabase;

    private Zone $zone;

    protected function setUp(): void
    {
        parent::setUp();

        $warehouse = Warehouse::factory()->create(['name' => 'Gudang Uji']);
        $this->zone = Zone::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Zona Uji', 'device_id' => 'dev-ui']);
        SensorLog::factory()->create(['device_id' => 'dev-ui', 'temperature' => 18, 'humidity' => 85]);
    }

    public function test_dashboard_keeps_the_javascript_contract(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();

        foreach (['id="dashboard"', 'id="warehouse-chips"', 'id="zones"', 'id="banners"', 'id="chart-zone-chips"',
            'id="chart-metric-chips"', 'id="trend-chart"', 'id="chart-empty"', 'id="scheduler-warning"', 'id="connection-warning"',
            'data-metric="temperature"', 'data-metric="humidity"'] as $needle) {
            $this->assertStringContainsString($needle, $html, $needle);
        }

        preg_match('~<script id="dashboard-data" type="application/json">(.*?)</script>~s', $html, $match);
        $data = json_decode($match[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['Zona Uji'], array_column($data['zones'], 'name'));
        $this->assertSame(['Gudang Uji'], array_column($data['warehouses'], 'name'));
        $this->assertArrayHasKey('banners', $data);
    }

    public function test_dashboard_has_page_header_cards_and_hidden_status_alerts(): void
    {
        $html = $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->getContent();

        $this->assertStringContainsString('Pantau suhu dan kelembapan', $html, 'deskripsi header halaman');
        $this->assertStringContainsString('Tren 24 jam terakhir', $html);
        $this->assertStringContainsString('Insiden terbaru', $html);
        $this->assertMatchesRegularExpression('~id="scheduler-warning"[^>]*\bhidden\b|\bhidden\b[^>]*id="scheduler-warning"~', $html);
        $this->assertMatchesRegularExpression('~id="connection-warning"[^>]*\bhidden\b|\bhidden\b[^>]*id="connection-warning"~', $html);
    }

    public function test_dashboard_shows_recent_incidents_card_and_report_link(): void
    {
        Incident::factory()->for($this->zone)->create(['started_at' => now()->subHour(), 'ended_at' => now()]);

        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()
            ->assertSee('Gudang Uji › Zona Uji')
            ->assertSee('Selesai')
            ->assertSee('Riwayat lengkap');
    }

    public function test_no_access_user_sees_notice_and_empty_data(): void
    {
        $operator = User::factory()->withRole(Role::where('name', 'Operator')->sole())->create();

        $html = $this->actingAs($operator)->get('/dashboard')->assertOk()
            ->assertSee('Anda belum ditugaskan ke gudang mana pun.')->getContent();

        preg_match('~<script id="dashboard-data" type="application/json">(.*?)</script>~s', $html, $match);
        $data = json_decode($match[1], true, flags: JSON_THROW_ON_ERROR);
        $this->assertFalse($data['has_access']);
        $this->assertSame([], $data['zones']);
    }
}
