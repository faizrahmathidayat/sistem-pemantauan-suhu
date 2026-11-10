<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\MonitorSnapshot;
use Illuminate\Http\JsonResponse;

class MonitorDataController extends Controller
{
    public function __invoke(string $token, MonitorSnapshot $snapshot): JsonResponse
    {
        $warehouse = Warehouse::findByMonitorToken($token) ?? abort(404);

        return response()->json($snapshot->build($warehouse));
    }
}
