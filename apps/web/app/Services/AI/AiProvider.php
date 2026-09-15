<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * Which provider serves the AI layer, decided by which keys exist.
 *
 * WHICHEVER KEY IS CONFIGURED IS THE ONE THAT WORKS. `AI_PROVIDER` names a preference, not a
 * requirement: if it names a provider whose key is missing, the layer falls back to one that
 * has a key rather than switching every AI feature off. A deployment that holds only a
 * Gemini key must not sit dark because the default says "anthropic".
 *
 * With both keys present and `AI_PROVIDER=auto`, Anthropic is chosen: the prompts, the
 * guardrail thresholds and the cost figures were tuned against it first.
 *
 * Chat and embeddings are resolved separately, because Anthropic has no embedding model.
 * Voyage is preferred when its key is set; Gemini otherwise.
 */
final class AiProvider
{
    public const ANTHROPIC = 'anthropic';

    public const GEMINI = 'gemini';

    public const VOYAGE = 'voyage';

    /** The provider that answers questions, extracts and runs agents; null when none is keyed. */
    public function chat(): ?string
    {
        return $this->pick((string) config('ai.provider', 'auto'), [self::ANTHROPIC, self::GEMINI]);
    }

    /** The provider that embeds the corpus and queries; null when none is keyed. */
    public function embeddings(): ?string
    {
        return $this->pick((string) config('ai.embedding_provider', 'auto'), [self::VOYAGE, self::GEMINI]);
    }

    public function chatConfigured(): bool
    {
        return $this->chat() !== null;
    }

    public function embeddingsConfigured(): bool
    {
        return $this->embeddings() !== null;
    }

    /**
     * @param  list<string>  $order  fallback order when the preference cannot be honoured
     */
    private function pick(string $preference, array $order): ?string
    {
        $keyed = array_values(array_filter($order, fn (string $provider): bool => $this->hasKey($provider)));
        $preference = strtolower(trim($preference));

        if (in_array($preference, $keyed, true)) {
            return $preference;
        }

        return $keyed[0] ?? null;
    }

    private function hasKey(string $provider): bool
    {
        return filled(config("ai.{$provider}.key"));
    }
}
