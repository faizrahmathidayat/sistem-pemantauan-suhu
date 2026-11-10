<?php

namespace App\Support;

use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor CSV: UTF-8 dengan BOM (agar Excel membaca "°" dengan benar), pemisah koma,
 * titik desimal. Teks yang berawalan karakter formula diberi apostrof supaya tidak
 * dijalankan Excel (nama gudang dan zona diisi user).
 */
final class Csv
{
    private const FORMULA_STARTS = ['=', '+', '-', '@', "\t", "\r"];

    public static function cell(mixed $value): mixed
    {
        if ($value === null) {
            return '';
        }

        if (is_string($value) && $value !== '' && in_array($value[0], self::FORMULA_STARTS, true)) {
            return "'".$value;
        }

        return $value;
    }

    /**
     * @param  list<string>  $header
     * @param  iterable<list<mixed>>  $rows  dibaca bertahap saat respons dikirim
     */
    public static function download(string $filename, array $header, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($header, $rows): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array_map(self::cell(...), $header), ',', '"', '');
            foreach ($rows as $row) {
                fputcsv($out, array_map(self::cell(...), $row), ',', '"', '');
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    public static function filename(string $prefix, string $warehouse, ReportPeriod $period, ?string $zone = null): string
    {
        $parts = [$prefix, Str::slug($warehouse)];
        if ($zone !== null) {
            $parts[] = Str::slug($zone);
        }
        $parts[] = $period->from->format('Ymd');
        $parts[] = $period->to->format('Ymd');

        return implode('-', array_filter($parts, fn (string $part) => $part !== '')).'.csv';
    }
}
