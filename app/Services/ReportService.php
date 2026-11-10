<?php

namespace App\Services;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\Zone;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Perhitungan halaman Laporan. Agregasi dikerjakan database (data bisa ratusan ribu baris per
 * zona); PHP hanya menyusun hasilnya.
 */
final class ReportService
{
    /**
     * Ringkasan per zona untuk satu periode, dalam urutan $zones.
     *
     * @param  Collection<int, Zone>  $zones
     * @return list<array{zone: Zone, readings: int, temperature: array{min: ?float, max: ?float, avg: ?float}, humidity: array{min: ?float, max: ?float, avg: ?float}, in_range_percent: ?float, incident_count: int, incident_minutes: int, offline_minutes: int}>
     */
    public function summary(Collection $zones, ReportPeriod $period): array
    {
        return $zones->map(function (Zone $zone) use ($period): array {
            $stats = $this->readingStats($zone, $period);
            $incidents = $this->incidentStats($zone, $period);
            $readings = (int) $stats->readings;

            return [
                'zone' => $zone,
                'readings' => $readings,
                'temperature' => $this->triple($stats->t_min, $stats->t_max, $stats->t_avg),
                'humidity' => $this->triple($stats->h_min, $stats->h_max, $stats->h_avg),
                'in_range_percent' => $readings > 0 ? round((int) $stats->in_range / $readings * 100, 1) : null,
                ...$incidents,
            ];
        })->values()->all();
    }

    /**
     * Deret waktu untuk grafik: rata-rata, minimum, dan maksimum per bucket (hanya bucket yang punya data).
     *
     * @return array{bucket_seconds: int, labels: list<string>, temperature: array{avg: list<float>, min: list<float>, max: list<float>}, humidity: array{avg: list<float>, min: list<float>, max: list<float>}}
     */
    public function series(Zone $zone, ReportPeriod $period): array
    {
        $size = $period->bucketSeconds();
        $expression = $this->bucketExpression($size);

        $rows = $this->readingsQuery($zone, $period)
            ->toBase()
            ->selectRaw("{$expression} AS bucket, AVG(temperature) AS t_avg, MIN(temperature) AS t_min, MAX(temperature) AS t_max, AVG(humidity) AS h_avg, MIN(humidity) AS h_min, MAX(humidity) AS h_max")
            ->groupBy('bucket')
            ->orderBy('bucket')
            ->get();

        $origin = CarbonImmutable::parse('2000-01-01 00:00:00', config('app.timezone'));
        $round = fn (string $column): array => $rows->map(fn ($row) => round((float) $row->{$column}, 2))->all();

        return [
            'bucket_seconds' => $size,
            'labels' => $rows->map(fn ($row) => $origin->addSeconds(((int) $row->bucket) * $size)->toIso8601String())->all(),
            'temperature' => ['avg' => $round('t_avg'), 'min' => $round('t_min'), 'max' => $round('t_max')],
            'humidity' => ['avg' => $round('h_avg'), 'min' => $round('h_min'), 'max' => $round('h_max')],
        ];
    }

