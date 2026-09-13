<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GeneratedNote;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GeneratedNote>
 */
class GeneratedNoteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'topic' => 'The idea of Telangana, 1948-1970',
            'depth' => 'exam_focused',
            'locale' => 'te',
            'title' => 'తెలంగాణ ఆలోచన దశ — 1948 నుంచి 1970',
            'body' => "## ముఖ్య తేదీలు\n\n- 17 సెప్టెంబర్ 1948\n",
            'sources' => ['Paper IV syllabus', 'Telangana Movement — official notes'],
            'confidence' => 0.82,
            'is_saved' => true,
        ];
    }
}
