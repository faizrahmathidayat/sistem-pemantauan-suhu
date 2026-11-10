<?php

namespace Tests\Unit;

use App\Support\Csv;
use App\Support\ReportPeriod;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CsvTest extends TestCase
{
    /**
     * @return array<string, array{0: string}>
     */
    public static function formulaStarts(): array
    {
        return [
            'sama dengan' => ['=SUM(A1)'],
            'plus' => ['+1'],
            'minus' => ['-1'],
            'at' => ['@x'],
            'tab' => ["\tx"],
            'carriage return' => ["\rx"],
        ];
    }

    #[DataProvider('formulaStarts')]
    public function test_text_that_looks_like_a_formula_is_prefixed_with_an_apostrophe(string $text): void
    {
        $this->assertSame("'".$text, Csv::cell($text));
    }

    public function test_normal_values_are_left_alone(): void
    {
        $this->assertSame('Zona A1', Csv::cell('Zona A1'));
        $this->assertSame(18.5, Csv::cell(18.5));
        $this->assertSame(-5.2, Csv::cell(-5.2));
        $this->assertSame(0, Csv::cell(0));
        $this->assertSame('', Csv::cell(null));
        $this->assertSame('', Csv::cell(''));
    }

    public function test_filename_uses_ascii_slugs_and_period_dates(): void
    {
        $period = ReportPeriod::preset('7d', CarbonImmutable::parse('2026-11-10 12:00:00', 'Asia/Jakarta'));

        $this->assertSame(
            'laporan-ringkasan-gudang-cikokol-telur-20261103-20261110.csv',
            Csv::filename('laporan-ringkasan', 'Gudang Cikokol (Telur)', $period),
        );
        $this->assertSame(
            'laporan-data-gudang-cikokol-telur-zona-fg-20261103-20261110.csv',
            Csv::filename('laporan-data', 'Gudang Cikokol (Telur)', $period, 'Zona FG'),
        );
    }
}
