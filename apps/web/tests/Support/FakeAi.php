<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\AiChunk;
use App\Services\Agents\AgentDecision;
use App\Services\AI\Contracts\EmbeddingClient;
use App\Services\AI\Contracts\ModelClient;
use App\Services\AI\Contracts\VectorStore;
use App\Services\AI\ModelResponse;
use App\Services\AI\ResolvedPrompt;

/**
 * Test doubles at the provider boundary.
 *
 * NOT A MOCK OF AiGateway. Faking the gateway itself would skip the seven things it exists
 * to do — intent routing, caps, caching, the retrieval gate, the output guard and metering
 * — which is precisely the code most worth testing. These fakes sit where the network
 * would be, so every test above them runs the real pipeline.
 */
final class FakeAi
{
    /**
     * Install the fakes and index some passages.
     *
     * @param  list<array{content: string, title?: string, locale?: string, source_type?: string, exam_id?: int}>  $corpus
     */
    public static function install(string $answer = 'A grounded answer.', array $corpus = []): FakeModelClient
    {
        $client = new FakeModelClient($answer);

        app()->instance(ModelClient::class, $client);
        app()->instance(EmbeddingClient::class, new FakeEmbeddingClient);

        $store = new FakeVectorStore;
        app()->instance(VectorStore::class, $store);

        foreach ($corpus as $i => $row) {
            $chunk = AiChunk::create([
                'source_type' => $row['source_type'] ?? 'material',
                'source_id' => $i + 1,
                'source_locale' => $row['locale'] ?? 'te',
                'chunk_index' => 0,
                'content' => $row['content'],
                'checksum' => hash('sha256', $row['content']),
                'metadata' => array_filter([
                    'title' => $row['title'] ?? null,
                    'exam_id' => $row['exam_id'] ?? null,
                ]),
            ]);

            $store->index($chunk->id);
        }

        return $client;
    }
}

/**
 * Returns whatever the test asked for, and records what it was asked.
 *
 * The recorded question is how a test checks that, say, "revision" depth actually sends a
 * different instruction rather than the same one with a smaller number attached.
 */
final class FakeModelClient implements ModelClient
{
    /** @var list<string> */
    public array $questions = [];

    /** @var list<ResolvedPrompt> */
    public array $prompts = [];

    /** @var array<string, mixed> */
    public array $extraction = [];

    public function __construct(public string $answer = 'A grounded answer.') {}

    public function complete(ResolvedPrompt $prompt, array $passages, string $question, string $tier = 'large'): ModelResponse
    {
        $this->questions[] = $question;
        $this->prompts[] = $prompt;

        return new ModelResponse(
            text: $this->answer,
            model: 'fake-model',
            inputTokens: 100,
            outputTokens: 200,
            confidence: 0.9,
        );
    }

    public function extract(string $instruction, string $content, array $schema, string $tier = 'large'): array
    {
        return $this->extraction;
    }

    public function decide(string $system, array $tools, array $transcript, array $input, string $tier = 'large'): AgentDecision
    {
        return AgentDecision::finish(['status' => 'done']);
    }
}

/**
 * A fixed vector. Retrieval order is decided by the store, not by distance, so tests stay
 * deterministic rather than depending on a similarity that means nothing here anyway.
 */
final class FakeEmbeddingClient implements EmbeddingClient
{
    public function embed(string $text): array
    {
        return array_fill(0, 8, 0.1);
    }

    public function embedBatch(array $texts): array
    {
        return array_map(fn (string $t): array => $this->embed($t), $texts);
    }

    public function model(): string
    {
        return 'fake-embedder';
    }

    public function dimensions(): int
    {
        return 8;
    }
}

/**
 * Returns everything indexed, in insertion order.
 *
 * Filters are honoured only where a test needs them to be — the point is to exercise the
 * gateway's behaviour when retrieval returns something or nothing, not to reimplement
 * Qdrant.
 */
final class FakeVectorStore implements VectorStore
{
    /** @var list<int> */
    private array $ids = [];

    public function index(int $chunkId): void
    {
        $this->ids[] = $chunkId;
    }

    public function search(array $vector, int $limit, array $filters = []): array
    {
        return array_map(
            static fn (int $id): array => ['id' => $id, 'score' => 0.9],
            array_slice($this->ids, 0, $limit),
        );
    }

    public function upsert(int $chunkId, array $vector, array $payload): void
    {
        if (! in_array($chunkId, $this->ids, true)) {
            $this->ids[] = $chunkId;
        }
    }

    public function delete(array $chunkIds): void
    {
        $this->ids = array_values(array_diff($this->ids, $chunkIds));
    }

    public function deleteBySource(string $sourceType, int $sourceId): void
    {
        $this->ids = [];
    }
}
