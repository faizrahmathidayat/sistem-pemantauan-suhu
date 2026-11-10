<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\ReportService;
use App\Support\Csv;
use App\Support\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
                    'timezone' => config('app.timezone'), // label grafik memakai zona waktu aplikasi, bukan zona browser
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
            'downloadQuery' => array_filter([
                'gudang' => $filters->warehouse?->id,
                'zona' => $filters->zone?->id,
                'periode' => $filters->periodKey,
                'dari' => $filters->from,
                'sampai' => $filters->to,
            ], fn ($value) => $value !== null && $value !== ''),
            'filters' => $filters,
            'warehouses' => Warehouse::visibleTo($user)->orderBy('name')->get(),
            'zones' => $zones,
            'rows' => $rows,
            'chart' => $chart,
            'incidents' => $incidents,
            'hasData' => $rows !== null && collect($rows)->sum('readings') > 0,
        ]);
    }

    /** Ringkasan semua zona di gudang terpilih. */
    public function summaryCsv(Request $request, ReportService $reports): StreamedResponse
    {
        $filters = $this->exportFilters($request);
        $zones = $filters->warehouse->zones()->orderBy('name')->get();
        $warehouse = $filters->warehouse->name;

        $rows = array_map(fn (array $row) => [
            $warehouse,
            $row['zone']->name,
            $row['zone']->device_id,
            $row['readings'],
            $row['temperature']['min'], $row['temperature']['max'], $row['temperature']['avg'],
            $row['humidity']['min'], $row['humidity']['max'], $row['humidity']['avg'],
            $row['in_range_percent'],
            $row['incident_count'], $row['incident_minutes'], $row['offline_minutes'],
        ], $reports->summary($zones, $filters->period));

        return Csv::download(
            Csv::filename('laporan-ringkasan', $warehouse, $filters->period),
            ['Gudang', 'Zona', 'Device ID', 'Pembacaan', 'Suhu min', 'Suhu maks', 'Suhu rata-rata',
                'Kelembapan min', 'Kelembapan maks', 'Kelembapan rata-rata', '% dalam batas',
                'Jumlah insiden', 'Menit insiden', 'Menit offline'],
            $rows,
        );
    }

    /** Pembacaan mentah satu zona, dialirkan bertahap. */
    public function readingsCsv(Request $request, ReportService $reports): StreamedResponse
    {
        $filters = $this->exportFilters($request);
        abort_if($filters->zone === null, 422, 'Pilih zona untuk mengunduh data mentah.');

        $zone = $filters->zone;
        $warehouse = $filters->warehouse->name;

        $rows = $reports->readings($zone, $filters->period)->map(fn ($log) => [
            $log->created_at->format('Y-m-d H:i:s'),
            $warehouse,
            $zone->name,
            $zone->device_id,
            $log->temperature,
            $log->humidity,
        ]);

        return Csv::download(
            Csv::filename('laporan-data', $warehouse, $filters->period, $zone->name),
            ['Waktu', 'Gudang', 'Zona', 'Device ID', 'Suhu (°C)', 'Kelembapan (%)'],
            $rows,
        );
    }

    /** Ekspor tidak punya halaman untuk menampilkan galat, jadi isian yang salah ditolak (422). */
    private function exportFilters(Request $request): ReportFilters
    {
        $filters = ReportFilters::resolve($request, $request->user());

        abort_if($filters->error !== null, 422, (string) $filters->error);
        abort_if($filters->warehouse === null, 422, 'Tidak ada gudang yang bisa dilaporkan.');

        return $filters;
    }
}
