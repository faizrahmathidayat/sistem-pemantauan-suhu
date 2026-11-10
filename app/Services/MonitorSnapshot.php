<?php

namespace App\Services;

use App\Enums\ZoneStatus;
use App\Models\Warehouse;
use App\Models\Zone;
use Carbon\CarbonInterface;

/**
 * Data halaman monitor publik (TV gudang) untuk satu gudang. Sengaja minim: tanpa produk,
 * tanpa id, tanpa pengguna; siapa pun yang memegang alamat rahasianya bisa membacanya.
 */
final class MonitorSnapshot
{
    public function __construct(private ZoneStatusEvaluator $evaluator) {}

    /**
     * @return array{warehouse: string, server_time: string, timezone: string, overall: array{level: string, headline: string, detail: string}, zones: list<array<string, mixed>>}
     */
    public function build(Warehouse $warehouse, ?CarbonInterface $now = null): array
    {
        $now ??= now();

        $rows = $warehouse->zones()->with('latestLog')->get()
            ->map(function (Zone $zone) use ($now): array {
                $log = $zone->latestLog;
                $status = $this->evaluator->forZone($zone, $now);

                return [
                    'zone' => $zone,
                    'status' => $status,
                    'public' => [
                        'name' => $zone->name,
                        'status' => $status->value,
                        'status_label' => $status->label(),
                        'temperature' => $log?->temperature,
                        'humidity' => $log?->humidity,
                        'temp_min' => $zone->temp_min,
                        'temp_max' => $zone->temp_max,
                        'humidity_min' => $zone->humidity_min,
                        'humidity_max' => $zone->humidity_max,
                        'last_seen_seconds' => $log === null ? null : max(0, $now->getTimestamp() - $log->created_at->getTimestamp()),
                    ],
                ];
            })
            ->sortBy([
                fn (array $a, array $b): int => $a['status']->severity() <=> $b['status']->severity(),
                fn (array $a, array $b): int => $a['zone']->name <=> $b['zone']->name,
            ])
            ->values();

        return [
            'warehouse' => $warehouse->name,
            'server_time' => $now->copy()->timezone(config('app.timezone'))->toIso8601String(),
            'timezone' => config('app.timezone'),
            'overall' => $this->overall($rows->all()),
            'zones' => $rows->pluck('public')->all(),
        ];
    }

    /**
     * Pita status: ditentukan zona terburuk (Bahaya > Offline > Waspada > Aman). Zona yang belum
     * pernah mengirim data tidak menurunkan status; bila semuanya begitu, pita "belum ada data".
     *
     * @param  list<array{zone: Zone, status: ZoneStatus, public: array<string, mixed>}>  $rows
     * @return array{level: string, headline: string, detail: string}
     */
    private function overall(array $rows): array
    {
        $reporting = array_filter($rows, fn (array $row): bool => $row['status'] !== ZoneStatus::TanpaData);
        if ($reporting === []) {
            return ['level' => 'tanpa_data', 'headline' => 'BELUM ADA DATA', 'detail' => ''];
        }

        $worst = min(array_map(fn (array $row): int => $row['status']->severity(), $reporting));
        $status = array_values(array_filter($reporting, fn (array $row): bool => $row['status']->severity() === $worst))[0]['status'];
        $affected = array_values(array_filter($reporting, fn (array $row): bool => $row['status'] === $status));
        $count = count($affected);

        return match ($status) {
            ZoneStatus::Bahaya => [
                'level' => 'bahaya',
                'headline' => "PERHATIAN: {$count} ZONA DI LUAR BATAS",
                'detail' => implode(' · ', array_merge(...array_map(fn (array $row): array => $this->dangerFragments($row['zone']), $affected))),
            ],
            ZoneStatus::Offline => [
                'level' => 'offline',
                'headline' => "SENSOR OFFLINE: {$count} ZONA",
                'detail' => $this->names($affected),
            ],
            ZoneStatus::Waspada => [
                'level' => 'waspada',
                'headline' => "WASPADA: {$count} ZONA MENDEKATI BATAS",
                'detail' => $this->names($affected),
            ],
            default => ['level' => 'aman', 'headline' => 'SEMUA ZONA AMAN', 'detail' => ''],
        };
    }

    /**
     * @return list<string>
     */
    private function dangerFragments(Zone $zone): array
    {
        $log = $zone->latestLog;
        $fragments = [];

        foreach ($this->evaluator->dangerReasons($zone, $log->temperature, $log->humidity) as $reason) {
            $value = $reason->isTemperature() ? $log->temperature : $log->humidity;
            $unit = $reason->unit();
            $fragments[] = sprintf(
                '%s: %s %s%s (batas %s%s)',
                $zone->name, $reason->label(), $this->number($value), $unit, $this->number($reason->limitFor($zone)), $unit,
            );
        }

        return $fragments;
    }

    /**
     * @param  list<array{zone: Zone}>  $rows
     */
    private function names(array $rows): string
    {
        return implode(' · ', array_map(fn (array $row): string => $row['zone']->name, $rows));
    }

    private function number(?float $value): string
    {
        return $value === null ? '-' : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }
}
