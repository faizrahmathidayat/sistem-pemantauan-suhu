<?php

namespace App\Support;

use App\Models\User;
use App\Models\Warehouse;
use App\Models\Zone;
use Illuminate\Http\Request;

/**
 * Pilihan filter halaman Laporan dan kedua ekspornya, dibaca dari query string
 * (`gudang`, `zona`, `periode`, `dari`, `sampai`) dan dibatasi pada gudang yang boleh dilihat user.
 *
 * Id yang tidak ada → 404, gudang/zona di luar jangkauan user → 403. Isian yang salah tetapi
 * bukan soal akses (periode, zona bukan milik gudang) disimpan di `$error` agar halaman bisa
 * menjelaskannya; ekspor menolaknya dengan 422.
 */
final class ReportFilters
{
    public function __construct(
        public readonly ?Warehouse $warehouse,
        public readonly ?Zone $zone,
        public readonly ReportPeriod $period,
        public readonly string $periodKey,
        public readonly ?string $from,
        public readonly ?string $to,
        public readonly ?string $error,
    ) {}

    public static function resolve(Request $request, User $user): self
    {
        $error = null;
        $warehouse = null;
        $zone = null;

        $warehouseId = self::id($request, 'gudang');
        $zoneId = self::id($request, 'zona');

        if ($warehouseId !== null) {
            $warehouse = Warehouse::find($warehouseId) ?? abort(404);
            abort_unless($user->canAccessWarehouse($warehouse), 403);
        }

        if ($zoneId !== null) {
            $zone = Zone::find($zoneId) ?? abort(404);
            abort_unless($user->canAccessZone($zone), 403);

            if ($warehouse === null) {
                $warehouse = $zone->warehouse;
            } elseif ($zone->warehouse_id !== $warehouse->id) {
                $error = 'Zona tidak termasuk gudang yang dipilih.';
                $zone = null;
            }
        }

        $warehouse ??= Warehouse::visibleTo($user)->orderBy('name')->first();

        if ($zone === null && $zoneId === null && $warehouse !== null) {
            $zone = $warehouse->zones()->orderBy('name')->first();
        }

        [$period, $periodKey, $from, $to, $periodError] = self::period($request);

        return new self($warehouse, $zone, $period, $periodKey, $from, $to, $error ?? $periodError);
    }

    /** @return array{0: ReportPeriod, 1: string, 2: ?string, 3: ?string, 4: ?string} */
    private static function period(Request $request): array
    {
        $key = $request->query('periode');
        $key = is_string($key) && $key !== '' ? $key : '7d';
        $from = null;
        $to = null;

        try {
            if ($key === 'custom') {
                $from = self::text($request->query('dari'));
                $to = self::text($request->query('sampai'));

                return [ReportPeriod::custom($from, $to), $key, $from, $to, null];
            }

            return [ReportPeriod::preset($key), $key, null, null, null];
        } catch (InvalidReportPeriod $e) {
            return [ReportPeriod::preset('7d'), $key, $from, $to, $e->getMessage()];
        }
    }

    private static function id(Request $request, string $name): ?int
    {
        $value = $request->query($name);

        if ($value === null || $value === '') {
            return null;
        }
        // Id yang bukan angka biasa (mis. "abc" atau larik) diperlakukan sebagai tidak ditemukan.
        if (! is_string($value) || ! ctype_digit($value) || strlen($value) > 18) {
            abort(404);
        }

        return (int) $value;
    }

    private static function text(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }
}
