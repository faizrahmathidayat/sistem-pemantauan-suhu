<?php

namespace App\Console\Commands;

use App\Services\DemoData;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('demo:clear')]
#[Description('Hapus data demo (Gudang Demo beserta zona, pembacaan, insiden, dan notifikasinya); data asli tidak disentuh')]
class DemoClear extends Command
{
    public function handle(DemoData $demo): int
    {
        $result = $demo->clear();

        if ($result === null) {
            $this->info('Tidak ada data demo.');

            return self::SUCCESS;
        }

        $this->info("Data demo dihapus: {$result['zones']} zona, {$result['readings']} pembacaan, {$result['notifications']} notifikasi.");

        return self::SUCCESS;
    }
}
