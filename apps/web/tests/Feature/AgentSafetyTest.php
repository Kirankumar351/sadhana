<?php

declare(strict_types=1);

use App\Models\AgentDefinition;
use App\Models\AiDraft;
use App\Models\ExamNotification;
use App\Models\Profile;
use App\Models\Question;
use App\Models\User;
use App\Services\Agents\AgentContext;
use App\Services\Agents\Exceptions\UnsafeToolBindingException;
use App\Services\Agents\ToolRegistry;
use App\Services\Agents\Tools\CheckEligibilityTool;
use App\Services\Agents\Tools\DraftQuestionTool;
use App\Services\Agents\Tools\SearchCorpusTool;
use Database\Seeders\AgentSeeder;

/**
 * Agent safety.
 *
 * The rule is: an agent drafts, a person decides. There is no autonomy level that
 * publishes. These tests exist because that rule is enforced by wiring rather than by
 * discipline, and wiring can be changed by someone who does not know why it is there.
 */
beforeEach(function (): void {
    $this->registry = app(ToolRegistry::class);
});

function definition(array $attributes = []): AgentDefinition
{
    return AgentDefinition::create(array_merge([
        'key' => 'test_agent',
        'name' => ['en' => 'Test'],
        'family' => 'ops',
        'goal' => 'A test agent.',
        'tools' => ['search_corpus'],
        'autonomy' => 'propose',
        'is_active' => false,
    ], $attributes));
}

// ================================================================ the allowlist

/**
 * A tool an agent cannot see, it cannot call. There is no wildcard and no "all tools"
 * mode, because the failure that would prevent — an agent reaching for a capability nobody
 * intended it to have — is the hardest kind to notice after the fact.
 */
it('resolves only the tools an agent declares', function (): void {
    $tools = $this->registry->for(definition(['tools' => ['search_corpus']]));

    expect($tools)->toHaveKey('search_corpus')
        ->and($tools)->not->toHaveKey('draft_question')
        ->and($tools)->not->toHaveKey('raise_alert');
});

it('refuses a tool that is not registered at all', function (): void {
    expect(fn () => $this->registry->for(definition(['tools' => ['publish_notification']])))
        ->toThrow(UnsafeToolBindingException::class);
});

/**
 * There is deliberately no tool for any of these. They are human actions, and their
 * absence from the registry is the enforcement.
 */
it('has no tool that can publish, approve or pay', function (string $forbidden): void {
    expect(fn () => $this->registry->resolve($forbidden))
        ->toThrow(UnsafeToolBindingException::class);
})->with([
    'publish_notification',
    'approve_question',
    'set_eligibility',
    'send_push_broadcast',
    'issue_refund',
    'change_prompt',
]);

// ================================================================ declared safety

/**
 * Every tool must appear in config('agents.tools') with its safety properties declared.
 * A tool registered in code but missing from that list fails at bind time rather than
 * running unreviewed.
 */
it('refuses a tool registered under a key that does not match its own', function (): void {
    // SearchCorpusTool reports its key as 'search_corpus'. Registering it as something
    // else would make it inherit whatever safety policy that other name happens to carry.
    $this->registry->register('undeclared_tool', SearchCorpusTool::class);

    expect(fn () => $this->registry->for(definition(['tools' => ['undeclared_tool']])))
        ->toThrow(UnsafeToolBindingException::class);
});

it('marks no currently registered tool as writing an owner table', function (): void {
    foreach (['search_corpus', 'check_eligibility', 'draft_question', 'raise_alert'] as $key) {
        expect($this->registry->resolve($key)->writesOwnerTable())->toBeFalse();
    }
});

// ================================================================ drafting, not publishing

it('writes a proposed question to the draft queue, never the bank', function (): void {
    $definition = definition(['tools' => ['draft_question']]);

    $run = $definition->runs()->create([
        'trigger' => 'manual',
        'status' => 'running',
        'started_at' => now(),
    ]);

    $result = (new DraftQuestionTool)->run([
        'question_en' => 'Which Article guarantees equality before the law?',
        'question_te' => 'చట్టం ముందు సమానత్వాన్ని ఏ అధికరణ హామీ ఇస్తుంది?',
        'options_en' => ['Article 14', 'Article 19', 'Article 21', 'Article 32'],
        'options_te' => ['అధికరణ 14', 'అధికరణ 19', 'అధికరణ 21', 'అధికరణ 32'],
        'correct_index' => 0,
        'subject' => 'Indian Polity',
    ], new AgentContext(run: $run, definition: $definition));

    expect($result->ok)->toBeTrue()
        ->and($result->createdDraft)->toBeTrue()
        ->and(AiDraft::query()->where('type', 'question')->where('status', 'pending')->count())->toBe(1)
        // Nothing reached the actual question bank.
        ->and(Question::query()->count())->toBe(0);
});

