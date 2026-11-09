<?php

namespace Database\Factories;

use App\Enums\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            // Bawaan Administrator: tes dan data contoh tidak terhalang izin kecuali diminta lain.
            'role_id' => fn () => Role::administrator()->id,
        ];
    }

    public function withRole(Role $role): static
    {
        return $this->state(fn (array $attributes) => ['role_id' => $role->id]);
    }

    public function withoutRole(): static
    {
        return $this->state(fn (array $attributes) => ['role_id' => null]);
    }

    /** User dengan role baru yang hanya berisi izin yang diminta. */
    public function withPermissions(Permission ...$permissions): static
    {
        return $this->state(fn (array $attributes) => [
            'role_id' => Role::factory()->withPermissions(...$permissions)->create()->id,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
