<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateZoneRequest;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ZoneSettingsController extends Controller
{
    public function index(): View
    {
        return view('zones.index', [
            'zones' => Zone::visibleTo(request()->user())->with(['warehouse', 'products'])->get()
                ->sortBy([['warehouse.name', 'asc'], ['name', 'asc']])->values(),
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
