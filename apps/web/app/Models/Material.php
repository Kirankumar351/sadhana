<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MaterialFactory;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Translatable\HasTranslations;

class Material extends Model
{
    /** @use HasFactory<MaterialFactory> */
    use HasFactory, HasTranslations, HasUuids, SoftDeletes;

    protected $table = 'materials';

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['title', 'description'];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    protected function casts(): array
    {
        return [
            'copyright_confirmed' => 'boolean',
            'reviewed_at' => 'datetime',
            'copyright_confirmed_at' => 'datetime',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'edited_by');
    }
}
