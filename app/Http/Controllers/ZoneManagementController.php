<?php

namespace App\Http\Controllers;

use App\Http\Requests\ZoneManagementRequest;
use App\Models\SensorLog;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ZoneManagementController extends Controller
{
    /** Kolom zona yang tidak dicatat lewat diff mentah (dicatat lewat entri khusus atau rahasia). */
    private const AUDIT_IGNORE = ['warehouse_id', 'device_token_hash'];

    public function create(Request $request, Warehouse $warehouse): View
    {
        abort_unless($request->user()->canAccessWarehouse($warehouse), 403);

        return view('zones.create', ['warehouse' => $warehouse]);
    }

    public function store(ZoneManagementRequest $request, Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        $data = $request->validated();
        $products = $request->selectedProducts();
        $zone = new Zone(collect($data)->except('products')->all() + ['warehouse_id' => $warehouse->id]);
        $token = null;

        DB::transaction(function () use ($audit, $request, $warehouse, $zone, $products, &$token): void {
            $audit->saveAndLog($zone, $request->user(), 'zone.created', $zone->name, [
                'warehouse' => ['from' => null, 'to' => $warehouse->name],
                'products' => ['from' => [], 'to' => $products],
            ], self::AUDIT_IGNORE);
            $zone->syncProducts($products);
            $token = $zone->issueToken();
        });

        return redirect()->route('zones.manage', $zone)
            ->with('status', "Zona {$zone->name} ditambahkan.")
            ->with('new_token', $token);
    }

    public function manage(Request $request, Zone $zone): View
    {
        abort_unless($request->user()->canAccessZone($zone), 403);

        return view('zones.manage', ['zone' => $zone->load(['warehouse', 'products'])]);
    }

    public function update(ZoneManagementRequest $request, Zone $zone, AuditLogger $audit): RedirectResponse
    {
        $before = $zone->products()->pluck('name')->all();
        $after = $request->selectedProducts();
        $zone->fill(['name' => $request->validated('name')]);

        $extra = $before === $after ? [] : ['products' => ['from' => $before, 'to' => $after]];

        DB::transaction(function () use ($audit, $request, $zone, $after, $extra, $before): void {
            $audit->saveAndLog($zone, $request->user(), 'zone.updated', $zone->name, $extra, self::AUDIT_IGNORE);
            if ($before !== $after) {
                $zone->syncProducts($after);
            }
        });

        return redirect()->route('zones.manage', $zone)->with('status', "Zona {$zone->name} disimpan.");
    }

    public function token(Request $request, Zone $zone, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->canAccessZone($zone), 403);

        $token = null;
        DB::transaction(function () use ($audit, $request, $zone, &$token): void {
            $token = $zone->issueToken();
            $audit->record($request->user(), 'zone.token_rotated', $zone, $zone->name, [
                'token' => ['from' => null, 'to' => 'diperbarui'],
            ]);
        });

        return redirect()->route('zones.manage', $zone)
            ->with('status', 'Token baru dibuat. Token lama tidak berlaku lagi.')
            ->with('new_token', $token);
    }

    public function destroy(Request $request, Zone $zone, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->canAccessZone($zone), 403);

        $name = $zone->name;
        $warehouseId = $zone->warehouse_id;
        DB::transaction(function () use ($audit, $request, $zone): void {
            $audit->record($request->user(), 'zone.deleted', $zone, $zone->name, [
                'name' => ['from' => $zone->name, 'to' => null],
                'device_id' => ['from' => $zone->device_id, 'to' => null],
                'products' => ['from' => $zone->products()->pluck('name')->all(), 'to' => []],
            ]);
            // Pembacaan sensor terikat lewat device_id (tanpa FK); insiden dan produk ikut lewat FK cascade.
            SensorLog::where('device_id', $zone->device_id)->delete();
            $zone->delete();
        });

        return redirect($this->afterDeleteUrl($request, $warehouseId))->with('status', "Zona {$name} dihapus beserta seluruh datanya.");
    }

    /** Tujuan setelah zona dihapus: halaman yang boleh dibuka user (tidak semua pemegang zone.manage boleh mengelola gudang). */
    private function afterDeleteUrl(Request $request, int $warehouseId): string
    {
        $user = $request->user();

        return match (true) {
            $user->can('warehouse.manage') => route('warehouses.edit', $warehouseId),
            $user->can('zone.view') => route('zones.index'),
            default => route('home'),
        };
    }
}
