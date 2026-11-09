<?php

namespace App\Services;

use App\Enums\IncidentReason;
use App\Enums\Permission;
use App\Models\Incident;
use App\Models\SensorLog;
use App\Models\User;
use App\Models\Zone;
use App\Notifications\IncidentOpened;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Membuka/menutup insiden dari pembacaan sensor. Tidak ada penghitung tersimpan:
 * "berturut-turut" diturunkan dari pembacaan sebelumnya milik perangkat yang sama.
 */
final class IncidentManager
{
    public function __construct(private ZoneStatusEvaluator $evaluator) {}

    public function handleReading(Zone $zone, SensorLog $log): void
    {
        /** @var list<array{Incident, ?SensorLog}> $toNotify */
        $toNotify = DB::transaction(function () use ($zone, $log): array {
            Zone::whereKey($zone->id)->lockForUpdate()->first(); // serialkan per zona

            $opened = [];
            $this->closeOfflineIncidents($zone, $log);

            $current = $this->evaluator->dangerReasons($zone, $log->temperature, $log->humidity);
            $previous = SensorLog::where('device_id', $log->device_id)
                ->where('id', '<', $log->id)->orderByDesc('id')->first();
            $consecutive = $previous !== null
                && $log->created_at->getTimestamp() - $previous->created_at->getTimestamp()
                    <= ZoneStatusEvaluator::OFFLINE_AFTER_SECONDS;
            $previousReasons = $consecutive
                ? $this->evaluator->dangerReasons($zone, $previous->temperature, $previous->humidity)
                : [];

            foreach ($current as $reason) {
                $open = $zone->openIncidents()->where('reason', $reason->value)->first();
                if ($open !== null) {
                    $this->updatePeak($open, $reason, $log);
                } elseif (in_array($reason, $previousReasons, true)) {
                    $incident = $zone->incidents()->create([
                        'reason' => $reason,
                        'started_at' => $log->created_at,
                        'peak_value' => $this->worse($reason, $this->metric($reason, $previous), $this->metric($reason, $log)),
                    ]);
                    $opened[] = [$incident, $log];
                }
            }

            if ($consecutive) {
                $zone->openIncidents()->where('reason', '!=', IncidentReason::SensorOffline->value)->get()
                    ->each(function (Incident $incident) use ($current, $previousReasons, $log): void {
                        if (! in_array($incident->reason, $current, true)
                            && ! in_array($incident->reason, $previousReasons, true)) {
                            $incident->update(['ended_at' => $log->created_at]);
                        }
                    });
            }

            return $opened;
        });

        foreach ($toNotify as [$incident, $reading]) {
            $this->notify($incident, $reading);
        }
    }

    /**
     * Buka insiden sensor_offline untuk zona yang diam. Pemanggil (zones:check-offline)
     * yang menentukan zona itu memang sudah offline.
     */
    public function openOffline(Zone $zone): ?Incident
    {
        $log = $zone->latestLog;
        if ($log === null) {
            return null;
        }

        $incident = DB::transaction(function () use ($zone): ?Incident {
            Zone::whereKey($zone->id)->lockForUpdate()->first();

            if ($zone->openIncidents()->where('reason', IncidentReason::SensorOffline->value)->exists()) {
                return null;
            }

            return $zone->incidents()->create([
                'reason' => IncidentReason::SensorOffline,
                'started_at' => now(),
                'peak_value' => null,
            ]);
        });

        if ($incident !== null) {
            $this->notify($incident, $log);
        }

        return $incident;
    }

    private function closeOfflineIncidents(Zone $zone, SensorLog $log): void
    {
        $zone->openIncidents()->where('reason', IncidentReason::SensorOffline->value)
            ->update(['ended_at' => $log->created_at]);
    }

    private function updatePeak(Incident $incident, IncidentReason $reason, SensorLog $log): void
    {
        $peak = $this->worse($reason, $incident->peak_value, $this->metric($reason, $log));
        if ($peak !== $incident->peak_value) {
            $incident->update(['peak_value' => $peak]);
        }
    }

    private function metric(IncidentReason $reason, SensorLog $log): float
    {
        return $reason->isTemperature() ? $log->temperature : $log->humidity;
    }

    private function worse(IncidentReason $reason, ?float $a, float $b): float
    {
        if ($a === null) {
            return $b;
        }

        return $reason->isHigh() ? max($a, $b) : min($a, $b);
    }

    private function notify(Incident $incident, ?SensorLog $log): void
    {
        // Hanya user yang rolenya boleh menerima notifikasi (Administrator selalu).
        Notification::send(
            User::withPermission(Permission::NotificationView)->get(),
            new IncidentOpened($incident->loadMissing('zone'), $log),
        );
    }
}
