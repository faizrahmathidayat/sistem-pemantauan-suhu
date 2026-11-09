<?php

namespace Database\Factories;

use App\Enums\IncidentReason;
use App\Models\Incident;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Incident>
 */
class IncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'zone_id' => Zone::factory(),
            'reason' => IncidentReason::SuhuTinggi,
            'started_at' => now(),
            'ended_at' => null,
            'peak_value' => 26,
        ];
    }
}
