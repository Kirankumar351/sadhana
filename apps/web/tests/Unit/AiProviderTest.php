<?php

declare(strict_types=1);

use App\Services\AI\AiProvider;
use App\Services\AI\AnthropicClient;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\ModelClient;
use App\Services\AI\GeminiClient;
use App\Services\AI\GeminiEmbedder;
use App\Services\AI\VoyageEmbedder;

/**
 * Whichever key is configured is the provider that works.
 *
 * The failure this prevents is a deployment holding a perfectly good Gemini key while every
 * AI feature reports "temporarily unavailable", because a default somewhere still says
 * "anthropic".
 */
beforeEach(function (): void {
    config([
        'ai.anthropic.key' => null,
        'ai.gemini.key' => null,
        'ai.voyage.key' => null,
        'ai.provider' => 'auto',
        'ai.embedding_provider' => 'auto',
    ]);

    app()->forgetInstance(ModelClient::class);
    app()->forgetInstance(EmbeddingClient::class);
});

it('uses Gemini for everything when only a Gemini key is set', function (): void {
    config(['ai.gemini.key' => 'gemini-key']);

    $provider = new AiProvider;

    expect($provider->chat())->toBe(AiProvider::GEMINI)
        ->and($provider->embeddings())->toBe(AiProvider::GEMINI)
        ->and(app(ModelClient::class))->toBeInstanceOf(GeminiClient::class)
        ->and(app(EmbeddingClient::class))->toBeInstanceOf(GeminiEmbedder::class);
});

it('uses Claude when only an Anthropic key is set', function (): void {
    config(['ai.anthropic.key' => 'anthropic-key']);

    expect((new AiProvider)->chat())->toBe(AiProvider::ANTHROPIC)
        ->and(app(ModelClient::class))->toBeInstanceOf(AnthropicClient::class);
});

it('prefers Claude when both keys are set and the choice is automatic', function (): void {
    config(['ai.anthropic.key' => 'anthropic-key', 'ai.gemini.key' => 'gemini-key']);

    expect((new AiProvider)->chat())->toBe(AiProvider::ANTHROPIC);
});

it('honours an explicit preference when that provider has a key', function (): void {
    config(['ai.anthropic.key' => 'anthropic-key', 'ai.gemini.key' => 'gemini-key', 'ai.provider' => 'gemini']);

    expect((new AiProvider)->chat())->toBe(AiProvider::GEMINI);
});

it('falls back to the provider that has a key when the preferred one does not', function (): void {
    // AI_PROVIDER=anthropic with only a Gemini key must not switch AI off.
    config(['ai.gemini.key' => 'gemini-key', 'ai.provider' => 'anthropic']);

    expect((new AiProvider)->chat())->toBe(AiProvider::GEMINI)
        ->and(app(ModelClient::class))->toBeInstanceOf(GeminiClient::class);
});

it('reports nothing configured when no key is set, and keeps a client that says so', function (): void {
    $provider = new AiProvider;

    expect($provider->chat())->toBeNull()
        ->and($provider->chatConfigured())->toBeFalse()
        ->and($provider->embeddingsConfigured())->toBeFalse()
        ->and(app(ModelClient::class)->isConfigured())->toBeFalse();
});

it('prefers Voyage for embeddings when its key is set', function (): void {
    config(['ai.voyage.key' => 'voyage-key', 'ai.gemini.key' => 'gemini-key']);

    expect((new AiProvider)->embeddings())->toBe(AiProvider::VOYAGE)
        ->and(app(EmbeddingClient::class))->toBeInstanceOf(VoyageEmbedder::class);
});

it('never treats an Anthropic key as an embedding key', function (): void {
    // Anthropic has no embedding model. This key once authenticated Voyage requests.
    config(['ai.anthropic.key' => 'anthropic-key']);

    expect((new AiProvider)->embeddings())->toBeNull()
        ->and(app(EmbeddingClient::class)->isConfigured())->toBeFalse();
});