/**
 * The two option lists share one correct_index. A mismatch means the Telugu reader is
 * shown a different correct answer from the English reader — silent, and only discovered
 * by a student in the exam hall.
 */
it('rejects a draft whose Telugu options do not match the English ones', function (): void {
    $definition = definition(['tools' => ['draft_question']]);
    $run = $definition->runs()->create(['trigger' => 'manual', 'status' => 'running', 'started_at' => now()]);

    $result = (new DraftQuestionTool)->run([
        'question_en' => 'A question?',
        'options_en' => ['A', 'B', 'C', 'D'],
        'options_te' => ['ఎ', 'బి'],          // two, not four
        'correct_index' => 0,
        'subject' => 'Polity',
    ], new AgentContext(run: $run, definition: $definition));

    expect($result->ok)->toBeFalse()
        ->and(AiDraft::query()->count())->toBe(0);
});

it('rejects a draft whose correct_index points past the options', function (): void {
    $definition = definition(['tools' => ['draft_question']]);
    $run = $definition->runs()->create(['trigger' => 'manual', 'status' => 'running', 'started_at' => now()]);

    $result = (new DraftQuestionTool)->run([
        'question_en' => 'A question?',
        'options_en' => ['A', 'B'],
        'correct_index' => 5,
        'subject' => 'Polity',
    ], new AgentContext(run: $run, definition: $definition));

    expect($result->ok)->toBeFalse();
});

// ================================================================ eligibility routing

/**
 * An agent must never reason its way to an eligibility verdict. This tool exists so that
 * it cannot: it calls the deterministic engine and returns the answer verbatim.
 */
it('returns the deterministic verdict rather than a judgement', function (): void {
    $user = User::factory()->create();

    Profile::create([
        'user_id' => $user->id,
        'date_of_birth' => '1999-06-15',
        'category' => 'sc',
        'highest_qualification' => 'degree',
        'state' => 'TS',
    ]);

    $notification = ExamNotification::create([
        'slug' => 'agent-test-job',
        'title' => ['en' => 'Test job'],
        'organisation' => 'Board',
        'min_qualification' => 'degree',
        'min_age' => 18,
        'max_age' => 30,
        'age_relaxation' => ['sc' => 5],
        'age_reference_date' => '2026-07-01',
        'status' => 'published',
        'published_at' => now(),
    ]);

    $definition = definition(['family' => 'student', 'tools' => ['check_eligibility']]);
    $run = $definition->runs()->create(['trigger' => 'manual', 'status' => 'running', 'started_at' => now()]);

    $result = app(CheckEligibilityTool::class)->run(
        ['notification_slug' => $notification->slug],
        new AgentContext(run: $run, definition: $definition, user: $user),
    );

    expect($result->ok)->toBeTrue()
        ->and($result->data['status'])->toBe('eligible')
        // The caveat travels with the verdict so the agent cannot drop it.
        ->and($result->data['caveat'])->not->toBeEmpty();
});

it('refuses to check eligibility with no user in context', function (): void {
    $definition = definition(['tools' => ['check_eligibility']]);
    $run = $definition->runs()->create(['trigger' => 'manual', 'status' => 'running', 'started_at' => now()]);

    $result = app(CheckEligibilityTool::class)->run(
        ['notification_slug' => 'anything'],
        new AgentContext(run: $run, definition: $definition, user: null),
    );

    expect($result->ok)->toBeFalse();
});

// ================================================================ shipping posture

it('ships every catalogued agent disabled', function (): void {
    $this->seed(AgentSeeder::class);

    expect(AgentDefinition::query()->where('is_active', true)->count())->toBe(0)
        ->and(AgentDefinition::query()->count())->toBeGreaterThan(0);
});

/**
 * `propose` writes to ai_drafts and stops. Nothing above `act` exists, and `act` covers
 * internal state only.
 */
it('has no autonomy level above act', function (): void {
    $this->seed(AgentSeeder::class);

    $levels = AgentDefinition::query()->pluck('autonomy')->unique()->values()->all();

    expect($levels)->each->toBeIn(['propose', 'act', 'escalate']);
});
