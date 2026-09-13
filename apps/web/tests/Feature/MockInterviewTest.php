<?php

declare(strict_types=1);

use App\Livewire\Ai\MockInterview as Screen;
use App\Models\Exam;
use App\Models\InterviewSession;
use App\Models\InterviewTurn;
use App\Models\User;
use App\Services\AI\Features\MockInterview as Interviewer;
use Livewire\Livewire;
use Tests\Support\FakeAi;

/**
 * Mock interview practice.
 *
 * It is practice, not simulation, and the bio-data is the whole feature: the questions a
 * board asks come off the candidate's own form, and those are the ones nobody rehearses.
 */
beforeEach(function (): void {
    $this->user = User::factory()->create(['preferred_locale' => 'te']);
    $this->exam = Exam::factory()->withInterview()->create();
    $this->user->examPreferences()->attach($this->exam->id, ['is_primary' => true]);

    $this->bio = [
        'district' => 'Karimnagar',
        'graduation' => 'B.Sc Computer Science',
        'hobbies' => 'Reading Telugu literature, cricket',
        'optional' => 'Public Administration',
    ];
});

function boardReply(array $data = []): array
{
    return array_merge([
        'feedback' => [
            ['kind' => 'good', 'note' => 'Correct and specific — you named the river and the acreage'],
            ['kind' => 'warn', 'note' => 'Too short for an interview. A board reads brevity as thin preparation.'],
        ],
        'next_question' => 'కాళేశ్వరం వచ్చాక శ్రీరాంసాగర్ ప్రాముఖ్యత తగ్గిందంటారు. మీరు ఏమంటారు?',
        'board_member' => 2,
    ], $data);
}

it('opens somewhere the candidate cannot be wrong', function (): void {
    // A board that opens with a hard question tells you only how someone handles an ambush.
    FakeAi::install('unused', [], boardReply());

    $session = app(Interviewer::class)->start($this->user, $this->exam, $this->bio);

    expect($session->question_count)->toBe(1)
        ->and($session->turns()->first()->question)->toContain('Karimnagar');
});

it('builds the board prompt from the bio-data', function (): void {
    // Generic questions are in every guidebook. The ones off their own form are not.
    $client = FakeAi::install('unused', [], boardReply());

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio);
    $interviewer->answer($session, 'శ్రీరాంసాగర్ ప్రాజెక్టు. గోదావరి నదిపై ఉంది.');

    expect($client->instructions[0])->toContain('Karimnagar')
        ->and($client->instructions[0])->toContain('B.Sc Computer Science')
        ->and($client->instructions[0])->toContain('Reading Telugu literature')
        ->and($client->instructions[0])->toContain('Public Administration');
});

it('tells the board to press on the last answer rather than change topic', function (): void {
    $client = FakeAi::install('unused', [], boardReply());

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio);
    $interviewer->answer($session, 'శ్రీరాంసాగర్ ప్రాజెక్టు, గోదావరి నదిపై.');

    expect($client->instructions[0])->toContain('PREFER A FOLLOW-UP')
        // No score, ever: a real board reads composure and a file, and neither is here.
        ->and($client->instructions[0])->toContain('Never score the candidate')
        ->and($client->instructions[0])->toContain('Ask in Telugu');
});

it('asks in the language the candidate chose', function (): void {
    $client = FakeAi::install('unused', [], boardReply());

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio, 'mixed');
    $interviewer->answer($session, 'Sriram Sagar project, on the Godavari.');

    expect($client->instructions[0])->toContain('Telugu and English mixed');
});

it('records the answer, its feedback and the follow-up', function (): void {
    FakeAi::install('unused', [], boardReply());

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio);
    $interviewer->answer($session, 'శ్రీరాంసాగర్ ప్రాజెక్టు. గోదావరి నదిపై ఉంది.');

    $first = InterviewTurn::where('turn_index', 0)->first();
    $second = InterviewTurn::where('turn_index', 1)->first();

    expect($first->answer)->toContain('శ్రీరాంసాగర్')
        ->and($first->feedback)->toHaveCount(2)
        ->and($second)->not->toBeNull()
        ->and($second->board_member)->toBe(2)
        ->and($session->refresh()->question_count)->toBe(2);
});

