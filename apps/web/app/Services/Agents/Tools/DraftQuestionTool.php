<?php

declare(strict_types=1);

namespace App\Services\Agents\Tools;

use App\Models\AiDraft;
use App\Services\Agents\AgentContext;
use App\Services\Agents\Contracts\Tool;
use App\Services\Agents\ToolResult;

/**
 * Propose a quiz question into the review queue.
 *
 * WRITES TO ai_drafts AND NOTHING ELSE. An agent cannot put a question into the bank; it
 * can only ask a person to. That separation is the entire reason the drafts table exists —
 * a fluent question with a wrong answer key teaches thousands of people something false,
 * and they carry it into the exam hall.
 *
 * The tool asks the agent to declare its own uncertainty. A model saying "both 2002 and
 * 2010 appear as the answer in different sources" is far more useful to a reviewer than a
 * confident wrong key, and asking for it costs nothing.
 */
final class DraftQuestionTool implements Tool
{
    public function key(): string
    {
        return 'draft_question';
    }

    public function description(): string
    {
        return 'Propose a bilingual multiple-choice question for human review. It does NOT '
            .'enter the question bank — a person verifies every answer key against a source '
            .'first. The Telugu options must be in the SAME ORDER as the English ones, '
            .'because a single correct_index is shared across both languages. If the correct '
            .'answer is disputed between sources, say so in flagged_reason rather than '
            .'picking one.';
    }

    /**
     * @return array<string, mixed>
     */
    public function inputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question_en' => ['type' => 'string'],
                'question_te' => ['type' => 'string'],
                'options_en' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 2, 'maxItems' => 6],
                'options_te' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 2, 'maxItems' => 6],
                'correct_index' => ['type' => 'integer', 'minimum' => 0],
                'explanation_en' => ['type' => 'string'],
                'explanation_te' => ['type' => 'string'],
                'subject' => ['type' => 'string'],
                'flagged_reason' => [
                    'type' => 'string',
                    'description' => 'Anything a reviewer should know: a disputed key, an ambiguous date, one source contradicting another.',
                ],
            ],
            'required' => ['question_en', 'options_en', 'correct_index', 'subject'],
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
        $english = (array) ($input['options_en'] ?? []);
        $telugu = (array) ($input['options_te'] ?? []);

        /**
         * A structural check the reviewer should never have to make by hand.
         *
         * The two option lists share one correct_index. A mismatch means the Telugu reader
         * is shown a different correct answer from the English reader — silent, and only
         * discovered by a student in the exam hall.
         */
        if ($telugu !== [] && count($telugu) !== count($english)) {
            return ToolResult::failure(
                'The Telugu and English option lists have different lengths. They share one '
                .'correct_index, so they must match exactly.'
            );
        }

        if ((int) $input['correct_index'] >= count($english)) {
            return ToolResult::failure('correct_index points past the end of the options.');
        }

        $draft = AiDraft::create([
            'type' => 'question',
            'payload' => $input,
            'source_refs' => ['agent_run' => $context->run->uuid],
            'flagged_reason' => $input['flagged_reason'] ?? null,
            'status' => 'pending',
        ]);

        return ToolResult::drafted($draft->id, 'question');
    }
}
