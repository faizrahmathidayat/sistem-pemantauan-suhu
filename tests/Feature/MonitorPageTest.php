<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class MonitorPageTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang TV']);
        $this->token = $this->warehouse->issueMonitorToken();
        $zone = Zone::factory()->create(['warehouse_id' => $this->warehouse->id, 'name' => 'Zona TV', 'device_id' => 'dev-tv-1']);
        SensorLog::factory()->create(['device_id' => $zone->device_id, 'temperature' => 20, 'humidity' => 87]);
    }

    public function test_page_renders_without_login_with_the_first_snapshot_embedded(): void
    {
        $html = $this->get("/monitor/{$this->token}")->assertOk()->getContent();

        $this->assertStringContainsString('<title>Gudang TV · Monitor</title>', $html);
        $this->assertStringContainsString('<meta name="robots" content="noindex, nofollow">', $html);
        $this->assertStringContainsString('data-url="'.route('monitor.data', $this->token).'"', $html);

        preg_match('~<script type="application/json" id="monitor-initial">(.*?)</script>~s', $html, $match);
        $snapshot = json_decode($match[1] ?? '', true);
        $this->assertIsArray($snapshot);
        $this->assertSame('Gudang TV', $snapshot['warehouse']);
        $this->assertSame('Zona TV', $snapshot['zones'][0]['name']);
    }

    public function test_page_is_a_standalone_document(): void
    {
        $html = $this->get("/monitor/{$this->token}")->assertOk()->getContent();

        foreach (['csrf-token', 'href="/login"', '<form', '<button', 'id="sidebar"', '<a '] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "halaman monitor tidak boleh memuat {$forbidden}");
        }

        $this->assertStringContainsString(
            "@vite(['resources/css/app.css', 'resources/js/monitor.js'])",
            file_get_contents(resource_path('views/monitor/show.blade.php')),
        );
    }

    public function test_unknown_token_is_404(): void
    {
        $this->get('/monitor/'.str_repeat('x', 40))->assertNotFound();
        $this->get('/monitor/abc')->assertNotFound();
    }

    public function test_old_token_is_404_after_reissue(): void
    {
        $this->warehouse->issueMonitorToken();

        $this->get("/monitor/{$this->token}")->assertNotFound();
    }

    public function test_headers_forbid_caching_indexing_and_referrer_and_no_cookie_is_set(): void
    {
        foreach ([$this->get("/monitor/{$this->token}"), $this->get('/monitor/salah')] as $response) {
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
            $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
            $this->assertSame([], $response->headers->getCookies());
        }
    }

    public function test_route_is_throttled_60_per_minute_and_not_behind_auth(): void
    {
        $middleware = Route::getRoutes()->getByName('monitor.show')->gatherMiddleware();

        $this->assertContains('throttle:60,1', $middleware);
        $this->assertNotContains('auth', $middleware);
    }

    public function test_page_does_not_leak_other_warehouses_or_products(): void
    {
        $other = Warehouse::factory()->create();
        Zone::factory()->create(['warehouse_id' => $other->id, 'name' => 'Zona Orang Lain']);
        Zone::query()->where('name', 'Zona TV')->first()->syncProducts(['Telur Rahasia Sekali']);

        $html = $this->get("/monitor/{$this->token}")->assertOk()->getContent();

        $this->assertStringNotContainsString('Zona Orang Lain', $html);
        $this->assertStringNotContainsString('Telur Rahasia Sekali', $html);
    }

    public function test_embedded_json_cannot_break_out_of_the_script_tag(): void
    {
        Zone::query()->where('name', 'Zona TV')->update(['name' => '</script><script>alert(1)</script>']);

        $html = $this->get("/monitor/{$this->token}")->assertOk()->getContent();

        $this->assertStringNotContainsString('</script><script>alert(1)', $html);
    }

    public function test_script_contract(): void
    {
        $js = file_get_contents(resource_path('js/monitor.js'));

        $this->assertStringContainsString('setInterval', $js);
        $this->assertStringContainsString('STALE_AFTER_MS = 45_000', $js);
        $this->assertStringContainsString('TIDAK TERHUBUNG KE SERVER', $js);
        $this->assertStringContainsString('Alamat monitor ini tidak berlaku lagi', $js);
        $this->assertStringContainsString('location.reload', $js);
        $this->assertStringContainsString("cache: 'no-store'", $js);
        $this->assertStringContainsString('resources/js/monitor.js', file_get_contents(base_path('vite.config.js')));
    }
}
