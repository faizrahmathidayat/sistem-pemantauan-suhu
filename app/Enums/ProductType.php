<?php

namespace App\Enums;

enum ProductType: string
{
    case Telur = 'telur';
    case Buah = 'buah';
    case Sayur = 'sayur';

    public function label(): string
    {
        return match ($this) {
            self::Telur => 'Telur',
            self::Buah => 'Buah',
            self::Sayur => 'Sayur',
        };
    }
}
