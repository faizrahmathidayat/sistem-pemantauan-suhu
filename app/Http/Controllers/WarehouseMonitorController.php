<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** Admin membuat, mengganti, dan mematikan alamat rahasia halaman monitor publik sebuah gudang. */
class WarehouseMonitorController extends Controller
{
    public function store(Request $request, Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->canAccessWarehouse($warehouse), 403);

        $had = $warehouse->hasMonitorToken();
        DB::transaction(function () use ($audit, $request, $warehouse, $had): void {
            $warehouse->issueMonitorToken();
            $audit->record($request->user(), 'warehouse.monitor_issued', $warehouse, $warehouse->name, [
                'monitor' => ['from' => $had ? 'aktif' : null, 'to' => 'aktif (alamat baru)'],
            ]);
        });

        return redirect()->route('warehouses.edit', $warehouse)->with('status', "Alamat monitor {$warehouse->name} dibuat.");
    }

    public function destroy(Request $request, Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->canAccessWarehouse($warehouse), 403);

        if (! $warehouse->hasMonitorToken()) {
            return redirect()->route('warehouses.edit', $warehouse)->with('status', 'Gudang ini belum punya alamat monitor.');
        }

        DB::transaction(function () use ($audit, $request, $warehouse): void {
            $warehouse->revokeMonitorToken();
            $audit->record($request->user(), 'warehouse.monitor_revoked', $warehouse, $warehouse->name, [
                'monitor' => ['from' => 'aktif', 'to' => 'dimatikan'],
            ]);
        });

        return redirect()->route('warehouses.edit', $warehouse)->with('status', "Alamat monitor {$warehouse->name} dimatikan.");
    }
}
