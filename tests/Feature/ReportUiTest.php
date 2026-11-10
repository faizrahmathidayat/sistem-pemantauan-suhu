<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportUiTest extends TestCase
{
    use RefreshDatabase;

    private Zone $a1;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-10 12:00:00');
        $warehouse = Warehouse::factory()->create(['name' => 'Gudang A']);
        $this->a1 = Zone::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Zona A1', 'device_id' => 'dev-a1']);
        Zone::factory()->create(['warehouse_id' => $warehouse->id, 'name' => 'Zona A2', 'device_id' => 'dev-a2']);
        foreach ([[18, 85], [20, 90], [22, 95]] as $i => [$temperature, $humidity]) {
            $at = "2026-11-10 10:0{$i}:00";
            SensorLog::factory()->create(['device_id' => 'dev-a1', 'temperature' => $temperature, 'humidity' => $humidity, 'created_at' => $at, 'updated_at' => $at]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function page(string $query = ''): string
    {
        return $this->actingAs(User::factory()->create())->get('/reports'.$query)->assertOk()->getContent();
    }

    private function section(string $html, string $pattern): string
    {
        preg_match($pattern, $html, $match);

        return $match[0] ?? '';
    }

    public function test_summary_is_a_table_on_desktop_and_cards_on_mobile(): void
    {
        $html = $this->page();

        foreach ([$this->section($html, '~<table.*?</table>~s'), $this->section($html, '~<ul[^>]*data-mobile-list.*?</ul>~s')] as $layout) {
            $this->assertStringContainsString('Zona A1', $layout);
            $this->assertStringContainsString('Zona A2', $layout);
            $this->assertStringContainsString('18,0', $layout);
            $this->assertStringContainsString('22,0', $layout);
            $this->assertStringContainsString('100,0 %', $layout);
        }
    }

    public function test_filter_form_keeps_field_names_and_custom_dates_toggle(): void
    {
        $html = $this->page();

        foreach (['name="gudang"', 'name="zona"', 'name="periode"', 'name="dari"', 'name="sampai"'] as $field) {
            $this->assertStringContainsString($field, $html, $field);
        }
        $this->assertMatchesRegularExpression('~id="custom-dates"[^>]*\bhidden\b~', $html);

        $custom = $this->page('?periode=custom&dari=2026-11-05&sampai=2026-11-09');
        $this->assertDoesNotMatchRegularExpression('~id="custom-dates"[^>]*\bhidden\b~', $custom);
    }

    public function test_download_print_and_print_header_remain(): void
    {
        $html = $this->page("?zona={$this->a1->id}&periode=24h");

        $this->assertStringContainsString(route('reports.summary-csv', ['gudang' => $this->a1->warehouse_id, 'zona' => $this->a1->id, 'periode' => '24h']), html_entity_decode($html));
        $this->assertStringContainsString('Unduh data mentah (CSV)', $html);
        $this->assertStringContainsString('Cetak / Simpan PDF', $html);
        $this->assertMatchesRegularExpression('~class="hidden print:block".*?Laporan sensor Gudang A.*?Zona: Zona A1~s', $html);
    }

    public function test_percent_in_range_uses_a_status_badge(): void
    {
        $mobile = $this->section($this->page(), '~<ul[^>]*data-mobile-list.*?</ul>~s');

        $this->assertStringContainsString('bg-success-50', $mobile, '100 % dalam batas = badge hijau');
    }
}
