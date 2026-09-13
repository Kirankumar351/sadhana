<?php

declare(strict_types=1);

namespace App\Services\Agents\Tools;

use App\Services\Agents\AgentContext;
use App\Services\Agents\Contracts\Tool;
use App\Services\Agents\ToolResult;
use App\Services\AI\RetrievedPassage;
use App\Services\AI\Retriever;

/**
 * Search our own indexed corpus.
 *
 * The workhorse tool. Almost every agent needs it, and it is read-only by construction:
 * retrieval can only return what was derived from an owner table, so there is no path
 * through this tool to change anything.
 *
 * The description below is read by the model and is therefore a prompt. It says what the
 * tool will NOT do as well as what it will, because an agent that expects open-web search
 * will keep reaching for it and getting nothing.
 */
final class SearchCorpusTool implements Tool
{
    public function __construct(
        private readonly Retriever $retriever,
    ) {}

    public function key(): string
    {
        return 'search_corpus';
    }

    public function description(): string
    {
        return 'Search Sadhana\'s own indexed material: exam syllabi, notification facts, '
            .'previous papers, approved study material, answered doubts and current affairs '
            .'digests. This is NOT a web search — it only returns what we hold. If it '
            .'returns nothing, the material does not exist in our corpus and you should say '
            .'so rather than answering from memory.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => [
                    'type' => 'string',
                    'description' => 'What to look for, in natural language.',
                ],
                'locale' => [
                    'type' => 'string',
                    'enum' => ['te', 'en'],
                    'description' => 'Preferred language. Telugu passages rank first when te.',
                ],
                'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
            ],
            'required' => ['query'],
        ];
    }

    public function writesOwnerTable(): bool
    {
        return false;
    }

    public function isDestructive(): bool
    {
        return false;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(array $input, AgentContext $context): ToolResult
    {
        $passages = $this->retriever->retrieve(
            query: (string) $input['query'],
            locale: (string) ($input['locale'] ?? $context->locale()),
            limit: min((int) ($input['limit'] ?? 6), 10),
        );

        if ($passages === []) {
            // An explicit empty answer, not an error. The agent must be able to tell
            // "nothing indexed" apart from "the tool failed", because the correct response
            // differs: one is a corpus gap to report, the other is worth retrying.
            return ToolResult::success(
                ['passages' => []],
                'Nothing in our corpus matches that. Do not answer from general knowledge.',
            );
        }

        return ToolResult::success([
            'passages' => array_map(static fn (RetrievedPassage $p): array => [
                'content' => $p->content,
                'source' => $p->sourceType,
                'title' => $p->title,
                'score' => round($p->score, 3),
            ], $passages),
        ]);
    }
}
