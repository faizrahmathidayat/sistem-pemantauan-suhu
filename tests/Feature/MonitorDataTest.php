<?php

namespace Tests\Feature;

use App\Models\SensorLog;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorDataTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $warehouse;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->warehouse = Warehouse::factory()->create(['name' => 'Gudang Monitor']);
        $this->token = $this->warehouse->issueMonitorToken();
        $zone = Zone::factory()->create(['warehouse_id' => $this->warehouse->id, 'name' => 'Zona Satu', 'device_id' => 'dev-mon-1']);
        SensorLog::factory()->create(['device_id' => $zone->device_id, 'temperature' => 20, 'humidity' => 87]);
    }

    public function test_valid_token_returns_snapshot_without_login(): void
    {
        $this->getJson("/monitor/{$this->token}/data")
            ->assertOk()
            ->assertJsonPath('warehouse', 'Gudang Monitor')
            ->assertJsonPath('overall.level', 'aman')
            ->assertJsonPath('zones.0.name', 'Zona Satu')
            ->assertJsonStructure(['warehouse', 'server_time', 'timezone', 'overall' => ['level', 'headline', 'detail'], 'zones']);
    }

    /** @return array<string, array{0: string}> */
    public static function badTokens(): array
    {
        return [
            'acak 40 karakter' => [str_repeat('x', 40)],
            'pendek' => ['abc'],
            'nol' => ['0'],
        ];
    }

    #[DataProvider('badTokens')]
    public function test_unknown_tokens_return_404(string $token): void
    {
        $this->getJson("/monitor/{$token}/data")->assertNotFound();
    }

    public function test_a_token_with_one_character_changed_returns_404(): void
    {
        $off = substr($this->token, 0, -1).($this->token[39] === 'a' ? 'b' : 'a');

        $this->getJson("/monitor/{$off}/data")->assertNotFound();
    }

    public function test_old_token_is_404_after_reissue_or_revoke(): void
    {
        $new = $this->warehouse->issueMonitorToken();

        $this->getJson("/monitor/{$this->token}/data")->assertNotFound();
        $this->getJson("/monitor/{$new}/data")->assertOk();

        $this->warehouse->revokeMonitorToken();
        $this->getJson("/monitor/{$new}/data")->assertNotFound();
    }

    public function test_response_headers_forbid_caching_indexing_and_referrer(): void
    {
        foreach ([$this->getJson("/monitor/{$this->token}/data"), $this->getJson('/monitor/salah/data')] as $response) {
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
            $this->assertSame('noindex, nofollow', $response->headers->get('X-Robots-Tag'));
            $this->assertSame('no-referrer', $response->headers->get('Referrer-Policy'));
        }
    }

    public function test_polling_does_not_create_a_session_or_cookies(): void
    {
        $response = $this->getJson("/monitor/{$this->token}/data");

        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_route_is_throttled_and_not_behind_auth(): void
    {
        $middleware = Route::getRoutes()->getByName('monitor.data')->gatherMiddleware();

        $this->assertContains('throttle:300,1,monitor-data', $middleware);
        $this->assertNotContains('auth', $middleware);
    }

    public function test_polling_does_not_use_up_the_login_or_page_rate_limits(): void
    {
        // Setiap throttle tanpa nama memakai kunci IP yang sama; tanpa awalan sendiri, polling TV
        // menghabiskan jatah login (10/menit) dan jatah halaman monitor (60/menit).
        for ($i = 0; $i < 61; $i++) {
            $this->getJson("/monitor/{$this->token}/data")->assertOk();
        }

        $this->post('/login', ['email' => 'tidak@ada.test', 'password' => 'salah'])->assertStatus(302);
        $this->get("/monitor/{$this->token}")->assertOk();
    }

    public function test_logged_in_user_gets_the_same_result(): void
    {
        $guest = $this->getJson("/monitor/{$this->token}/data")->json('zones');

        $member = $this->actingAs(User::factory()->create())->getJson("/monitor/{$this->token}/data");

        $member->assertOk();
        $this->assertSame($guest, $member->json('zones'));
    }
}
