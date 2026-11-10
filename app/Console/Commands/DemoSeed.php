<?php

namespace App\Console\Commands;

use App\Models\SensorLog;
use App\Services\DemoData;
use App\Support\ReportPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('demo:seed {--days=30 : jumlah hari riwayat (1-90)}')]
#[Description('Buat data demo (Gudang Demo, 3 zona, riwayat pembacaan, insiden) untuk melihat dashboard dan Laporan')]
class DemoSeed extends Command
{
    public function handle(DemoData $demo): int
    {
        $days = (string) $this->option('days');
        if (! ctype_digit($days) || (int) $days < 1 || (int) $days > ReportPeriod::MAX_DAYS) {
            $this->error('--days harus angka 1 sampai '.ReportPeriod::MAX_DAYS.' (data hanya disimpan '.ReportPeriod::MAX_DAYS.' hari).');

            return self::FAILURE;
        }

        try {
            $demo->seed((int) $days);
        } catch (RuntimeException) {
            $this->error('Data demo sudah ada. Jalankan "php artisan demo:clear" lebih dulu bila ingin membuatnya ulang.');

            return self::FAILURE;
        }

        $readings = SensorLog::where('device_id', 'like', 'demo-%')->count();
        $this->info("Data demo dibuat: {$demo->zones()->count()} zona, {$readings} pembacaan ({$days} hari).");
        $this->line('Administrator langsung melihat "'.DemoData::WAREHOUSE.'"; user lain perlu di-assign ke gudang itu lewat menu User.');
        $this->line('Tanpa pengiriman data baru, zona demo ditandai offline setelah 2 menit. Jalankan "php artisan demo:simulate" agar data terus masuk.');
        $this->line('Hapus semuanya dengan "php artisan demo:clear".');

        return self::SUCCESS;
    }
}
