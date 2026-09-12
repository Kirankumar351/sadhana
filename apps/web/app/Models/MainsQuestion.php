<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MainsQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Translatable\HasTranslations;

class MainsQuestion extends Model
{
    /** @use HasFactory<MainsQuestionFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['question'];

    protected function casts(): array
    {
        return [
            'rubric' => 'array',
            'model_answer' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
