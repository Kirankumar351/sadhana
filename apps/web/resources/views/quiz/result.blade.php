@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    {{-- Score first, explanation second. The number is what they came for. --}}
    <div class="card bg-green-wash p-6 text-center">
        <p class="text-body text-ink-soft">{{ __('Your score') }}</p>
        <p class="numeral font-display text-5xl font-extrabold text-green">
            {{ $attempt->correctCount() }}<span class="text-2xl text-ink-soft">/{{ (int) $attempt->total_marks }}</span>
        </p>

        @if ($streak)
            <p class="mt-3 inline-flex items-center gap-2 rounded-pill bg-white px-3 py-1.5 text-body font-semibold">
                🔥 <span class="numeral">{{ $streak->current_streak }}</span>
                {{ trans_choice('{1} day streak|[2,*] day streak', $streak->current_streak) }}
            </p>

            @if ($next = $streak->nextMilestone())
                <p class="mt-2 text-meta text-muted">
                    <span class="numeral">{{ $next - $streak->current_streak }}</span>
                    {{ __('more days to your next milestone') }}
                </p>
            @endif
        @endif
    </div>

    {{-- Weak area routes back into free material rather than a paywall. This is the
         moment a student is most receptive to being told what to study next. --}}
    @if ($weak = $attempt->weakestSubject())
        <div class="card mt-4 border-marigold bg-marigold-wash p-5">
            <h2 class="font-display text-card-title">{{ __('Work on this') }}</h2>
            <p class="mt-1 text-body text-ink-soft">
                {{ __('Your weakest area today was :subject.', ['subject' => __($weak)]) }}
            </p>
            <a href="{{ route('exams.index') }}" class="btn-secondary mt-3 text-body">
                {{ __('Find free material') }}
            </a>
        </div>
    @endif

    {{-- Answer review with explanations. The explanation is the actual learning; the
         score is only the hook that gets them to read it. --}}
    <section class="mt-6">
        <h2 class="font-display text-screen-title">{{ __('Your answers') }}</h2>

        <div class="mt-3 grid gap-3">
            @foreach ($attempt->dailyQuiz?->questions() ?? [] as $i => $question)
                @php
                    $row = collect($attempt->answers)->firstWhere('q', $question->id);
                    $chosen = $row['a'] ?? null;
                    $correct = $chosen !== null && $question->isCorrect((int) $chosen);
                    $options = $question->optionsFor();
                @endphp

                <article class="card p-4">
                    <div class="flex items-start gap-2">
                        <span class="numeral text-meta font-semibold text-muted">{{ $i + 1 }}.</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-card-title leading-relaxed">{{ $question->question }}</p>

                            <p class="mt-2 text-body">
                                @if ($chosen === null)
                                    <span class="text-muted">{{ __('Not answered') }}</span>
                                @elseif ($correct)
                                    <span class="font-semibold text-green">✓ {{ $options[$chosen] ?? '' }}</span>
                                @else
                                    <span class="font-semibold text-danger">✕ {{ $options[$chosen] ?? '' }}</span>
                                @endif
                            </p>

                            @unless ($correct)
                                <p class="mt-1 text-body">
                                    <span class="text-muted">{{ __('Correct answer') }}:</span>
                                    <span class="font-semibold text-green">{{ $options[$question->correct_index] ?? '' }}</span>
                                </p>
                            @endunless

                            @if ($question->explanation)
                                <p class="mt-2 rounded-control bg-paper px-3 py-2 text-body text-ink-soft">
                                    {{ $question->explanation }}
                                </p>
                            @endif
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    </section>

    <div class="mt-6 flex flex-wrap gap-3">
        <a href="{{ route('quiz.leaderboard') }}" class="btn-secondary">{{ __('Leaderboard') }}</a>
        <a href="{{ route('notifications.index') }}" class="btn-primary">{{ __('See new notifications') }}</a>
    </div>

</div>
@endsection
