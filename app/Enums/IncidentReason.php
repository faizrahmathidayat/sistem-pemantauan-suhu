<?php

namespace App\Enums;

use App\Models\Zone;

enum IncidentReason: string
{
    case SuhuTinggi = 'suhu_tinggi';
    case SuhuRendah = 'suhu_rendah';
    case LembapTinggi = 'lembap_tinggi';
    case LembapRendah = 'lembap_rendah';
    case SensorOffline = 'sensor_offline';

    public function label(): string
    {
        return match ($this) {
            self::SuhuTinggi => 'Suhu terlalu tinggi',
            self::SuhuRendah => 'Suhu terlalu rendah',
            self::LembapTinggi => 'Kelembapan terlalu tinggi',
            self::LembapRendah => 'Kelembapan terlalu rendah',
            self::SensorOffline => 'Sensor tidak mengirim data',
        };
    }

    public function advice(): string
    {
        return match ($this) {
            self::SuhuTinggi => 'Segera cek sirkulasi udara dan nyalakan exhaust atau pendingin cadangan untuk mencegah produk layu atau rusak.',
            self::SuhuRendah => 'Segera cek pengaturan pendingin; suhu terlalu rendah dapat merusak produk.',
            self::LembapTinggi => 'Periksa ventilasi dan kemungkinan kebocoran; kelembapan tinggi memicu jamur dan pembusukan.',
            self::LembapRendah => 'Periksa humidifier dan ventilasi; udara terlalu kering membuat produk layu.',
            self::SensorOffline => 'Periksa daya dan koneksi Wi-Fi perangkat sensor; kondisi gudang saat ini tidak terpantau.',
        };
    }

    /** Batas zona yang dilanggar oleh alasan ini (null untuk sensor offline). */
    public function limitFor(Zone $zone): ?float
    {
        return match ($this) {
            self::SuhuTinggi => $zone->temp_max,
            self::SuhuRendah => $zone->temp_min,
            self::LembapTinggi => $zone->humidity_max,
            self::LembapRendah => $zone->humidity_min,
            self::SensorOffline => null,
        };
    }

    public function unit(): string
    {
        return $this->isHumidity() ? '%' : '°C';
    }

    public function isTemperature(): bool
    {
        return $this === self::SuhuTinggi || $this === self::SuhuRendah;
    }

    public function isHumidity(): bool
    {
        return $this === self::LembapTinggi || $this === self::LembapRendah;
    }

    /** Bahaya karena nilai terlalu tinggi (puncak = nilai terbesar). */
    public function isHigh(): bool
    {
        return $this === self::SuhuTinggi || $this === self::LembapTinggi;
    }
}
