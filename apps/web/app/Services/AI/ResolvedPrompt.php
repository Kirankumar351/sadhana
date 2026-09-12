<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * A resolved, versioned prompt.
 *
 * Hard blocks are NOT in here. The eligibility route, the retrieval gate and the output
 * filters live in application code where no prompt edit can reach them. What a prompt
 * carries is tone, structure and emphasis.
 *
 * Prompt editing is Owner-only by design. A prompt is not content — it silently changes the
 * behaviour of every answer the product gives. Treating it as a text field a content editor
 * can adjust is how a product's voice drifts with nobody noticing.
 */
final readonly class ResolvedPrompt
{
    /**
     * @param  array<string, mixed>  $parameters
     */
    public function __construct(
        public string $key,
        public string $version,
        public string $systemPrompt,
        public ?string $userTemplate = null,
        public array $parameters = [],
    ) {}

    /**
     * @param  array<string, string>  $replacements
     */
    public function renderSystem(array $replacements): string
    {
        return str_replace(
            array_map(static fn (string $k): string => '{'.$k.'}', array_keys($replacements)),
            array_values($replacements),
            $this->systemPrompt,
        );
    }
}
