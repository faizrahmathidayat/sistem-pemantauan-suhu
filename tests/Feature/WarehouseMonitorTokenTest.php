<?php

namespace Tests\Feature;

use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarehouseMonitorTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_issue_returns_a_40_char_token_and_stores_only_its_hash(): void
    {
        $warehouse = Warehouse::factory()->create();

        $token = $warehouse->issueMonitorToken();

        $this->assertSame(40, strlen($token));
        $this->assertSame(hash('sha256', $token), $warehouse->fresh()->monitor_token_hash);
        $this->assertStringNotContainsString($token, json_encode(DB::table('warehouses')->first()));
    }

    public function test_find_by_token_matches_only_the_exact_token(): void
    {
        $warehouse = Warehouse::factory()->create();
        $token = $warehouse->issueMonitorToken();
        $off = substr($token, 0, -1).($token[39] === 'a' ? 'b' : 'a');

        $this->assertTrue($warehouse->is(Warehouse::findByMonitorToken($token)));
        $this->assertNull(Warehouse::findByMonitorToken(null));
        $this->assertNull(Warehouse::findByMonitorToken(''));
        $this->assertNull(Warehouse::findByMonitorToken('0'));
        $this->assertNull(Warehouse::findByMonitorToken($off));
    }

    public function test_reissuing_invalidates_the_previous_token(): void
    {
        $warehouse = Warehouse::factory()->create();
        $first = $warehouse->issueMonitorToken();
        $second = $warehouse->issueMonitorToken();

        $this->assertNotSame($first, $second);
        $this->assertNull(Warehouse::findByMonitorToken($first));
        $this->assertTrue($warehouse->is(Warehouse::findByMonitorToken($second)));
    }

    public function test_revoke_clears_the_token_and_find_returns_null(): void
    {
        $warehouse = Warehouse::factory()->create();
        $token = $warehouse->issueMonitorToken();
        $this->assertTrue($warehouse->hasMonitorToken());

        $warehouse->revokeMonitorToken();

        $this->assertFalse($warehouse->fresh()->hasMonitorToken());
        $this->assertNull(Warehouse::findByMonitorToken($token));
    }

    public function test_hash_is_hidden_from_serialisation(): void
    {
        $warehouse = Warehouse::factory()->create();
        $warehouse->issueMonitorToken();

        $this->assertArrayNotHasKey('monitor_token_hash', $warehouse->fresh()->toArray());
    }

    public function test_two_warehouses_never_share_a_token(): void
    {
        $a = Warehouse::factory()->create();
        $b = Warehouse::factory()->create();
        $tokenA = $a->issueMonitorToken();
        $tokenB = $b->issueMonitorToken();

        $this->assertTrue($a->is(Warehouse::findByMonitorToken($tokenA)));
        $this->assertTrue($b->is(Warehouse::findByMonitorToken($tokenB)));
        $this->assertNotSame($tokenA, $tokenB);
    }
}
