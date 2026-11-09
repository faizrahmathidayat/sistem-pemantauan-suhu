<?php

namespace App\Http\Controllers;

use App\Models\Incident;
use Illuminate\View\View;

class IncidentController extends Controller
{
    public function index(): View
    {
        return view('incidents.index', [
            'incidents' => Incident::with(['zone', 'acknowledger'])->latest('started_at')->paginate(15),
        ]);
    }
}
