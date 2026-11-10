<?php

namespace Tests\Unit;

use App\Support\InvalidReportPeriod;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ReportPeriodTest extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();

        $this->now = CarbonImmutable::parse('2026-11-10 12:00:00', 'Asia/Jakarta');
    }

    public function test_presets(): void
    {
        $day = ReportPeriod::preset('24h', $this->now);
        $this->assertSame('2026-11-09 12:00:00', $day->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 12:00:00', $day->to->format('Y-m-d H:i:s'));
        $this->assertSame('24h', $day->key);

        $this->assertSame('2026-11-03 12:00:00', ReportPeriod::preset('7d', $this->now)->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-12 12:00:00', ReportPeriod::preset('90d', $this->now)->from->format('Y-m-d H:i:s'));
    }

    public function test_unknown_preset_is_rejected(): void
    {
        $this->expectException(InvalidReportPeriod::class);
        ReportPeriod::preset('1y', $this->now);
    }

    public function test_custom_covers_whole_days_inclusive(): void
    {
        $period = ReportPeriod::custom('2026-11-01', '2026-11-03', $this->now);

        $this->assertSame('2026-11-01 00:00:00', $period->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-03 23:59:59', $period->to->format('Y-m-d H:i:s'));
        $this->assertSame('custom', $period->key);
    }

    public function test_custom_clamps_to_now(): void
    {
        $this->assertSame('2026-11-10 12:00:00', ReportPeriod::custom('2026-11-09', '2026-11-10', $this->now)->to->format('Y-m-d H:i:s'));
        $this->assertSame('2026-11-10 12:00:00', ReportPeriod::custom('2026-11-09', '2026-12-31', $this->now)->to->format('Y-m-d H:i:s'));
    }

    public function test_earliest_allowed_custom_start_is_the_first_day_fully_inside_retention(): void
    {
        // Pembersihan data memakai now - 90 hari (12 Agu 12:00), jadi 13 Agu 00:00 adalah awal paling lama.
        $period = ReportPeriod::custom('2026-08-13', '2026-11-10', $this->now);

        $this->assertSame('2026-08-13 00:00:00', $period->from->format('Y-m-d H:i:s'));
        $this->assertLessThanOrEqual(ReportPeriod::MAX_DAYS * 86400, $period->seconds());
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function invalidCustomPeriods(): array
    {
        return [
            'bukan tanggal' => ['abc', '2026-11-03', 'Tanggal tidak valid.'],
            'tanggal mustahil' => ['2026-02-30', '2026-11-03', 'Tanggal tidak valid.'],
            'akhir bukan tanggal' => ['2026-11-01', '', 'Tanggal tidak valid.'],
            'awal setelah akhir' => ['2026-11-05', '2026-11-01', 'Tanggal awal tidak boleh setelah tanggal akhir.'],
            'awal di masa depan' => ['2026-11-11', '2026-11-12', 'Tanggal awal tidak boleh di masa depan.'],
            'lebih lama dari retensi' => ['2026-08-12', '2026-11-10', 'Data hanya tersimpan 90 hari; pilih tanggal awal mulai 13 Agu 2026.'],
            'jauh lebih lama' => ['2026-01-01', '2026-02-01', 'Data hanya tersimpan 90 hari; pilih tanggal awal mulai 13 Agu 2026.'],
        ];
    }

    #[DataProvider('invalidCustomPeriods')]
    public function test_invalid_custom_periods_are_rejected_with_a_message(string $from, string $to, string $message): void
    {
        try {
            ReportPeriod::custom($from, $to, $this->now);
            $this->fail('InvalidReportPeriod tidak dilempar');
        } catch (InvalidReportPeriod $e) {
            $this->assertSame($message, $e->getMessage());
        }
    }

    public function test_bucket_seconds_picks_smallest_step_for_at_most_300_points(): void
    {
        $this->assertSame(300, ReportPeriod::preset('24h', $this->now)->bucketSeconds());
        $this->assertSame(3600, ReportPeriod::preset('7d', $this->now)->bucketSeconds());
        $this->assertSame(14400, ReportPeriod::preset('30d', $this->now)->bucketSeconds());
        $this->assertSame(43200, ReportPeriod::preset('90d', $this->now)->bucketSeconds());
        $this->assertSame(300, (new ReportPeriod($this->now->subHour(), $this->now, 'custom'))->bucketSeconds());
    }

    public function test_label(): void
    {
        $this->assertSame('3 Nov 2026 12:00 – 10 Nov 2026 12:00', ReportPeriod::preset('7d', $this->now)->label());
        $this->assertSame('1 Nov 2026 – 3 Nov 2026', ReportPeriod::custom('2026-11-01', '2026-11-03', $this->now)->label());
    }
}
