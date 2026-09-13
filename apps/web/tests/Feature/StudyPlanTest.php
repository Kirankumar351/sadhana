<?php

declare(strict_types=1);

use App\Livewire\Learning\StudyPlan as Screen;
use App\Models\Exam;
use App\Models\QuizAttempt;
use App\Models\StudyPlan;
use App\Models\User;
use App\Services\AI\Features\StudyPlanner;
use Carbon\CarbonImmutable;
use Livewire\Livewire;
use Tests\Support\FakeAi;

/**
 * The personal study plan.
 *
 * The three rules that make it trustworthy: the arithmetic is computed rather than
 * generated, it refuses to build anything from too few attempts, and it predicts nothing.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['preferred_locale' => 'te']);
    $this->exam = Exam::factory()->create(['short_name' => 'Group 2']);
    $this->target = CarbonImmutable::parse(today())->addDays(28);

    $this->user->examPreferences()->attach($this->exam->id, [
        'is_primary' => true,
        'target_date' => $this->target->toDateString(),
    ]);
});

/**
 * Resolved per call, never in beforeEach: the fakes are installed inside each test, and a
 * planner built before them would be holding the real provider client.
 */
function planner(): StudyPlanner
{
    return app(StudyPlanner::class);
}

/**
 * @param  array<string, int>  $breakdown
 */
function attempts(int $count, array $breakdown): void
{
    foreach (range(1, $count) as $i) {
        QuizAttempt::create([
            'user_id' => test()->user->id,
            'answers' => [],
            'score' => 5,
            'total_marks' => 10,
            'time_taken_sec' => 600,
            'subject_breakdown' => $breakdown,
            'completed_at' => now()->subDays($i),
        ]);
    }
}

it('refuses to build a plan from too few attempts', function (): void {
    // A plan built on a handful of quizzes is sampling error dressed as advice.
    attempts(5, ['Indian Polity' => 41, 'Telangana Movement' => 92]);

    expect(planner()->build($this->user, $this->exam, $this->target))->toBeNull()
        ->and(planner()->attemptsNeeded($this->user, $this->exam))->toBe(5);
});

it('says how many more quizzes are needed rather than showing an empty screen', function (): void {
    attempts(7, ['Indian Polity' => 41]);

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->assertSee(__('Not enough quizzes yet to build a plan worth following'))
        ->assertSee('3');
});

it('gives the weakest subject the largest share', function (): void {
    FakeAi::install('Polity is the real problem.', [
        ['content' => 'Polity covers Articles 12 to 35.', 'title' => 'Syllabus'],
        ['content' => 'The Telangana movement began in 1969.', 'title' => 'Paper IV'],
    ]);

    attempts(12, ['Indian Polity' => 41, 'Economy' => 65, 'Telangana Movement' => 92]);

    $plan = planner()->build($this->user, $this->exam, $this->target);
    $allocation = $plan->plan['allocation'];

    expect($allocation['Indian Polity'])->toBeGreaterThan($allocation['Economy'])
        ->and($allocation['Economy'])->toBeGreaterThan($allocation['Telangana Movement'])
        // Strength does not lose all its time: a subject at 92% still decays without revision.
        ->and($allocation['Telangana Movement'])->toBeGreaterThan(0);
});

it('always reserves a quarter of the time for mocks and revision', function (): void {
    FakeAi::install('Explanation.', []);
    attempts(12, ['Indian Polity' => 41, 'Economy' => 65]);

    $plan = planner()->build($this->user, $this->exam, $this->target);

    expect($plan->plan['allocation']['__mocks'])->toBeGreaterThanOrEqual(25);
});

it('allocates exactly one hundred percent', function (): void {
    // Rounding must not invent or lose study time.
    FakeAi::install('Explanation.', []);
    attempts(12, ['A' => 33, 'B' => 47, 'C' => 61, 'D' => 78, 'E' => 89]);

    $plan = planner()->build($this->user, $this->exam, $this->target);

    expect(array_sum($plan->plan['allocation']))->toBe(100);
});

it('leaves the final days for practice and sleep', function (): void {
    FakeAi::install('Explanation.', []);
    attempts(12, ['Indian Polity' => 41, 'Economy' => 65]);

    $plan = planner()->build($this->user, $this->exam, $this->target);
    $weeks = $plan->plan['weeks'];
    $lastWeek = end($weeks);

    expect($lastWeek['consolidation'])->toBeTrue()
        // No new topic in the last week, whatever the allocation says.
        ->and($lastWeek['focus'])->toBeNull();
});

it('records which subjects it considers weak', function (): void {
    FakeAi::install('Explanation.', []);
    attempts(12, ['Indian Polity' => 41, 'Economy' => 65, 'Telangana Movement' => 92]);

    $plan = planner()->build($this->user, $this->exam, $this->target);

    expect($plan->weak_subjects)->toBe(['Indian Polity'])
        ->and($plan->based_on_attempts)->toBe(12);
});

it('still produces a correct plan when the model is unavailable', function (): void {
    // The arithmetic is code. Losing the provider loses the prose, not the plan.
    FakeAi::install('unused', []);
    attempts(12, ['Indian Polity' => 41, 'Economy' => 65]);

    $plan = planner()->build($this->user, $this->exam, $this->target);

    expect($plan)->not->toBeNull()
        ->and($plan->plan['narrative'])->toBeNull()
        ->and(array_sum($plan->plan['allocation']))->toBe(100);
});

it('supersedes the previous plan rather than overwriting it', function (): void {
    FakeAi::install('Explanation.', []);
    attempts(12, ['Indian Polity' => 41]);

    $first = planner()->build($this->user, $this->exam, $this->target);
    $second = planner()->build($this->user, $this->exam, $this->target);

    expect($first->refresh()->superseded_at)->not->toBeNull()
        ->and($second->superseded_at)->toBeNull()
        ->and(StudyPlan::count())->toBe(2)
        ->and(planner()->current($this->user, $this->exam)->id)->toBe($second->id);
});

it('asks the model to explain the allocation, never to decide it', function (): void {
    $client = FakeAi::install('Explanation.', [
        ['content' => 'Polity covers Articles 12 to 35.', 'title' => 'Syllabus'],
        ['content' => 'Economy notes.', 'title' => 'Paper III'],
    ]);

    attempts(12, ['Indian Polity' => 41]);
    planner()->build($this->user, $this->exam, $this->target);

    expect($client->questions[0])->toContain('Explain this study allocation')
        // The finished numbers go in. The model is not asked what they should be.
        ->and($client->questions[0])->toContain('allocated')
        ->and($client->questions[0])->toContain('Do not predict whether they will pass');
});

it('shows the plan and refuses to promise an outcome', function (): void {
    FakeAi::install('Polity is the real problem.', [
        ['content' => 'Polity covers Articles 12 to 35.', 'title' => 'Syllabus'],
        ['content' => 'Economy notes.', 'title' => 'Paper III'],
    ]);

    attempts(12, ['Indian Polity' => 41, 'Telangana Movement' => 92]);
    planner()->build($this->user, $this->exam, $this->target);

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->assertSee(__('Week by week'))
        ->assertSee('Indian Polity')
        ->assertSee(__('This is a plan, not a prediction.'));
});

it('asks for an exam date before anything else', function (): void {
    $user = User::factory()->create();
    $exam = Exam::factory()->create();
    $user->examPreferences()->attach($exam->id, ['is_primary' => true]);

    Livewire::actingAs($user)
        ->test(Screen::class)
        ->assertSee(__('Set your exam date'));
});

it('requires a login', function (): void {
    $this->get(route('study-plan', ['locale' => 'te']))->assertRedirect();
});
