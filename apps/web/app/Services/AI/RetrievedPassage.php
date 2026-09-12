<?php

declare(strict_types=1);

namespace App\Services\AI;

/**
 * One passage retrieved from our own corpus.
 *
 * `sourceType` and `sourceId` point back at the owner row, which is what lets the UI render
 * a source card the student can actually open. An answer whose citations cannot be opened
 * is not meaningfully grounded — a citation has to be checkable to be worth anything.
 */
final readonly class RetrievedPassage
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
}