    /**
     * Insiden zona yang beririsan dengan periode, terbaru dulu.
     *
     * @return Collection<int, Incident>
     */
    public function incidents(Zone $zone, ReportPeriod $period, int $limit = 100): Collection
    {
        return $this->overlappingIncidents($zone, $period)
            ->orderByDesc('started_at')->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Pembacaan mentah zona pada periode, dialirkan bertahap menurut id (hampir sama dengan urutan waktu).
     *
     * @return LazyCollection<int, SensorLog>
     */
    public function readings(Zone $zone, ReportPeriod $period): LazyCollection
    {
        return $this->readingsQuery($zone, $period)->lazyById(5000);
    }

    /** @return Builder<SensorLog> */
    private function readingsQuery(Zone $zone, ReportPeriod $period): Builder
    {
        return SensorLog::query()
            ->where('device_id', $zone->device_id)
            ->whereBetween('created_at', [$period->from, $period->to]);
    }

    private function readingStats(Zone $zone, ReportPeriod $period): object
    {
        $hasHumidity = $zone->humidity_min !== null && $zone->humidity_max !== null;
        $inRange = 'SUM(CASE WHEN temperature BETWEEN ? AND ?'.($hasHumidity ? ' AND humidity BETWEEN ? AND ?' : '').' THEN 1 ELSE 0 END)';
        $bindings = $hasHumidity
            ? [$zone->temp_min, $zone->temp_max, $zone->humidity_min, $zone->humidity_max]
            : [$zone->temp_min, $zone->temp_max];

        return $this->readingsQuery($zone, $period)
            ->toBase()
            ->selectRaw(
                "COUNT(*) AS readings, MIN(temperature) AS t_min, MAX(temperature) AS t_max, AVG(temperature) AS t_avg, MIN(humidity) AS h_min, MAX(humidity) AS h_max, AVG(humidity) AS h_avg, {$inRange} AS in_range",
                $bindings,
            )
            ->first();
    }

    /** @return array{incident_count: int, incident_minutes: int, offline_minutes: int} */
    private function incidentStats(Zone $zone, ReportPeriod $period): array
    {
        $from = $period->from->getTimestamp();
        $to = $period->to->getTimestamp();
        $incidents = $this->overlappingIncidents($zone, $period)->get();

        $intervals = fn (callable $filter): array => $incidents
            ->filter($filter)
            ->map(fn (Incident $incident) => [
                max($incident->started_at->getTimestamp(), $from),
                min($incident->ended_at?->getTimestamp() ?? $to, $to),
            ])
            ->all();

        return [
            // Sensor offline punya kolom sendiri (lama offline), jadi tidak ikut dihitung sebagai insiden.
            'incident_count' => $incidents->filter(fn (Incident $i) => $i->reason !== IncidentReason::SensorOffline)->count(),
            'incident_minutes' => $this->unionMinutes($intervals(fn (Incident $i) => $i->reason !== IncidentReason::SensorOffline)),
            'offline_minutes' => $this->unionMinutes($intervals(fn (Incident $i) => $i->reason === IncidentReason::SensorOffline)),
        ];
    }

    /** @return Builder<Incident> */
    private function overlappingIncidents(Zone $zone, ReportPeriod $period): Builder
    {
        return Incident::query()
            ->where('zone_id', $zone->id)
            ->where('started_at', '<=', $period->to)
            ->where(fn (Builder $query) => $query->whereNull('ended_at')->orWhere('ended_at', '>=', $period->from));
    }

    /**
     * Total menit gabungan beberapa interval (detik): interval yang tumpang tindih dihitung sekali.
     *
     * @param  list<array{0: int, 1: int}>  $intervals
     */
    private function unionMinutes(array $intervals): int
    {
        usort($intervals, fn (array $a, array $b) => $a[0] <=> $b[0]);

        $seconds = 0;
        $currentStart = null;
        $currentEnd = null;
        foreach ($intervals as [$start, $end]) {
            if ($end <= $start) {
                continue;
            }
            if ($currentEnd === null || $start > $currentEnd) {
                $seconds += $currentEnd === null ? 0 : $currentEnd - $currentStart;
                [$currentStart, $currentEnd] = [$start, $end];
            } else {
                $currentEnd = max($currentEnd, $end);
            }
        }
        if ($currentEnd !== null) {
            $seconds += $currentEnd - $currentStart;
        }

        return intdiv($seconds, 60);
    }

    /** @return array{min: ?float, max: ?float, avg: ?float} */
    private function triple(mixed $min, mixed $max, mixed $avg): array
    {
        $number = fn (mixed $value): ?float => $value === null ? null : round((float) $value, 2);

        return ['min' => $number($min), 'max' => $number($max), 'avg' => $number($avg)];
    }

    /**
     * Nomor bucket tiap baris. Selisih detik dari titik tetap (bukan UNIX_TIMESTAMP) agar tidak
     * bergantung zona waktu sesi database; ukuran bucket ditulis langsung karena MySQL menolak
     * ekspresi ber-placeholder yang berbeda antara SELECT dan GROUP BY.
     */
    private function bucketExpression(int $seconds): string
    {
        return DB::getDriverName() === 'sqlite'
            ? "((CAST(strftime('%s', created_at) AS INTEGER) - 946684800) / {$seconds})"
            : "(TIMESTAMPDIFF(SECOND, '2000-01-01 00:00:00', created_at) DIV {$seconds})";
    }
}
