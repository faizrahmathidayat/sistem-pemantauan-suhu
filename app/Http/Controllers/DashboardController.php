<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use App\Services\DashboardSnapshot;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, DashboardSnapshot $snapshot): View
    {
        return view('dashboard', [
            'snapshot' => $snapshot->build($request->user()),
            'recentIncidents' => Incident::with(['zone', 'acknowledger'])->latest('started_at')->limit(5)->get(),
        ]);
    }
}
