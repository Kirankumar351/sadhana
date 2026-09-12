<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentMemoryRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentMemoryRecord extends Model
{
    /** @use HasFactory<AgentMemoryRecordFactory> */
    use HasFactory;

    protected $table = 'agent_memories';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'confidence' => 'float',
        ];
    }

    public function definition(): BelongsTo
    {
        return $this->belongsTo(AgentDefinition::class, 'agent_definition_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
