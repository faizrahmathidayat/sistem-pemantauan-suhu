<?php

namespace App\Support;

/**
 * Daftar produk zona ditulis sebagai teks, satu nama per baris.
 */
final class ZoneProducts
{
    public const MAX_PRODUCTS = 20;

    public const MAX_NAME_LENGTH = 80;

    /**
     * Pangkas spasi, buang baris kosong dan duplikat (tanpa membedakan huruf besar-kecil; ejaan
     * pertama yang dipakai). Batas jumlah dan panjang diperiksa pemanggil (form request).
     *
     * @return list<string>
     */
    public static function parse(string $text): array
    {
        $names = [];
        $seen = [];

        foreach (preg_split('/\R/u', $text) ?: [] as $line) {
            $name = trim($line);
            $key = mb_strtolower($name);
            if ($name === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $names[] = $name;
        }

        return $names;
    }
}
