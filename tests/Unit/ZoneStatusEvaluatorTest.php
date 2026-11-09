<?php

namespace Tests\Unit;

use App\Enums\IncidentReason;
use App\Enums\ZoneStatus;
use App\Models\Zone;
use App\Services\ZoneStatusEvaluator;
use Tests\TestCase;

class ZoneStatusEvaluatorTest extends TestCase
{
    private function zone(array $overrides = []): Zone
    {
        return Zone::factory()->make(array_merge([
            'temp_min' => 15, 'temp_max' => 25, 'warning_margin' => 2,
            'humidity_min' => 80, 'humidity_max' => 95, 'humidity_warning_margin' => 5,
        ], $overrides));
    }

    public function test_exactly_at_max_is_waspada_not_bahaya(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(ZoneStatus::Waspada, $evaluator->evaluate($this->zone(), 25.0, 88));
        $this->assertSame([], $evaluator->dangerReasons($this->zone(), 25.0, 88));
    }

    public function test_above_max_is_bahaya_with_suhu_tinggi_reason(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(ZoneStatus::Bahaya, $evaluator->evaluate($this->zone(), 25.1, 88));
        $this->assertSame([IncidentReason::SuhuTinggi], $evaluator->dangerReasons($this->zone(), 25.1, 88));
    }

    public function test_below_min_is_bahaya_with_suhu_rendah_reason(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(ZoneStatus::Bahaya, $evaluator->evaluate($this->zone(), 14.9, 88));
        $this->assertSame([IncidentReason::SuhuRendah], $evaluator->dangerReasons($this->zone(), 14.9, 88));
    }

    public function test_waspada_band_boundaries(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(ZoneStatus::Aman, $evaluator->evaluate($this->zone(), 22.9, 88));
        $this->assertSame(ZoneStatus::Waspada, $evaluator->evaluate($this->zone(), 23.0, 88));
        $this->assertSame(ZoneStatus::Waspada, $evaluator->evaluate($this->zone(), 15.0, 88));
        $this->assertSame(ZoneStatus::Waspada, $evaluator->evaluate($this->zone(), 17.0, 88));
        $this->assertSame(ZoneStatus::Aman, $evaluator->evaluate($this->zone(), 17.1, 88));
    }

    public function test_humidity_out_of_range_is_bahaya_with_humidity_reason(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame([IncidentReason::LembapTinggi], $evaluator->dangerReasons($this->zone(), 20, 96));
        $this->assertSame([IncidentReason::LembapRendah], $evaluator->dangerReasons($this->zone(), 20, 79));
        $this->assertSame(ZoneStatus::Bahaya, $evaluator->evaluate($this->zone(), 20, 96));
    }

    public function test_humidity_waspada_band_uses_humidity_margin(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(ZoneStatus::Waspada, $evaluator->evaluate($this->zone(), 20, 90));
        $this->assertSame(ZoneStatus::Aman, $evaluator->evaluate($this->zone(), 20, 89.9));
    }

    public function test_null_humidity_limits_are_ignored(): void
    {
        $evaluator = new ZoneStatusEvaluator;
        $zone = $this->zone(['humidity_min' => null, 'humidity_max' => null]);

        $this->assertSame([], $evaluator->dangerReasons($zone, 20, 5));
        $this->assertSame(ZoneStatus::Aman, $evaluator->evaluate($zone, 20, 5));
    }

    public function test_multiple_reasons_are_all_reported(): void
    {
        $evaluator = new ZoneStatusEvaluator;

        $this->assertSame(
            [IncidentReason::SuhuTinggi, IncidentReason::LembapRendah],
            $evaluator->dangerReasons($this->zone(), 26, 70),
        );
    }
}
