<?php

namespace App\Enums;

enum ZoneStatus: string
{
    case Aman = 'aman';
    case Waspada = 'waspada';
    case Bahaya = 'bahaya';
    case Offline = 'offline';
    // Zona yang belum pernah mengirim data sama sekali (bukan offline).
    case TanpaData = 'tanpa_data';

    public function label(): string
    {
        return match ($this) {
            self::Aman => 'AMAN',
            self::Waspada => 'WASPADA',
            self::Bahaya => 'BAHAYA',
            self::Offline => 'OFFLINE',
            self::TanpaData => 'BELUM ADA DATA',
        };
    }

    /** Urutan tampil: makin kecil makin atas. */
    public function severity(): int
    {
        return match ($this) {
            self::Bahaya => 0,
            self::Offline => 1,
            self::Waspada => 2,
            self::Aman => 3,
            self::TanpaData => 4,
        };
    }
}
