<?php

declare(strict_types=1);

namespace App\Services\AI;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * The one place that sends a request to the Gemini API.
 *
 * Shared by the chat client and the embedder so both get the same retry rule, which matters
 * more for Gemini than for most providers: in testing, a handful of calls produced both
 * "503 This model is currently experiencing high demand" and "429 quota exceeded".
 *
 * RETRY ONLY WHEN WAITING CAN HELP. A 503 or a short per-minute limit clears in seconds, so
 * those are retried with backoff. A quota whose reset is further away than `max_retry_wait`
 * is not retried at all: a student's page would hang for minutes to fail anyway, and the
 * gateway's "temporarily unavailable" is the honest answer sooner.
 */
final class GeminiTransport
{
    private const RETRYABLE = [429, 500, 503];

    public function isConfigured(): bool
    {
        return filled(config('ai.gemini.key'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function post(string $path, array $payload, int $timeout = 90): array
    {
        $key = (string) config('ai.gemini.key');

        if ($key === '') {
            throw new RuntimeException('GEMINI_API_KEY is not set.');
        }

        $url = rtrim((string) config('ai.gemini.base_url', 'https://generativelanguage.googleapis.com'), '/')
            .'/v1beta/'.ltrim($path, '/');

        $attempts = max(1, (int) config('ai.gemini.retries', 2) + 1);

        for ($attempt = 1; ; $attempt++) {
            try {
                $response = Http::withHeaders(['x-goog-api-key' => $key])
                    ->acceptJson()
                    ->timeout($timeout)
                    ->post($url, $payload);
            } catch (ConnectionException $e) {
                if ($attempt >= $attempts) {
                    throw new RuntimeException('Gemini is unreachable: '.$e->getMessage(), 0, $e);
                }

                Sleep::for($this->backoff($attempt))->seconds();

                continue;
            }

            if ($response->successful()) {
                return (array) $response->json();
            }

            $wait = $this->retryDelay($response, $attempt);

            if (in_array($response->status(), self::RETRYABLE, true)
                && $attempt < $attempts
                && $wait <= (int) config('ai.gemini.max_retry_wait', 10)) {
                Sleep::for($wait)->seconds();

                continue;
            }

            throw new RuntimeException('Model provider error: '.$response->status().' '
                .Str::limit((string) data_get($response->json(), 'error.message', $response->body()), 300));
        }
    }

    /**
     * The server's own RetryInfo when it sends one, exponential backoff otherwise.
     */
    private function retryDelay(Response $response, int $attempt): int
    {
        foreach ((array) data_get($response->json(), 'error.details', []) as $detail) {
            if (is_array($detail)
                && str_ends_with((string) ($detail['@type'] ?? ''), 'RetryInfo')
                && isset($detail['retryDelay'])) {
                return (int) ceil((float) rtrim((string) $detail['retryDelay'], 's'));
            }
        }

        return $this->backoff($attempt);
    }

    private function backoff(int $attempt): int
    {
        return min(2 ** $attempt, 16);
    }
}
