<?php

namespace App\Console\Commands;

use App\Models\SensorLog;
use App\Services\DemoData;
use App\Services\IncidentManager;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:simulate {--interval=30 : detik antar pengiriman} {--times=0 : jumlah putaran (0 = terus sampai dihentikan dengan Ctrl+C)}')]
#[Description('Kirim pembacaan palsu ke zona demo seperti sensor sungguhan (lewat jalur yang sama dengan API telemetry)')]
class DemoSimulate extends Command
{
    public function handle(DemoData $demo, IncidentManager $incidents): int
    {
        $zones = $demo->zones();
        if ($zones->isEmpty()) {
            $this->error('Belum ada data demo. Jalankan "php artisan demo:seed" lebih dulu.');

            return self::FAILURE;
        }

        $interval = max(0, (int) $this->option('interval'));
        $times = max(0, (int) $this->option('times'));

        for ($round = 1; $times === 0 || $round <= $times; $round++) {
            foreach ($zones as $zone) {
                $reading = $demo->reading($zone->device_id, now());
                $log = SensorLog::create(['device_id' => $zone->device_id, ...$reading]);
                $incidents->handleReading($zone, $log);
                $this->line(now()->format('H:i:s')." {$zone->device_id}: {$reading['temperature']} °C, {$reading['humidity']} %");
            }

            if ($times === 0 || $round < $times) {
                sleep($interval);
            }
        }

        return self::SUCCESS;
    }
}
