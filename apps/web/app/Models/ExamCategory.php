<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class ExamCategory extends Model
{
    /** @use HasFactory<ExamCategoryFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['name'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function exams(): HasMany
    {
        return $this->hasMany(Exam::class, 'category_id');
    }
}
