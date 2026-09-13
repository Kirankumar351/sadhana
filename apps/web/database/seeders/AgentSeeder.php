<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AgentDefinition;
use App\Models\AgentTool;
use Illuminate\Database\Seeder;

/**
 * Seeds the agent catalogue from config.
 *
 * EVERY AGENT IS SEEDED INACTIVE. Turning one on is a deliberate operational decision with
 * an eval run behind it. A catalogue that starts running the moment it is merged is how an
 * AI bill arrives unannounced and how an unreviewed draft reaches a student.
 *
 * An agent is configuration rather than a class because the ones worth having are
 * discovered while operating the product, not while designing it. Editing a goal or
 * narrowing a toolset should not require a deploy.
 */
class AgentSeeder extends Seeder
{
    public function run(): void
    {
        $this->tools();
        $this->agents();
    }

    /**
     * The tool table mirrors config('agents.tools') so the admin panel can show what each
     * agent may do without reading a config file.
     */
    private function tools(): void
    {
        $descriptions = [
            'search_corpus' => 'Search our own indexed material. Read-only, never the open web.',
            'check_eligibility' => 'Ask the deterministic engine whether a user qualifies.',
            'draft_question' => 'Propose a bilingual MCQ into the review queue.',
            'raise_alert' => 'Flag something for a person to look at.',
        ];

        foreach (config('agents.tools', []) as $key => $policy) {
            AgentTool::updateOrCreate(['key' => $key], [
                'name' => str_replace('_', ' ', ucfirst($key)),
                'description' => $descriptions[$key] ?? 'See config/agents.php.',
                'input_schema' => [],
                'handler_class' => 'App\Services\Agents\Tools',
                'writes_owner_table' => $policy['writes_owner'] ?? false,
                'requires_human_approval' => $policy['approval'] ?? false,
                'is_destructive' => false,
                // Only tools with an implementation are marked active. The rest are
                // catalogued so the intended surface is visible, and cannot be bound.
                'is_active' => isset($descriptions[$key]),
            ]);
        }
    }

    private function agents(): void
    {
        foreach (config('agents.catalogue', []) as $key => $spec) {
            // Bind only tools that actually exist. A definition listing an unimplemented
            // tool would fail at bind time, which is correct but unhelpful in a seeder.
            $available = array_values(array_intersect(
                $spec['tools'] ?? [],
                ['search_corpus', 'check_eligibility', 'draft_question', 'raise_alert'],
            ));

            AgentDefinition::updateOrCreate(['key' => $key], [
                'name' => ['en' => str_replace('_', ' ', ucwords($key, '_'))],
                'description' => mb_substr($spec['goal'] ?? '', 0, 400),
                'family' => $spec['family'],
                'goal' => $spec['goal'],
                'tools' => $available,
                'model_tier' => $spec['model_tier'] ?? 'large',
                'autonomy' => $spec['autonomy'] ?? 'propose',
                'schedule_cron' => $spec['schedule'] ?? null,
                // Always off. See the class docblock.
                'is_active' => false,
            ]);
        }

        $this->command?->info(
            'Seeded '.count(config('agents.catalogue', [])).' agents, all inactive. '
            .'Run `php artisan agents:eval` before enabling one.'
        );
    }
}
