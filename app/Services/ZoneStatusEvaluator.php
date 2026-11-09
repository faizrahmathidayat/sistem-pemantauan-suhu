<?php

namespace App\Services;

use App\Enums\IncidentReason;
use App\Enums\ZoneStatus;
use App\Models\Zone;
use Carbon\CarbonInterface;

final class ZoneStatusEvaluator
{
    public const OFFLINE_AFTER_SECONDS = 120;

    /**
     * Status dari satu pembacaan: Aman, Waspada, atau Bahaya (tidak pernah Offline).
     */
    public function evaluate(Zone $zone, float $temperature, float $humidity): ZoneStatus
    {
        if ($this->dangerReasons($zone, $temperature, $humidity) !== []) {
            return ZoneStatus::Bahaya;
        }

        $nearTemperatureLimit = $this->nearLimit($temperature, $zone->temp_min, $zone->temp_max, $zone->warning_margin);
        $nearHumidityLimit = $zone->humidity_min !== null && $zone->humidity_max !== null
            && $this->nearLimit($humidity, $zone->humidity_min, $zone->humidity_max, $zone->humidity_warning_margin);

        return $nearTemperatureLimit || $nearHumidityLimit ? ZoneStatus::Waspada : ZoneStatus::Aman;
    }

    /**
     * Alasan bahaya (di luar rentang min..max). Nilai tepat di batas bukan bahaya.
     *
     * @return list<IncidentReason>
     */
    public function dangerReasons(Zone $zone, float $temperature, float $humidity): array
    {
        $reasons = [];

        if ($this->round($temperature) > $this->round($zone->temp_max)) {
            $reasons[] = IncidentReason::SuhuTinggi;
        }
        if ($this->round($temperature) < $this->round($zone->temp_min)) {
            $reasons[] = IncidentReason::SuhuRendah;
        }
        if ($zone->humidity_max !== null && $this->round($humidity) > $this->round($zone->humidity_max)) {
            $reasons[] = IncidentReason::LembapTinggi;
        }
        if ($zone->humidity_min !== null && $this->round($humidity) < $this->round($zone->humidity_min)) {
            $reasons[] = IncidentReason::LembapRendah;
        }

        return $reasons;
    }

    /**
     * Status zona saat ini berdasarkan pembacaan terakhir.
     */
    public function forZone(Zone $zone, ?CarbonInterface $now = null): ZoneStatus
    {
        $log = $zone->latestLog;
        if ($log === null) {
            return ZoneStatus::TanpaData;
        }

        $now ??= now();
        if ($now->getTimestamp() - $log->created_at->getTimestamp() > self::OFFLINE_AFTER_SECONDS) {
            return ZoneStatus::Offline;
        }

        return $this->evaluate($zone, $log->temperature, $log->humidity);
    }

    private function nearLimit(float $value, float $min, float $max, float $margin): bool
    {
        return $this->round($value) >= $this->round($max - $margin)
            || $this->round($value) <= $this->round($min + $margin);
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
