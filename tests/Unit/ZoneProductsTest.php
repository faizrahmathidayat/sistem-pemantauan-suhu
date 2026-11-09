<?php

namespace Tests\Unit;

use App\Support\ZoneProducts;
use PHPUnit\Framework\TestCase;

class ZoneProductsTest extends TestCase
{
    public function test_parse_trims_and_drops_blank_and_duplicate_lines(): void
    {
        $this->assertSame(
            ['Telur A', 'Telur B'],
            ZoneProducts::parse("Telur A\n\n telur a \r\nTelur B\n   \n"),
        );
    }

    public function test_parse_keeps_first_spelling_and_order(): void
    {
        $this->assertSame(['Telur Bebek', 'TELUR AYAM'], ZoneProducts::parse("Telur Bebek\nTELUR AYAM\ntelur bebek\nTelur Ayam"));
    }

    public function test_parse_of_empty_text_is_empty(): void
    {
        $this->assertSame([], ZoneProducts::parse(''));
        $this->assertSame([], ZoneProducts::parse(" \n \n"));
    }

    public function test_parse_keeps_all_names_so_limits_can_be_checked_by_the_caller(): void
    {
        $names = array_map(fn (int $i) => "Produk {$i}", range(1, 25));

        $this->assertCount(25, ZoneProducts::parse(implode("\n", $names)));
        $this->assertSame(20, ZoneProducts::MAX_PRODUCTS);
        $this->assertSame(80, ZoneProducts::MAX_NAME_LENGTH);
    }
}
