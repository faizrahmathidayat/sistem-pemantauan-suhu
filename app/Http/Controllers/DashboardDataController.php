<?php

namespace App\Http\Controllers;

use App\Services\DashboardSnapshot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardDataController extends Controller
{
    public function __invoke(Request $request, DashboardSnapshot $snapshot): JsonResponse
    {
        return response()->json($snapshot->build($request->user()));
    }
}
