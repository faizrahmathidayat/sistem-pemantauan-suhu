<?php

namespace App\Console\Commands;

use App\Models\Zone;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zone:token {device_id : device_id zona yang akan diberi token}')]
#[Description('Buat (atau putar ulang) token perangkat untuk sebuah zona; token hanya ditampilkan sekali')]
class ZoneToken extends Command
{
    public function handle(): int
    {
        $zone = Zone::where('device_id', $this->argument('device_id'))->first();
        if ($zone === null) {
            $this->error('Zona dengan device_id "'.$this->argument('device_id').'" tidak ditemukan.');

            return self::FAILURE;
        }

        $token = $zone->issueToken();

        $this->info('Token untuk '.$zone->name.' (simpan sekarang, tidak akan ditampilkan lagi):');
        $this->line('Token: '.$token);

        return self::SUCCESS;
    }
}
