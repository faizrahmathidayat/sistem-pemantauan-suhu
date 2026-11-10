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

    public function test_url_can_be_shown_again_from_an_encrypted_copy(): void
    {
        $warehouse = Warehouse::factory()->create();
        $token = $warehouse->issueMonitorToken();

        $this->assertSame(route('monitor.show', $token), $warehouse->fresh()->monitorUrl());
        $this->assertStringNotContainsString($token, json_encode(DB::table('warehouses')->first()));
        $this->assertArrayNotHasKey('monitor_token', $warehouse->fresh()->toArray());
    }

    public function test_revoke_also_clears_the_visible_url(): void
    {
        $warehouse = Warehouse::factory()->create();
        $warehouse->issueMonitorToken();

        $warehouse->revokeMonitorToken();

        $this->assertNull($warehouse->fresh()->monitorUrl());
    }

    public function test_token_issued_before_the_copy_existed_still_works_but_has_no_url_to_show(): void
    {
        $warehouse = Warehouse::factory()->create();
        $token = $warehouse->issueMonitorToken();
        DB::table('warehouses')->where('id', $warehouse->id)->update(['monitor_token' => null]);

        $this->assertTrue($warehouse->fresh()->hasMonitorToken());
        $this->assertNull($warehouse->fresh()->monitorUrl());
        $this->assertTrue($warehouse->is(Warehouse::findByMonitorToken($token)));
    }

    public function test_an_unreadable_copy_gives_no_url_instead_of_an_error(): void
    {
        $warehouse = Warehouse::factory()->create();
        $warehouse->issueMonitorToken();
        DB::table('warehouses')->where('id', $warehouse->id)->update(['monitor_token' => 'bukan-hasil-enkripsi']);

        $this->assertNull($warehouse->fresh()->monitorUrl());
        $this->assertTrue($warehouse->fresh()->hasMonitorToken());
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
