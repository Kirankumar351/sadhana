<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExamCutoffFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamCutoff extends Model
{
    /** @use HasFactory<ExamCutoffFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }
}
