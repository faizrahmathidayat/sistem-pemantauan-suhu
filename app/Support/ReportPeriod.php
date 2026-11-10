<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Rentang waktu sebuah laporan: salah satu preset (24 jam ... 90 hari) atau rentang tanggal sendiri.
 * Data hanya tersimpan 90 hari (SensorLog::RETENTION_DAYS), jadi rentang tidak boleh lebih lama.
 */
final class ReportPeriod
{
    public const MAX_DAYS = 90;

    /** @var array<string, string> kunci => label tombol */
    public const PRESETS = ['24h' => '24 jam', '7d' => '7 hari', '30d' => '30 hari', '90d' => '90 hari'];

    private const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    /** Ukuran bucket (detik) yang dipilih agar grafik berisi sekitar 300 titik atau kurang. */
    private const BUCKET_STEPS = [300, 900, 1800, 3600, 7200, 14400, 21600, 43200, 86400];

    private const TARGET_POINTS = 300;

    public function __construct(
        public readonly CarbonImmutable $from,
        public readonly CarbonImmutable $to,
        public readonly string $key,
    ) {}

    public static function preset(string $key, ?CarbonImmutable $now = null): self
    {
        $now ??= CarbonImmutable::now(config('app.timezone'));

        $from = match ($key) {
            '24h' => $now->subHours(24),
            '7d' => $now->subDays(7),
            '30d' => $now->subDays(30),
            '90d' => $now->subDays(self::MAX_DAYS),
            default => throw new InvalidReportPeriod('Periode tidak dikenal.'),
        };

        return new self($from, $now, $key);
    }

    /**
     * Rentang tanggal sendiri: "dari" mulai 00:00:00, "sampai" berakhir 23:59:59 (dipotong ke sekarang).
     *
     * @throws InvalidReportPeriod
     */
    public static function custom(string $from, string $to, ?CarbonImmutable $now = null): self
    {
        $tz = config('app.timezone');
        $now ??= CarbonImmutable::now($tz);

        $start = self::parseDate($from, $tz);
        $end = self::parseDate($to, $tz);

        if ($start > $end) {
            throw new InvalidReportPeriod('Tanggal awal tidak boleh setelah tanggal akhir.');
        }
        if ($start > $now) {
            throw new InvalidReportPeriod('Tanggal awal tidak boleh di masa depan.');
        }

        // Pembersihan data memakai "sekarang - 90 hari"; awal yang lebih lama pasti sebagian sudah hilang.
        $oldest = $now->subDays(self::MAX_DAYS);
        if ($start < $oldest) {
            $firstDay = $oldest->startOfDay() < $oldest ? $oldest->startOfDay()->addDay() : $oldest;

            throw new InvalidReportPeriod('Data hanya tersimpan '.self::MAX_DAYS.' hari; pilih tanggal awal mulai '.self::formatDate($firstDay).'.');
        }

        $end = $end->setTime(23, 59, 59);

        return new self($start, $end > $now ? $now : $end, 'custom');
    }

    public function seconds(): int
    {
        return $this->to->getTimestamp() - $this->from->getTimestamp();
    }

    public function bucketSeconds(): int
    {
        $needed = $this->seconds() / self::TARGET_POINTS;

        foreach (self::BUCKET_STEPS as $step) {
            if ($step >= $needed) {
                return $step;
            }
        }

        return self::BUCKET_STEPS[array_key_last(self::BUCKET_STEPS)];
    }

    public function label(): string
    {
        if ($this->key === 'custom') {
            return self::formatDate($this->from).' – '.self::formatDate($this->to);
        }

        return self::formatDate($this->from).' '.$this->from->format('H:i').' – '.self::formatDate($this->to).' '.$this->to->format('H:i');
    }

    private static function parseDate(string $value, string $tz): CarbonImmutable
    {
        // Parser bawaan PHP (Carbon melempar exception dengan pesan Inggris).
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone($tz));
        $errors = DateTimeImmutable::getLastErrors();

        // Tanggal mustahil (30 Feb) lolos parser PHP sebagai "warning", jadi diperiksa juga.
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidReportPeriod('Tanggal tidak valid.');
        }

        return CarbonImmutable::instance($date);
    }

    private static function formatDate(CarbonImmutable $date): string
    {
        return $date->day.' '.self::MONTHS[$date->month - 1].' '.$date->year;
    }
}
