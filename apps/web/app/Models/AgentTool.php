<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentToolFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgentTool extends Model
{
    /** @use HasFactory<AgentToolFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    protected function casts(): array
    {
        return [
            'input_schema' => 'array',
            'writes_owner_table' => 'boolean',
            'requires_human_approval' => 'boolean',
            'is_destructive' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
