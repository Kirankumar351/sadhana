<?php

declare(strict_types=1);

namespace App\Services\Agents\Contracts;

use App\Services\Agents\AgentContext;
use App\Services\Agents\ToolResult;

/**
 * One capability an agent can invoke.
 *
 * `description()` is read by the model, so it is a prompt and should be written as one:
 * say what the tool does, when to reach for it, and what it will not do. A vague
 * description is the most common reason an agent picks the wrong tool.
 *
 * `writesOwnerTable()` is the safety-critical declaration. An owner table holds an
 * eligibility criterion, a date, a fee or an answer key — the facts a student acts on.
 * ToolRegistry refuses to bind a tool that returns true here unless the binding is behind
 * human approval, so the rule is enforced at wiring time rather than trusted at runtime.
 */
interface Tool
{
    public function key(): string;

    public function description(): string;

    /**
     * JSON Schema for the arguments. Validated before `run()` is reached, so an
     * implementation never has to defend against a malformed call from the model.
     *
     * @return array<string, mixed>
     */
    public function inputSchema(): array;

    /**
     * True if this tool can modify a table that owns a student-facing fact.
     *
     * Almost every tool should return false. A tool that needs to return true probably
     * wants to be a draft-writing tool instead: propose into `ai_drafts` and let a person
     * promote it.
     */
    public function writesOwnerTable(): bool;

    public function isDestructive(): bool;

    /**
     * @param  array<string, mixed>  $input
     */
    public function run(array $input, AgentContext $context): ToolResult;
}
