<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            // Indian mobile numbers start 6-9. Generating something that would fail our own
            // validation would make the factory useless for testing the real sign-up path.
            'phone' => (string) fake()->unique()->numberBetween(6000000000, 9999999999),
            'phone_verified_at' => now(),
            'preferred_locale' => 'te',
            'remember_token' => Str::random(10),
        ];
    }

    public function staff(): static
    {
        return $this->state(fn (): array => ['is_staff' => true]);
    }

    public function banned(): static
    {
        return $this->state(fn (): array => ['is_banned' => true, 'ban_reason' => 'test']);
    }
}
