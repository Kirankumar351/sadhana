<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * The price per million tokens for a model, in integer paise.
 *
 * WHY THIS IS NOT `config("ai.pricing.{$model}")`. Laravel reads dots in a config key as
 * nesting, so `gemini-3.8-flash` was looked up as `gemini-3` → `8-flash`, found nothing, and
 * every Gemini call was metered at zero. Claude's model ids happen to contain no dots, which
 * is why nobody noticed. The model id is used here as a literal array key.
 *
 * A zero cost is the dangerous failure: caps and the monthly ceiling are enforced from these
 * figures, so a model that looks free is a model nobody is limiting.
 */
final class AiPricing
{
    /**
     * @return array{input: float, output: float}|null
     */
    public static function rateFor(string $model): ?array
    {
        $table = config('ai.pricing');

        return is_array($table) && isset($table[$model]) && is_array($table[$model])
            ? $table[$model]
            : null;
    }

    public static function costPaise(string $model, int $inputTokens, int $outputTokens): int
    {
        $rate = self::rateFor($model);

        if ($rate === null) {
            return 0;
        }

        return (int) ceil(
            $inputTokens / 1_000_000 * $rate['input']
            + $outputTokens / 1_000_000 * $rate['output']
        );
    }
}
