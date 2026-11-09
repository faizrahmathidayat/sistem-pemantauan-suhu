<?php

namespace Database\Seeders;

use App\Enums\ProductType;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Nilai batas di bawah hanya contoh (placeholder); sesuaikan dengan
     * kebutuhan penyimpanan produk sebenarnya. Token perangkat dibuat lewat
     * `php artisan zone:token {device_id}`.
     */
    public function run(): void
    {
        $zones = [
            ['Zona A (Telur)', ProductType::Telur, 'gudang-zona-a', 10, 20, 60, 85],
            ['Zona B (Sayur Daun)', ProductType::Sayur, 'gudang-zona-b', 15, 25, 80, 95],
            ['Zona C (Buah)', ProductType::Buah, 'gudang-zona-c', 8, 13, 85, 95],
        ];

        foreach ($zones as [$name, $type, $deviceId, $tMin, $tMax, $hMin, $hMax]) {
            Zone::updateOrCreate(['device_id' => $deviceId], [
                'name' => $name,
                'product_type' => $type,
                'temp_min' => $tMin,
                'temp_max' => $tMax,
                'humidity_min' => $hMin,
                'humidity_max' => $hMax,
            ]);
        }
    }
}
