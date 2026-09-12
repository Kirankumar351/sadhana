<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentStepFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentStep extends Model
{
    /** @use HasFactory<AgentStepFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'tool_input' => 'array',
            'tool_output' => 'array',
            'tool_error' => 'boolean',
        ];
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
