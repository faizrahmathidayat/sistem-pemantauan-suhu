<?php

namespace App\Console\Commands;

use App\Enums\ZoneStatus;
use App\Models\Zone;
use App\Services\IncidentManager;
use App\Services\ZoneStatusEvaluator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('zones:check-offline')]
#[Description('Buka insiden sensor offline untuk zona yang berhenti mengirim data lebih dari 2 menit')]
class CheckOfflineZones extends Command
{
    public const LAST_CHECK_CACHE_KEY = 'zones:last-offline-check';

    public function handle(ZoneStatusEvaluator $evaluator, IncidentManager $incidents): int
    {
        $opened = 0;

        Zone::with('latestLog')->get()->each(function (Zone $zone) use ($evaluator, $incidents, &$opened): void {
            if ($evaluator->forZone($zone) === ZoneStatus::Offline && $incidents->openOffline($zone) !== null) {
                $opened++;
            }
        });

        Cache::forever(self::LAST_CHECK_CACHE_KEY, now()->toIso8601String());
        $this->info("Pemeriksaan selesai; insiden offline baru: {$opened}.");

        return self::SUCCESS;
    }
}
