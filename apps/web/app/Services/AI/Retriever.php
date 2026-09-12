<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Models\AiChunk;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\VectorStore;

/**
 * Retrieval over our own corpus, and nothing else.
 *
 * THE CONSTRAINT THAT MAKES THIS PRODUCT SAFE. The model may only answer from what we have
 * indexed. That is what stops a study assistant confidently inventing an exam pattern that
 * does not exist — the single most damaging thing a product like this can do — and it is
 * also what keeps generation legal, because the corpus contains only government documents,
 * material we wrote, and user notes we hold rights to. There is no coaching book to quote
 * because there is no coaching book in the index.
 *
 * When retrieval finds nothing, the correct behaviour is to say so and route to the
 * community. Never to fall back on general knowledge.
 *
 * Locale handling is deliberate: Telugu queries retrieve Telugu chunks first, then fall
 * back to English ones. A Telugu-medium student asking in Telugu should get the Telugu
 * source where one exists, but an English-only syllabus PDF is far better than no answer.
 */
final class Retriever
{
    public function __construct(
        private readonly EmbeddingClient $embeddings,
        private readonly VectorStore $vectors,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return list<RetrievedPassage>
     */
    public function retrieve(string $query, string $locale, array $context = [], int $limit = 6): array
    {
        $vector = $this->embeddings->embed($query);

        $filters = $this->filtersFrom($context, $locale);

        $hits = $this->vectors->search($vector, $limit * 2, $filters);

        if ($hits === []) {
            return [];
        }

        /** @var array<int, AiChunk> $chunks */
        $chunks = AiChunk::query()
            ->whereIn('id', array_column($hits, 'id'))
            ->get()
            ->keyBy('id')
            ->all();

        $passages = [];

        foreach ($hits as $hit) {
            $chunk = $chunks[$hit['id']] ?? null;

            // A hit whose owner row is gone means the corpus is momentarily ahead of a
            // deletion. Skipping it is what keeps a taken-down material out of an answer.
            if ($chunk === null) {
                continue;
            }

            $passages[] = new RetrievedPassage(
                chunkId: $chunk->id,
                sourceType: $chunk->source_type,
                sourceId: $chunk->source_id,
                content: $chunk->content,
                score: (float) $hit['score'],
                locale: $chunk->source_locale,
                title: $chunk->metadata['title'] ?? null,
                url: $chunk->metadata['url'] ?? null,
            );
        }

        return array_slice($this->preferLocale($passages, $locale), 0, $limit);
    }

    /**
     * Same-locale passages first, original relative order preserved within each group.
     *
     * @param  list<RetrievedPassage>  $passages
     * @return list<RetrievedPassage>
     */
    private function preferLocale(array $passages, string $locale): array
    {
        $native = array_values(array_filter(
            $passages,
            static fn (RetrievedPassage $p): bool => $p->locale === $locale,
        ));

        $other = array_values(array_filter(
            $passages,
            static fn (RetrievedPassage $p): bool => $p->locale !== $locale,
        ));

        return [...$native, ...$other];
    }

    /**
     * Scope the search when the question already has one.
     *
     * "Ask about this job" from a notification page must not retrieve a different
     * notification — answering about the wrong vacancy is worse than not answering.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function filtersFrom(array $context, string $locale): array
    {
        $filters = [];

        if (isset($context['notification_id'])) {
            $filters['source_type'] = 'notification';
            $filters['source_id'] = $context['notification_id'];
        }

        if (isset($context['exam_id'])) {
            $filters['exam_id'] = $context['exam_id'];
        }

        if (isset($context['source_types'])) {
            $filters['source_type'] = $context['source_types'];
        }

        $filters['locales'] = array_values(array_unique([$locale, config('locales.fallback')]));

        return $filters;
    }
}
