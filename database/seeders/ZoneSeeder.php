<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Database\Seeder;

class ZoneSeeder extends Seeder
{
    /**
     * Contoh gudang, zona, dan produk. Nilai batas di bawah hanya contoh (placeholder);
     * sesuaikan dengan kebutuhan penyimpanan sebenarnya lewat menu Batas Zona. Token
     * perangkat dibuat lewat menu Gudang atau `php artisan zone:token {device_id}`.
     */
    public function run(): void
    {
        $warehouse = Warehouse::firstOrCreate(['name' => 'Gudang Cikokol (Telur)']);

        $zones = [
            ['Zona Inbound', 'cikokol-inbound', ['Telur Ayam Grade A', 'Telur Bebek']],
            ['Zona FG', 'cikokol-fg', ['Telur Ayam Grade A', 'Telur Ayam Grade B', 'Telur Bebek']],
            ['Zona Loading', 'cikokol-loading', ['Telur Ayam Grade A']],
        ];

        foreach ($zones as [$name, $deviceId, $products]) {
            $zone = Zone::updateOrCreate(['device_id' => $deviceId], [
                'warehouse_id' => $warehouse->id,
                'name' => $name,
                'temp_min' => 10,
                'temp_max' => 20,
                'humidity_min' => 60,
                'humidity_max' => 85,
            ]);

            if ($zone->wasRecentlyCreated) {
                $zone->syncProducts($products);
            }
        }
    }
}
