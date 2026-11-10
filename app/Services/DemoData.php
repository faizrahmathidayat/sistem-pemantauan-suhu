<?php

namespace App\Services;

use App\Enums\IncidentReason;
use App\Models\Warehouse;
use App\Models\Zone;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Data demo untuk melihat dashboard dan Laporan tanpa sensor sungguhan. Semuanya ada di satu
 * gudang terpisah ("Gudang Demo", device_id `demo-*`) supaya bisa dihapus bersih tanpa
 * menyentuh data asli: `php artisan demo:seed`, `demo:simulate`, `demo:clear`.
 */
final class DemoData
{
    public const WAREHOUSE = 'Gudang Demo';

    public const STEP_MINUTES = 15;

    /** @var array<string, array{0: string, 1: list<string>}> device_id => [nama zona, produk] */
    private const ZONES = [
        'demo-inbound' => ['Zona Inbound', ['Telur Ayam Grade A', 'Telur Bebek']],
        'demo-fg' => ['Zona FG', ['Telur Ayam Grade A', 'Telur Ayam Grade B', 'Telur Bebek']],
        'demo-loading' => ['Zona Loading', ['Telur Ayam Grade A']],
    ];

    /** @var array<string, list<array{0: IncidentReason, 1: int, 2: string, 3: int}>> kejadian skrip: [alasan, berapa hari lalu, jam mulai, lama (menit)] */
    private const EVENTS = [
        'demo-inbound' => [[IncidentReason::SuhuTinggi, 3, '14:00', 90], [IncidentReason::SensorOffline, 12, '01:30', 45]],
        'demo-fg' => [[IncidentReason::LembapTinggi, 6, '02:00', 120], [IncidentReason::SensorOffline, 2, '03:00', 40]],
        'demo-loading' => [[IncidentReason::SuhuTinggi, 10, '11:00', 60], [IncidentReason::SuhuTinggi, 1, '16:00', 45]],
    ];

    public function exists(): bool
    {
        return Warehouse::where('name', self::WAREHOUSE)->exists();
    }

    /** @return Collection<int, Zone> */
    public function zones(): Collection
    {
        return Zone::whereIn('warehouse_id', Warehouse::where('name', self::WAREHOUSE)->select('id'))->orderBy('name')->get();
    }

    /**
     * Gudang, tiga zona, produk, riwayat pembacaan tiap 15 menit sampai sekarang, dan insiden
     * yang cocok dengan pembacaannya (lonjakan suhu/kelembapan dan sensor mati).
     *
     * @throws RuntimeException bila data demo sudah ada
     */
    public function seed(int $days = 30): void
    {
        if ($this->exists()) {
            throw new RuntimeException('Data demo sudah ada.');
        }

        $now = Carbon::now();
        $end = $now->copy()->startOfMinute()->setMinute(intdiv($now->minute, self::STEP_MINUTES) * self::STEP_MINUTES);
        $start = $end->copy()->subDays($days);

        DB::transaction(function () use ($start, $end): void {
            $warehouse = Warehouse::create(['name' => self::WAREHOUSE]);

            foreach (self::ZONES as $deviceId => [$name, $products]) {
                $zone = Zone::create([
                    'warehouse_id' => $warehouse->id, 'name' => $name, 'device_id' => $deviceId,
                    'temp_min' => 10, 'temp_max' => 20, 'humidity_min' => 60, 'humidity_max' => 85,
                ]);
                $zone->syncProducts($products);
                $this->history($zone, $start, $end);
            }
        });
    }

    /**
     * Hapus gudang demo beserta zona, pembacaan, insiden, dan notifikasinya.
     *
     * @return array{zones: int, readings: int, notifications: int}|null null bila tidak ada data demo
     */
    public function clear(): ?array
    {
        $warehouse = Warehouse::where('name', self::WAREHOUSE)->first();
        if ($warehouse === null) {
            return null;
        }

        return DB::transaction(function () use ($warehouse): array {
            $zones = $warehouse->zones()->get();
            $readings = DB::table('sensor_logs')->whereIn('device_id', $zones->pluck('device_id'))->delete();
            $notifications = DB::table('notifications')->where('data', 'like', '%'.self::WAREHOUSE.'%')->delete();
            foreach ($zones as $zone) {
                $zone->delete(); // insiden dan produk ikut terhapus (cascade)
            }
            $warehouse->delete();

            return ['zones' => $zones->count(), 'readings' => $readings, 'notifications' => $notifications];
        });
    }