it('passes the whole transcript so the board remembers what was said', function (): void {
    $client = FakeAi::install('unused', [], boardReply());

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio);
    $interviewer->answer($session, 'First answer about the project.');
    $interviewer->answer($session, 'Second answer about Kaleshwaram.');

    expect($client->contents[1])->toContain('First answer about the project')
        ->and($client->contents[1])->toContain('Second answer about Kaleshwaram');
});

it('ends with a report rather than running forever', function (): void {
    FakeAi::install('unused', [], boardReply([
        'session_summary' => 'You were specific on your district but brief throughout.',
        'strengths' => ['Specific on irrigation'],
        'work_on' => ['Answer length'],
    ]));

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio);

    foreach (range(1, Interviewer::TARGET_QUESTIONS) as $ignored) {
        $interviewer->answer($session->refresh(), 'An answer of adequate length for the board.');
    }

    $session->refresh();

    expect($session->completed_at)->not->toBeNull()
        ->and($session->report['summary'])->toContain('specific on your district')
        ->and($session->report['work_on'])->toBe(['Answer length'])
        ->and($session->question_count)->toBeLessThanOrEqual(Interviewer::TARGET_QUESTIONS + 1);
});

it('does nothing when there is no open question', function (): void {
    FakeAi::install('unused', [], boardReply());

    $session = InterviewSession::create([
        'user_id' => $this->user->id,
        'exam_id' => $this->exam->id,
        'bio_data' => $this->bio,
        'language' => 'te',
    ]);

    $result = app(Interviewer::class)->answer($session, 'An answer to nothing.');

    expect($result->succeeded())->toBeFalse()
        ->and($result->reason)->toBe('no_open_question');
});

it('leaves the turn unanswered when the provider fails', function (): void {
    // Losing the answer is worse than losing the turn: the candidate would have to retype it.
    FakeAi::install('unused', [], []);

    $interviewer = app(Interviewer::class);
    $session = $interviewer->start($this->user, $this->exam, $this->bio);
    $result = $interviewer->answer($session, 'An answer that the board never received.');

    expect($result->succeeded())->toBeFalse()
        ->and(InterviewTurn::where('turn_index', 0)->first()->answer)->toBeNull()
        ->and($session->refresh()->question_count)->toBe(1);
});

it('says plainly that it is practice and not simulation', function (): void {
    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->assertSee(__('Practice, not simulation.'))
        ->assertSee(__('Your bio-data — the board will ask from this'));
});

it('starts a session from the form', function (): void {
    FakeAi::install('unused', [], boardReply());

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('district', 'Karimnagar')
        ->set('graduation', 'B.Sc Computer Science')
        ->call('start')
        ->assertSee('Karimnagar');

    expect(InterviewSession::where('user_id', $this->user->id)->count())->toBe(1);
});

it('will not start without the bio-data the board reads from', function (): void {
    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('district', '')
        ->set('graduation', '')
        ->call('start')
        ->assertHasErrors(['district', 'graduation']);

    expect(InterviewSession::count())->toBe(0);
});

it('refuses a three-word answer the way a board would', function (): void {
    FakeAi::install('unused', [], boardReply());

    Livewire::actingAs($this->user)
        ->test(Screen::class)
        ->set('district', 'Karimnagar')
        ->set('graduation', 'B.Sc')
        ->call('start')
        ->set('answer', 'Sriram Sagar.')
        ->call('reply')
        ->assertHasErrors('answer');
});

it('never shows one candidates session to another', function (): void {
    $session = InterviewSession::create([
        'user_id' => User::factory()->create()->id,
        'exam_id' => $this->exam->id,
        'bio_data' => ['district' => 'Warangal'],
        'language' => 'te',
    ]);

    Livewire::actingAs($this->user)
        ->test(Screen::class, ['sessionId' => $session->id])
        ->assertDontSee('Warangal');
});

it('requires a login', function (): void {
    $this->get(route('mock-interview', ['locale' => 'te']))->assertRedirect();
});
