<?php

namespace Database\Factories;

use App\Models\SensorLog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SensorLog>
 */
class SensorLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'device_id' => 'gudang-zona-a',
            'temperature' => 20,
            'humidity' => 85,
        ];
    }
}
