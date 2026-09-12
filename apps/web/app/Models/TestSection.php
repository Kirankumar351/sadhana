<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\TestSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class TestSection extends Model
{
    /** @use HasFactory<TestSectionFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['name'];

    protected function casts(): array
    {
        return [
            'question_ids' => 'array',
        ];
    }

    public function test(): BelongsTo
    {
        return $this->belongsTo(Test::class);
    }
}
