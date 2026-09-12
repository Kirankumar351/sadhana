<?php

declare(strict_types=1);

namespace App\Services\Agents;

/**
 * What a tool returns to the agent loop.
 *
 * A failure is a normal outcome, not an exception: the agent reads the message and usually
 * recovers on the next step. Crashing a run because one tool call failed throws away every
 * step that came before it, which is both expensive and unhelpful.
 *
 * `createdDraft` is counted on the run so the newsroom and review queues can show which
 * agent produced the work waiting for a human.
 */
final readonly class ToolResult
{
    /**
     * @param  array<string, mixed>  $data
     */
    private function __construct(
        public bool $ok,
        public array $data = [],
        public ?string $message = null,
        public bool $createdDraft = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function success(array $data, ?string $message = null): self
    {
        return new self(ok: true, data: $data, message: $message);
    }

    /**
     * A tool that proposed something for human review.
     */
    public static function drafted(int $draftId, string $type): self
    {
        return new self(
            ok: true,
            data: ['draft_id' => $draftId, 'type' => $type],
            message: "Draft {$draftId} created and queued for human review.",
            createdDraft: true,
        );
    }

    public static function failure(string $message): self
    {
        return new self(ok: false, message: $message);
    }

    /**
     * Rendered back into the transcript for the model to read.
     *
     * Truncated hard: a tool returning a whole syllabus would push the real context out of
     * the window and cost a fortune doing it. Two good passages beat a large dump.
     */
    public function toPromptString(int $limit = 4000): string
    {
        if (! $this->ok) {
            return 'ERROR: '.($this->message ?? 'tool failed');
        }

        $body = json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) ?: '{}';

        if (mb_strlen($body) > $limit) {
            $body = mb_substr($body, 0, $limit)."\n... (truncated)";
        }

        return trim(($this->message ?? '')."\n".$body);
    }
}
