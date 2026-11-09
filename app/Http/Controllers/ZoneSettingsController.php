<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateZoneRequest;
use App\Models\Warehouse;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneSettingsController extends Controller
{
    public function index(): View
    {
        // Semua gudang yang boleh dilihat (juga yang belum punya zona, agar zona pertama bisa ditambahkan).
        return view('zones.index', [
            'warehouses' => Warehouse::visibleTo(request()->user())
                ->with(['zones' => fn ($zones) => $zones->with('products')->orderBy('name')])
                ->orderBy('name')->get(),
        ]);
    }

    public function edit(Request $request, Zone $zone): View
    {
        abort_unless($request->user()->canAccessZone($zone), 403);

        return view('zones.edit', ['zone' => $zone->load(['warehouse', 'products'])]);
    }

    public function update(UpdateZoneRequest $request, Zone $zone, AuditLogger $audit): RedirectResponse
    {
        $zone->fill($request->validated());
        $audit->saveAndLog($zone, $request->user(), 'zone.limits_updated', $zone->name);

        return redirect()->route('zones.index')->with('status', "Batas {$zone->name} disimpan.");
    }
}
