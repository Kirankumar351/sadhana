<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\GlossaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Glossary extends Model
{
    /** @use HasFactory<GlossaryFactory> */
    use HasFactory;

    protected $table = 'glossary';

    protected $guarded = ['id'];
}
