<?php

declare(strict_types=1);

use App\Services\AI\AnthropicClient;
use Illuminate\Support\Facades\Http;

/**
 * The shape of what we send the provider.
 *
 * No request here reaches the real API. These pin the parts of the payload that fail
 * silently when wrong: a thinking budget that eats a short answer cap returns an empty
 * string rather than an error, and a refusal that is read as text puts a fragment in front
 * of a student.
 */
beforeEach(function (): void {
    Http::preventStrayRequests();

    config([
        'ai.anthropic.key' => 'test-key',
        'ai.anthropic.base_url' => 'https://api.anthropic.com',
        'ai.models.large' => 'claude-sonnet-5',
        'ai.models.small' => 'claude-haiku-4-5',
    ]);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function fakeProvider(array $overrides = []): void
{
    Http::fake(['api.anthropic.com/v1/messages' => Http::response($overrides + [
        'content' => [['type' => 'text', 'text' => "```json\n{\"title\":\"TGPSC Group II\"}\n```"]],
        'stop_reason' => 'end_turn',
        'usage' => ['input_tokens' => 1200, 'output_tokens' => 80],
    ])]);
}

it('extracts JSON with room to finish and without thinking on Sonnet 5', function (): void {
    fakeProvider();

    $result = app(AnthropicClient::class)->extract('Extract the notification.', 'text', ['type' => 'object']);

    expect($result->data)->toBe(['title' => 'TGPSC Group II'])
        ->and($result->model)->toBe('claude-sonnet-5');

    Http::assertSent(fn ($request): bool => $request['model'] === 'claude-sonnet-5'
        && $request['max_tokens'] === 16000
        && $request['thinking'] === ['type' => 'disabled']
        && $request->hasHeader('x-api-key', 'test-key')
        && $request->hasHeader('anthropic-version', '2023-06-01'));
});

it('sends Haiku no thinking setting at all', function (): void {
    fakeProvider();

    app(AnthropicClient::class)->extract('Classify.', 'text', ['type' => 'object'], tier: 'small');

    Http::assertSent(fn ($request): bool => $request['model'] === 'claude-haiku-4-5'
        && ! array_key_exists('thinking', $request->data()));
});

it('treats a refusal as no data, even when text came back with it', function (): void {
    fakeProvider([
        'stop_reason' => 'refusal',
        'content' => [['type' => 'text', 'text' => '{"title":"partial"}']],
    ]);

    expect(app(AnthropicClient::class)->extract('Extract.', 'text', ['type' => 'object'])->data)->toBe([]);
});

it('refuses to call the provider without a key', function (): void {
    config(['ai.anthropic.key' => null]);

    expect(fn () => app(AnthropicClient::class)->extract('Extract.', 'text', []))
        ->toThrow(RuntimeException::class, 'ANTHROPIC_API_KEY');

    Http::assertNothingSent();
});

it('has a price for every model the application is configured to call', function (): void {
    // A model missing from the price table records zero cost, and a feature that looks free
    // on the usage dashboard is how an AI bill goes unnoticed for a month.
    foreach (['small', 'large'] as $tier) {
        expect(config('ai.pricing.'.config("ai.models.{$tier}")))->not->toBeNull();
    }
});
