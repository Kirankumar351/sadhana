<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExamCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ExamCategory>
 */
class ExamCategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['State PSC', 'Railway', 'Banking', 'SSC', 'Police']);

        return [
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'name' => ['en' => $name, 'te' => $name],
            'sort_order' => 0,
        ];
    }
}
