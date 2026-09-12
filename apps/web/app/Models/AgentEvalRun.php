<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentEvalRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AgentEvalRun extends Model
{
    /** @use HasFactory<AgentEvalRunFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'passed' => 'boolean',
            'failures' => 'array',
        ];
    }

    public function eval(): BelongsTo
    {
        return $this->belongsTo(AgentEval::class, 'agent_eval_id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }
}
