<?php

namespace App\Http\Controllers;

use App\Models\SensorLog;
use App\Models\Zone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ZoneReadingsController extends Controller
{
    private const BUCKET_SECONDS = 300; // rata-rata per 5 menit

    public function __invoke(Request $request, Zone $zone): JsonResponse
    {
        $hours = (int) $request->query('hours', 24);
        $hours = $request->query('hours') === null || ! is_numeric($request->query('hours'))
            ? 24
            : max(1, min(72, $hours));

        $buckets = SensorLog::where('device_id', $zone->device_id)
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at')
            ->get(['temperature', 'humidity', 'created_at'])
            ->groupBy(fn (SensorLog $log) => intdiv($log->created_at->getTimestamp(), self::BUCKET_SECONDS));

        return response()->json([
            'labels' => $buckets->keys()->map(fn (int $b) => now()->setTimestamp($b * self::BUCKET_SECONDS)->toIso8601String())->all(),
            'temperature' => $buckets->map(fn ($logs) => round($logs->avg('temperature'), 2))->values()->all(),
            'humidity' => $buckets->map(fn ($logs) => round($logs->avg('humidity'), 2))->values()->all(),
            'temp_min' => $zone->temp_min,
            'temp_max' => $zone->temp_max,
            'humidity_min' => $zone->humidity_min,
            'humidity_max' => $zone->humidity_max,
        ]);
    }
}
