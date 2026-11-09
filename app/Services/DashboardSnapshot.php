<?php

namespace App\Services;

use App\Console\Commands\CheckOfflineZones;
use App\Enums\IncidentReason;
use App\Enums\Permission;
use App\Enums\ZoneStatus;
use App\Models\Incident;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Kontrak JSON dashboard: dipakai halaman (render awal) dan endpoint polling.
 */
final class DashboardSnapshot
{
    private const SCHEDULER_STALE_AFTER_MINUTES = 5;

    public function __construct(private ZoneStatusEvaluator $evaluator) {}

    /**
     * @return array{zones: list<array<string, mixed>>, warehouses: list<array<string, mixed>>, banners: list<array<string, mixed>>, unread_notifications: int, can_acknowledge: bool, has_access: bool, scheduler_stale: bool}
     */
    public function build(User $user): array
    {
        $now = now();

        $rows = Zone::visibleTo($user)->with(['latestLog', 'products', 'warehouse'])->orderBy('name')->get()
            ->map(function (Zone $zone) use ($now): array {
                $log = $zone->latestLog;
                $status = $this->evaluator->forZone($zone, $now);

                return [
                    'id' => $zone->id,
                    'name' => $zone->name,
                    'warehouse_id' => $zone->warehouse_id,
                    'warehouse_name' => $zone->warehouse->name,
                    'products' => $zone->products->pluck('name')->all(),
                    'status' => $status->value,
                    'temperature' => $log?->temperature,
                    'humidity' => $log?->humidity,
                    'temp_min' => $zone->temp_min,
                    'temp_max' => $zone->temp_max,
                    'humidity_min' => $zone->humidity_min,
                    'humidity_max' => $zone->humidity_max,
                    'trend' => $this->trend($zone, $log?->temperature),
                    'humidity_trend' => $this->humidityTrend($zone, $log?->humidity),
                    'last_seen_seconds' => $log === null ? null : max(0, $now->getTimestamp() - $log->created_at->getTimestamp()),
                    '_severity' => $status->severity(),
                ];
            })
            ->sortBy([['_severity', 'asc'], ['name', 'asc']])
            ->values();

        $incidents = Incident::open()->visibleTo($user)->with(['zone.warehouse', 'zone.latestLog', 'acknowledger'])->orderBy('started_at')->get();

        $banners = $incidents->map(fn (Incident $incident): array => [
            'incident_id' => $incident->id,
            'zone_name' => $incident->zone->name,
            'warehouse_name' => $incident->zone->warehouse->name,
            'location' => $incident->zone->warehouse->name.' › '.$incident->zone->name,
            'kind' => $incident->reason === IncidentReason::SensorOffline ? 'offline' : 'kritis',
            'message' => $this->message($incident),
            'started_at' => $incident->started_at->toIso8601String(),
            'acknowledged_by' => $incident->acknowledger?->name,
        ])->all();

        $warehouses = Warehouse::visibleTo($user)->orderBy('name')->get()
            ->map(function (Warehouse $warehouse) use ($rows, $incidents): array {
                $zoneRows = $rows->where('warehouse_id', $warehouse->id);

                return [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                    // Status terburuk (severity terkecil) di antara zona gudang ini.
                    'status' => $zoneRows->isEmpty() ? ZoneStatus::TanpaData->value : $zoneRows->sortBy('_severity')->first()['status'],
                    'open_incidents' => $incidents->filter(fn (Incident $i) => $i->zone->warehouse_id === $warehouse->id)->count(),
                ];
            })->values()->all();

        return [
            'zones' => $rows->map(fn (array $row) => array_diff_key($row, ['_severity' => true]))->all(),
            'warehouses' => $warehouses,
            'banners' => $banners,
            'unread_notifications' => $user->unreadNotifications()->count(),
            'can_acknowledge' => $user->hasPermission(Permission::IncidentAcknowledge),
            'has_access' => $user->isAdministrator() || $user->warehouses()->exists(),
            'scheduler_stale' => $this->schedulerStale($now),
        ];
    }

    private function trend(Zone $zone, ?float $temperature): ?string
    {
        return match (true) {
            $temperature === null => null,
            $temperature > $zone->temp_max => 'naik',
            $temperature < $zone->temp_min => 'turun',
            default => null,
        };
    }

    private function humidityTrend(Zone $zone, ?float $humidity): ?string
    {
        return match (true) {
            $humidity === null => null,
            $zone->humidity_max !== null && $humidity > $zone->humidity_max => 'naik',
            $zone->humidity_min !== null && $humidity < $zone->humidity_min => 'turun',
            default => null,
        };
    }

    private function message(Incident $incident): string
    {
        $zone = $incident->zone;
        $reason = $incident->reason;

        if ($reason === IncidentReason::SensorOffline) {
            $since = $zone->latestLog?->created_at?->format('H:i');

            return "Sensor {$zone->name} tidak mengirim data".($since ? " sejak {$since}" : '').'.';
        }

        return sprintf(
            '%s di %s: %s%s (batas %s%s).',
            $reason->label(), $zone->name,
            $this->number($incident->peak_value), $reason->unit(),
            $this->number($reason->limitFor($zone)), $reason->unit(),
        );
    }

    private function number(?float $value): string
    {
        return $value === null ? '-' : rtrim(rtrim(number_format($value, 1, '.', ''), '0'), '.');
    }

    private function schedulerStale(Carbon $now): bool
    {
        $last = Cache::get(CheckOfflineZones::LAST_CHECK_CACHE_KEY);

        return $last === null
            || Carbon::parse($last)->lt($now->copy()->subMinutes(self::SCHEDULER_STALE_AFTER_MINUTES));
    }
}
