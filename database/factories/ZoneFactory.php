<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Zone>
 */
class ZoneFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Zona '.fake()->unique()->lexify('??'),
            'product_type' => ProductType::Sayur,
            'device_id' => 'gudang-'.fake()->unique()->slug(2),
            'device_token_hash' => null,
            'temp_min' => 15,
            'temp_max' => 25,
            'humidity_min' => 80,
            'humidity_max' => 95,
            'warning_margin' => 2,
            'humidity_warning_margin' => 5,
        ];
    }
}
