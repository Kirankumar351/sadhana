<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AiGuardrailFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AiGuardrail extends Model
{
    /** @use HasFactory<AiGuardrailFactory> */
    use HasFactory;

    protected $table = 'ai_guardrails';

    protected $guarded = ['id'];
}
