<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiPrompt;
use App\Models\Glossary;
use Illuminate\Support\Facades\Cache;

/**
 * Versioned prompts, editable in admin, every change written to the audit log.
 *
 * Saving an edit creates a NEW version and leaves the old one available to roll back to.
 * Prompts are never edited in place, because the only way to know whether a change helped
 * is to be able to put the previous one back.
 *
 * The glossary is injected here, into every prompt, for every feature. That is the whole
 * reason the glossary table is the first thing to build: ten features read it, and without
 * it each one quietly produces Telugu that is grammatically fine and commercially useless,
 * because it uses words nobody searches for.
 */
final class PromptRegistry
{
    public function resolve(string $key, string $locale): ResolvedPrompt
    {
        /** @var AiPrompt|null $prompt */
        $prompt = Cache::remember(
            "ai:prompt:{$key}",
            now()->addMinutes(10),
            static fn () => AiPrompt::query()
                ->where('key', $key)
                ->where('is_active', true)
                ->latest('version')
                ->first(),
        );

        if ($prompt === null) {
            return $this->fallback($key, $locale);
        }

        return new ResolvedPrompt(
            key: $key,
            version: 'v'.$prompt->version,
            systemPrompt: $this->interpolate($prompt->system_prompt, $locale),
            userTemplate: $prompt->user_template,
            parameters: $prompt->parameters ?? [],
        );
    }

    /**
     * The glossary, rendered for prompt injection.
     *
     * Cached for an hour rather than read per request: it changes a few times a week and is
     * read on every single AI call in the product.
     */
    public function glossaryFor(string $locale): string
    {
        return Cache::remember(
            "ai:glossary:{$locale}",
            now()->addHour(),
            static function () use ($locale): string {
                $column = 'term_'.$locale;

                if (! in_array($column, ['term_te', 'term_hi', 'term_ta'], true)) {
                    return '';
                }

                return Glossary::query()
                    ->whereNotNull($column)
                    ->get()
                    ->map(static function (Glossary $term) use ($column): string {
                        return match ($term->rule) {
                            'do_not_translate' => "{$term->term_en} -> keep as \"{$term->term_en}\"",
                            'latin_only' => "{$term->term_en} -> Latin script only",
                            default => "{$term->term_en} -> {$term->{$column}}",
                        };
                    })
                    ->implode("\n");
            },
        );
    }

    private function interpolate(string $system, string $locale): string
    {
        return str_replace(
            ['{locale}', '{glossary}', '{language_name}'],
            [$locale, $this->glossaryFor($locale), (string) config("locales.supported.{$locale}.name", $locale)],
            $system,
        );
    }

    /**
     * Shipped default for the grounded answer, used before any prompt has been seeded.
     *
     * Note what is NOT here: the eligibility route, the retrieval gate and the output
     * filters. Those are application code. A prompt instruction can be talked around by a
     * determined user, so the prompt only has to handle the honest majority.
     */
    private function fallback(string $key, string $locale): ResolvedPrompt
    {
        $system = <<<'PROMPT'
            You answer questions for Telugu-speaking Indian government exam aspirants.

            Answer ONLY from the passages provided below. If they do not contain the answer,
            say so plainly and suggest asking the community. Never fill a gap from general
            knowledge.

            NEVER:
            - state whether a person is eligible for anything
            - give a date, fee or deadline that is not in the passages
            - predict a cutoff, a rank or a selection chance
            - promise or imply that following advice leads to selection

            Answer in {language_name}. Apply this glossary exactly — these are the words
            people search for:
            {glossary}

            Keep numerals in Latin script. Be direct: an aspirant is short of time and under
            pressure. Cite which passage each fact came from.
            PROMPT;

        return new ResolvedPrompt(
            key: $key,
            version: 'v0-fallback',
            systemPrompt: $this->interpolate($system, $locale),
        );
    }
}
