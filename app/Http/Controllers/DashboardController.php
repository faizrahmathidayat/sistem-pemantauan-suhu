<?php

namespace App\Http\Controllers;

use App\Models\Zone;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard', [
            'zones' => Zone::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
