<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Role '.fake()->unique()->lexify('????'),
            'is_system' => false,
        ];
    }

    public function withPermissions(Permission ...$permissions): static
    {
        return $this->afterCreating(fn (Role $role) => $role->syncPermissions(array_values($permissions)));
    }
}
