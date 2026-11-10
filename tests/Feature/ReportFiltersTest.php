<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Support\ReportFilters;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class ReportFiltersTest extends TestCase
{
    use RefreshDatabase;

    private Warehouse $a;

    private Warehouse $b;

    private Zone $a1;

    private Zone $a2;

    private Zone $b1;

    protected function setUp(): void
    {
        parent::setUp();

        $this->a = Warehouse::factory()->create(['name' => 'Gudang A']);
        $this->b = Warehouse::factory()->create(['name' => 'Gudang B']);
        $this->a2 = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona A2', 'device_id' => 'dev-a2']);
        $this->a1 = Zone::factory()->create(['warehouse_id' => $this->a->id, 'name' => 'Zona A1', 'device_id' => 'dev-a1']);
        $this->b1 = Zone::factory()->create(['warehouse_id' => $this->b->id, 'name' => 'Zona B1', 'device_id' => 'dev-b1']);
    }

    private function operator(Warehouse ...$warehouses): User
    {
        return User::factory()->withRole(Role::where('name', 'Operator')->sole())->assignedTo(...$warehouses)->create();
    }

    /** @param array<string, mixed> $query */
    private function resolve(User $user, array $query = []): ReportFilters
    {
        return ReportFilters::resolve(Request::create('/reports', 'GET', $query), $user);
    }

    private function assertAbortsWith(int $status, callable $call): void
    {
        try {
            $call();
            $this->fail("HttpException {$status} tidak dilempar");
        } catch (HttpException $e) {
            $this->assertSame($status, $e->getStatusCode());
        }
    }

    public function test_defaults_to_first_visible_warehouse_first_zone_and_seven_days(): void
    {
        $filters = $this->resolve($this->operator($this->a));

        $this->assertSame($this->a->id, $filters->warehouse->id);
        $this->assertSame($this->a1->id, $filters->zone->id);
        $this->assertSame('7d', $filters->periodKey);
        $this->assertSame('7d', $filters->period->key);
        $this->assertNull($filters->error);
    }

    public function test_administrator_defaults_to_first_warehouse_by_name(): void
    {
        $filters = $this->resolve(User::factory()->create());

        $this->assertSame($this->a->id, $filters->warehouse->id);
    }

    public function test_user_with_no_warehouse_gets_null_warehouse_and_zone_without_error(): void
    {
        $filters = $this->resolve($this->operator());

        $this->assertNull($filters->warehouse);
        $this->assertNull($filters->zone);
        $this->assertNull($filters->error);
    }

    public function test_warehouse_without_zones_has_null_zone(): void
    {
        $empty = Warehouse::factory()->create(['name' => 'Gudang Kosong']);

        $filters = $this->resolve($this->operator($empty), ['gudang' => (string) $empty->id]);

        $this->assertSame($empty->id, $filters->warehouse->id);
        $this->assertNull($filters->zone);
    }

    public function test_explicit_zone_selects_its_warehouse(): void
    {
        $filters = $this->resolve(User::factory()->create(), ['zona' => (string) $this->b1->id]);

        $this->assertSame($this->b->id, $filters->warehouse->id);
        $this->assertSame($this->b1->id, $filters->zone->id);
    }

    public function test_zone_outside_selected_warehouse_is_an_error(): void
    {
        $filters = $this->resolve(User::factory()->create(), ['gudang' => (string) $this->a->id, 'zona' => (string) $this->b1->id]);

        $this->assertSame('Zona tidak termasuk gudang yang dipilih.', $filters->error);
    }

    public function test_foreign_warehouse_or_zone_is_forbidden(): void
    {
        $operator = $this->operator($this->a);

        $this->assertAbortsWith(403, fn () => $this->resolve($operator, ['gudang' => (string) $this->b->id]));
        $this->assertAbortsWith(403, fn () => $this->resolve($operator, ['zona' => (string) $this->b1->id]));
        $this->assertAbortsWith(403, fn () => $this->resolve($operator, ['gudang' => (string) $this->a->id, 'zona' => (string) $this->b1->id]));
    }

    public function test_unknown_or_malformed_ids_are_not_found(): void
    {
        $admin = User::factory()->create();

        $this->assertAbortsWith(404, fn () => $this->resolve($admin, ['gudang' => '9999']));
        $this->assertAbortsWith(404, fn () => $this->resolve($admin, ['zona' => '9999']));
        $this->assertAbortsWith(404, fn () => $this->resolve($admin, ['gudang' => 'abc']));
        $this->assertAbortsWith(404, fn () => $this->resolve($admin, ['zona' => ['1']]));
    }

    public function test_custom_period_uses_from_and_to(): void
    {
        $now = now();
        $filters = $this->resolve($this->operator($this->a), [
            'periode' => 'custom', 'dari' => $now->copy()->subDays(5)->format('Y-m-d'), 'sampai' => $now->copy()->subDays(1)->format('Y-m-d'),
        ]);

        $this->assertNull($filters->error);
        $this->assertSame('custom', $filters->period->key);
        $this->assertSame($now->copy()->subDays(5)->format('Y-m-d'), $filters->from);
        $this->assertSame($now->copy()->subDays(1)->format('Y-m-d'), $filters->to);
        $this->assertSame($now->copy()->subDays(5)->startOfDay()->getTimestamp(), $filters->period->from->getTimestamp());
    }

    public function test_invalid_custom_period_sets_error_and_falls_back_to_seven_days(): void
    {
        $filters = $this->resolve($this->operator($this->a), ['periode' => 'custom', 'dari' => '2026-11-05', 'sampai' => '2026-11-01']);

        $this->assertSame('Tanggal awal tidak boleh setelah tanggal akhir.', $filters->error);
        $this->assertSame('7d', $filters->period->key);
        $this->assertSame('custom', $filters->periodKey);
    }

    public function test_missing_custom_dates_are_an_error(): void
    {
        $filters = $this->resolve($this->operator($this->a), ['periode' => 'custom']);

        $this->assertSame('Tanggal tidak valid.', $filters->error);
    }

    public function test_unknown_period_key_falls_back_with_error(): void
    {
        $filters = $this->resolve($this->operator($this->a), ['periode' => '1y']);

        $this->assertSame('Periode tidak dikenal.', $filters->error);
        $this->assertSame('7d', $filters->period->key);
    }
}
