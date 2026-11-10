<?php

namespace App\Http\Controllers;

use App\Http\Requests\WarehouseRequest;
use App\Models\Warehouse;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request): View
    {
        return view('warehouses.index', [
            'warehouses' => Warehouse::visibleTo($request->user())->withCount(['zones', 'users'])->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        return view('warehouses.create', ['warehouse' => new Warehouse]);
    }

    public function store(WarehouseRequest $request, AuditLogger $audit): RedirectResponse
    {
        $warehouse = new Warehouse($request->validated());

        DB::transaction(function () use ($audit, $request, $warehouse): void {
            $audit->saveAndLog($warehouse, $request->user(), 'warehouse.created', $warehouse->name);
            // Pembuat non-Administrator otomatis mendapat akses ke gudang barunya; kalau tidak,
            // gudang itu langsung tak terlihat olehnya.
            if (! $request->user()->isAdministrator()) {
                $request->user()->warehouses()->attach($warehouse->id);
            }
        });

        return redirect()->route('warehouses.index')->with('status', "Gudang {$warehouse->name} ditambahkan.");
    }

    public function edit(Request $request, Warehouse $warehouse): Response
    {
        abort_unless($request->user()->canAccessWarehouse($warehouse), 403);

        $response = response()->view('warehouses.edit', ['warehouse' => $warehouse->load(['zones' => fn ($zones) => $zones->orderBy('name')->with('products')])]);

        // no-store: alamat monitor (rahasia) di halaman ini tidak boleh tersimpan di cache browser/proksi.
        if ($warehouse->monitorUrl() !== null) {
            $response->header('Cache-Control', 'no-store, private');
        }

        return $response;
    }

    public function update(WarehouseRequest $request, Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        $warehouse->fill($request->validated());
        $audit->saveAndLog($warehouse, $request->user(), 'warehouse.updated', $warehouse->name);

        return redirect()->route('warehouses.index')->with('status', "Gudang {$warehouse->name} disimpan.");
    }

    public function destroy(Request $request, Warehouse $warehouse, AuditLogger $audit): RedirectResponse
    {
        abort_unless($request->user()->canAccessWarehouse($warehouse), 403);

        $zones = $warehouse->zones()->count();
        if ($zones > 0) {
            return back()->with('error', "Gudang {$warehouse->name} masih punya {$zones} zona. Hapus zonanya dulu.");
        }

        DB::transaction(function () use ($audit, $request, $warehouse): void {
            $audit->record($request->user(), 'warehouse.deleted', $warehouse, $warehouse->name, [
                'name' => ['from' => $warehouse->name, 'to' => null],
            ]);
            $warehouse->delete();
        });

        return redirect()->route('warehouses.index')->with('status', "Gudang {$warehouse->name} dihapus.");
    }
}
