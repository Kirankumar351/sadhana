<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Exam;
use App\Models\TestSeries;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TestSeries>
 */
class TestSeriesFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return [
            'exam_id' => Exam::factory(),
            'slug' => "mock-series-{$n}",
            'title' => ['en' => "Mock Series {$n}", 'te' => "మాక్ సిరీస్ {$n}"],
            'is_free' => false,
            // Money is integer paise everywhere. Floats and rupees never touch the database.
            'price_paise' => 49900,
            'status' => 'published',
        ];
    }

    public function free(): self
    {
        return $this->state(fn (): array => ['is_free' => true, 'price_paise' => null]);
    }
}
