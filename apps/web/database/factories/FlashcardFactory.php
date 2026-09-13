<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Flashcard;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Flashcard>
 */
class FlashcardFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'deck' => fake()->randomElement(['Polity', 'History', 'Geography']),
            'front' => ['te' => fake()->sentence().' ?', 'en' => fake()->sentence().' ?'],
            'back' => ['te' => fake()->sentence(), 'en' => fake()->sentence()],
            'locale' => 'te',
            'source_type' => 'wrong_answer',
            'source_ref' => 'question:'.fake()->unique()->numberBetween(1, 9999),
            'ease' => 2.50,
            'interval_days' => 0,
            'due_at' => today()->toDateString(),
            'review_count' => 0,
            'lapse_count' => 0,
        ];
    }

    public function due(int $daysAgo = 0): self
    {
        return $this->state(fn (): array => [
            'due_at' => today()->subDays($daysAgo)->toDateString(),
        ]);
    }

    public function scheduled(int $daysAhead): self
    {
        return $this->state(fn (): array => [
            'due_at' => today()->addDays($daysAhead)->toDateString(),
            'interval_days' => $daysAhead,
            'review_count' => 3,
        ]);
    }
}
