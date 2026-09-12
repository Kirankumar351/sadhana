<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ContentTranslationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ContentTranslation extends Model
{
    /** @use HasFactory<ContentTranslationFactory> */
    use HasFactory;

    protected $guarded = ['id'];
}
