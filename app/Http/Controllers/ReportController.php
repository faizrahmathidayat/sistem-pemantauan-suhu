<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\ReportService;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function index(Request $request, ReportService $reports): View
    {
        $user = $request->user();
        $filters = ReportFilters::resolve($request, $user);

        $zones = $filters->warehouse
            ? $filters->warehouse->zones()->orderBy('name')->get()
            : collect();

        $rows = null;
        $chart = null;
        $incidents = collect();

        // Isian salah (mis. tanggal tidak valid): jelaskan alasannya, jangan tampilkan angka dari periode pengganti.
        if ($filters->error === null && $filters->warehouse !== null) {
            $rows = $reports->summary($zones, $filters->period);

            if ($filters->zone !== null) {
                $zone = $filters->zone;
                $chart = [
                    'zone' => $zone->name,
                    'warehouse' => $filters->warehouse->name,
                    'temp_min' => $zone->temp_min,
                    'temp_max' => $zone->temp_max,
                    'humidity_min' => $zone->humidity_min,
                    'humidity_max' => $zone->humidity_max,
                    ...$reports->series($zone, $filters->period),
                ];
                $incidents = $reports->incidents($zone, $filters->period);
            }
        }

        return view('reports.index', [
            'filters' => $filters,
            'warehouses' => Warehouse::visibleTo($user)->orderBy('name')->get(),
            'zones' => $zones,
            'rows' => $rows,
            'chart' => $chart,
            'incidents' => $incidents,
            'hasData' => $rows !== null && collect($rows)->sum('readings') > 0,
        ]);
    }
}
