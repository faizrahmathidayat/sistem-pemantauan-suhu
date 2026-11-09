<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateZoneRequest;
use App\Models\Zone;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ZoneSettingsController extends Controller
{
    public function index(): View
    {
        return view('zones.index', ['zones' => Zone::orderBy('name')->get()]);
    }

    public function edit(Zone $zone): View
    {
        return view('zones.edit', ['zone' => $zone]);
    }

    public function update(UpdateZoneRequest $request, Zone $zone, AuditLogger $audit): RedirectResponse
    {
        $zone->fill($request->validated());
        $audit->saveAndLog($zone, $request->user(), 'zone.limits_updated', $zone->name);

        return redirect()->route('zones.index')->with('status', "Batas {$zone->name} disimpan.");
    }
}
