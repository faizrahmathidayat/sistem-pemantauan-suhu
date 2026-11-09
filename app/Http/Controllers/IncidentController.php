<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\Http\Request;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(Request $request): View
    {
        return view('incidents.index', [
            'incidents' => Incident::visibleTo($request->user())
                ->with(['zone.warehouse', 'acknowledger'])->latest('started_at')->paginate(15),
        ]);
    }
}
