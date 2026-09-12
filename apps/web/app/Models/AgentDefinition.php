<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AgentDefinitionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Translatable\HasTranslations;

class AgentDefinition extends Model
{
    /** @use HasFactory<AgentDefinitionFactory> */
    use HasFactory, HasTranslations;

    protected $guarded = ['id'];

    /** @var list<string> */
    public array $translatable = ['name'];

    public function getRouteKeyName(): string
    {
        return 'key';
    }

    protected function casts(): array
    {
        return [
            'tools' => 'array',
            'trigger_events' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }
}
