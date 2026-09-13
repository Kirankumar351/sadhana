<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Test;
use App\Models\TestSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Test>
 */
class TestFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return [
            'test_series_id' => TestSeries::factory(),
            'slug' => "mock-test-{$n}",
            'title' => ['en' => "Mock Test {$n}", 'te' => "మాక్ టెస్ట్ {$n}"],
            'sequence' => 1,
            'duration_min' => 150,
            'total_marks' => 150.00,
            'negative_marking' => 0.33,
            'status' => 'published',
        ];
    }

    /**
     * The first mock in a series is free even in a paid one: people buy what they have
     * tried, and gating the sample loses the sale and the trust together.
     */
    public function freeSample(): self
    {
        return $this->state(fn (): array => ['is_free_sample' => true, 'sequence' => 1]);
    }
}
