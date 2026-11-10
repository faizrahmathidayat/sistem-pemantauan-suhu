<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\MonitorSnapshot;
use Illuminate\View\View;

class MonitorController extends Controller
{
    public function __invoke(string $token, MonitorSnapshot $snapshot): View
    {
        $warehouse = Warehouse::findByMonitorToken($token) ?? abort(404);

        return view('monitor.show', [
            'snapshot' => $snapshot->build($warehouse),
            'dataUrl' => route('monitor.data', $token),
        ]);
    }
}
