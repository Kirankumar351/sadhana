<?php

declare(strict_types=1);

namespace App\Services\AI;

use Livewire\Wireable;

/**
 * One passage retrieved from our own corpus.
 *
 * `sourceType` and `sourceId` point back at the owner row, which is what lets the UI render
 * a source card the student can actually open. An answer whose citations cannot be opened
 * is not meaningfully grounded — a citation has to be checkable to be worth anything.
 */
final readonly class RetrievedPassage implements Wireable
{
    public function __construct(
        public int $chunkId,
        public string $sourceType,
        public int $sourceId,
        public string $content,
        public float $score,
        public string $locale,
        public ?string $title = null,
        public ?string $url = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toCitation(): array
    {
        return [
            'chunk_id' => $this->chunkId,
            'type' => $this->sourceType,
            'id' => $this->sourceId,
            'title' => $this->title,
            'url' => $this->url,
            'score' => round($this->score, 4),
        ];
    }

    /**
     * Livewire serialisation. A passage travels with the answer it grounds, so it has to
     * survive the roundtrip too — see AiResult::toLivewire.
     *
     * @return array<string, mixed>
     */
    public function toLivewire(): array
    {
        return [
            'chunkId' => $this->chunkId,
            'sourceType' => $this->sourceType,
            'sourceId' => $this->sourceId,
            'content' => $this->content,
            'score' => $this->score,
            'locale' => $this->locale,
            'title' => $this->title,
            'url' => $this->url,
        ];
    }

    /**
     * @param  array<string, mixed>  $value
     */
    public static function fromLivewire($value): self
    {
        return new self(
            chunkId: (int) ($value['chunkId'] ?? 0),
            sourceType: (string) ($value['sourceType'] ?? ''),
            sourceId: (int) ($value['sourceId'] ?? 0),
            content: (string) ($value['content'] ?? ''),
            score: (float) ($value['score'] ?? 0.0),
            locale: (string) ($value['locale'] ?? ''),
            title: $value['title'] ?? null,
            url: $value['url'] ?? null,
        );
    }
}
