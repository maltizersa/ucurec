<?php

namespace Database\Factories;

use App\Enums\Role;
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
     * Active researcher by default.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => Role::Researcher,
            'college_program' => fake()->randomElement(config('ucurec.colleges')),
            'contact_number' => '09'.fake()->numerify('#########'),
            'is_active' => true,
            'activated_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
            'remember_token' => Str::random(10),
        ];
    }

    public function role(Role $role): static
    {
        return $this->state(fn () => ['role' => $role]);
    }

    public function admin(): static
    {
        return $this->role(Role::Admin);
    }

    public function secretariat(): static
    {
        return $this->role(Role::Secretariat);
    }

    public function chair(): static
    {
        return $this->role(Role::Chair);
    }

    public function reviewer(): static
    {
        return $this->role(Role::Reviewer);
    }

    /**
     * A sign-up that the Secretariat has not activated yet.
     */
    public function pending(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'activated_at' => null,
        ]);
    }

    /**
     * An account that was active before and has been deactivated.
     */
    public function deactivated(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
            'deactivated_at' => now(),
        ]);
    }
}
