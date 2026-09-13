<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    public function definition(): array
    {
        $n = fake()->unique()->numberBetween(1, 99999);

        return [
            'category_id' => ExamCategory::factory(),
            // Slugs are never translated — they are the permanent URL identity.
            'slug' => 'tgpsc-group-'.$n,
            'name' => ['en' => "TGPSC Group {$n}", 'te' => "TGPSC గ్రూప్ {$n}"],
            'short_name' => "Group {$n}",
            'conducting_body' => 'TGPSC',
            'state' => 'TS',
            'is_active' => true,
        ];
    }

    public function withMains(): self
    {
        return $this->state(fn (): array => ['has_mains' => true]);
    }

    public function withInterview(): self
    {
        return $this->state(fn (): array => ['has_interview' => true]);
    }
}
