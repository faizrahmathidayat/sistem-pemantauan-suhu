<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class IncidentAcknowledgeController extends Controller
{
    public function __invoke(Request $request, Incident $incident): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()->canAccessZone($incident->zone), 403);

        // Pengklik pertama tetap tercatat; insiden yang sudah tertutup tidak diubah.
        // Update atomik agar dua klik bersamaan tidak saling menimpa.
        if ($incident->isOpen()) {
            Incident::whereKey($incident->id)->whereNull('acknowledged_by')->whereNull('ended_at')
                ->update(['acknowledged_by' => $request->user()->id, 'acknowledged_at' => now()]);
        }

        $incident->refresh()->load('acknowledger');

        return $request->expectsJson()
            ? response()->json(['acknowledged_by' => $incident->acknowledger?->name])
            : back();
    }
}
