<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentEvalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEval extends Model
{
    /** @use HasFactory<AgentEvalFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'expectations' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'agent_definition_id');
    }
}