    /**
     * Pembacaan normal (di dalam batas dan di luar rentang Waspada): naik-turun harian
     * dengan sedikit variasi acak yang tetap untuk waktu dan perangkat yang sama.
     *
     * @return array{temperature: float, humidity: float}
     */
    public function reading(string $deviceId, CarbonInterface $at): array
    {
        $day = ($at->hour * 60 + $at->minute) / 1440 * 2 * M_PI;
        $phase = (crc32($deviceId) % 360) * M_PI / 180;
        $noise = ((crc32($deviceId.'|'.$at->getTimestamp()) % 9) - 4) / 10; // -0,4 .. 0,4

        return [
            'temperature' => round(15 + 2.2 * sin($day + $phase) + $noise, 2),
            'humidity' => round(72 + 5 * cos($day + $phase) + $noise * 2, 2),
        ];
    }

    private function history(Zone $zone, Carbon $start, Carbon $end): void
    {
        $windows = $this->eventWindows($zone->device_id, $start, $end);
        $step = self::STEP_MINUTES;

        $rows = [];
        $danger = []; // indeks kejadian => [waktu pertama, waktu terakhir, nilai puncak]
        $lastEmitted = null;
        $offlineSince = null;

        for ($at = $start->copy(); $at <= $end; $at->addMinutes($step)) {
            $index = $this->windowAt($windows, $at);
            $reason = $index === null ? null : $windows[$index][0];

            if ($reason === IncidentReason::SensorOffline) {
                $offlineSince ??= $lastEmitted;

                continue; // sensor mati: tidak ada pembacaan
            }

            $reading = $this->reading($zone->device_id, $at);
            if ($reason !== null) {
                [, $from, $to] = $windows[$index];
                $bump = sin(M_PI * ($at->getTimestamp() - $from->getTimestamp()) / ($to->getTimestamp() - $from->getTimestamp()));
                $metric = $reason === IncidentReason::SuhuTinggi ? 'temperature' : 'humidity';
                $reading[$metric] = round(($reason === IncidentReason::SuhuTinggi ? 21 : 87) + 4 * $bump, 2);
                $danger[$index] = [
                    $danger[$index][0] ?? $at->copy(), $at->copy(), max($danger[$index][2] ?? 0, $reading[$metric]),
                ];
            }

            if ($offlineSince !== null) {
                // Sensor kembali: insiden offline berjalan dari 2 menit setelah pembacaan terakhir sampai sekarang.
                $zone->incidents()->create([
                    'reason' => IncidentReason::SensorOffline,
                    'started_at' => $offlineSince->copy()->addMinutes(2),
                    'ended_at' => $at->copy(),
                ]);
                $offlineSince = null;
            }

            $created = $at->format('Y-m-d H:i:s');
            $rows[] = ['device_id' => $zone->device_id, 'temperature' => $reading['temperature'], 'humidity' => $reading['humidity'], 'created_at' => $created, 'updated_at' => $created];
            $lastEmitted = $at->copy();
        }

        foreach (array_chunk($rows, 1000) as $chunk) {
            DB::table('sensor_logs')->insert($chunk);
        }

        foreach ($danger as $index => [$first, $last, $peak]) {
            $zone->incidents()->create([
                'reason' => $windows[$index][0],
                'started_at' => $first,
                'ended_at' => $last->copy()->addMinutes($step),
                'peak_value' => $peak,
            ]);
        }
    }

    /**
     * Kejadian skrip yang seluruhnya jatuh di dalam rentang riwayat (dan tidak di ujungnya).
     *
     * @return list<array{0: IncidentReason, 1: Carbon, 2: Carbon}>
     */
    private function eventWindows(string $deviceId, Carbon $start, Carbon $end): array
    {
        $windows = [];
        foreach (self::EVENTS[$deviceId] ?? [] as [$reason, $daysAgo, $time, $minutes]) {
            [$hour, $minute] = array_map('intval', explode(':', $time));
            $from = $end->copy()->startOfDay()->subDays($daysAgo)->setTime($hour, $minute);
            $to = $from->copy()->addMinutes($minutes);

            if ($from >= $start->copy()->addHour() && $to <= $end->copy()->subHour()) {
                $windows[] = [$reason, $from, $to];
            }
        }

        return $windows;
    }

    /** @param list<array{0: IncidentReason, 1: Carbon, 2: Carbon}> $windows */
    private function windowAt(array $windows, Carbon $at): ?int
    {
        foreach ($windows as $index => [, $from, $to]) {
            if ($at >= $from && $at < $to) {
                return $index;
            }
        }

        return null;
    }
}
