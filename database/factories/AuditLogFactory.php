<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Zone;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'user_name' => fake()->name(),
            'event' => 'zone.limits_updated',
            'subject_type' => Zone::class,
            'subject_id' => fake()->numberBetween(1, 1000),
            'subject_label' => 'Zona '.fake()->lexify('??'),
            'diff' => ['temp_max' => ['from' => 20.0, 'to' => 22.0]],
            'created_at' => now(),
        ];
    }
}
