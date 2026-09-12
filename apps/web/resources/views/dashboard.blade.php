@extends('layouts.app')

@section('content')

<h1 class="font-display text-screen-title">
    {{ __('Hello, :name', ['name' => $user->name]) }}
</h1>

<div class="mt-5 grid gap-4 lg:grid-cols-3">

    {{-- Left: the daily habit, then the feed --}}
    <div class="lg:col-span-2">

        <div class="card p-5 {{ $attempt ? 'bg-green-wash' : 'bg-marigold-wash' }}">
            @if (! $quiz)
                <p class="text-body text-ink-soft">{{ __("Today's quiz goes live at 7 AM.") }}</p>
            @elseif ($attempt)
                <p class="text-body text-ink-soft">{{ __('Done for today.') }}</p>
                <p class="numeral mt-1 font-display text-2xl font-extrabold text-green">
                    {{ $attempt->correctCount() }}/{{ (int) $attempt->total_marks }}
                </p>
                <a href="{{ route('quiz.result', ['attempt' => $attempt->id]) }}"
                   class="mt-2 inline-block text-body font-medium text-green hover:underline">
                    {{ __('See your answers') }}
                </a>
            @else
                <p class="font-display text-card-title">రోజు ప్రశ్న</p>
                <p class="mt-1 text-body text-ink-soft">{{ __('Ten questions are waiting.') }}</p>
                <a href="{{ route('quiz.today') }}" class="btn-accent mt-3">{{ __("Start today's quiz") }}</a>
            @endif
        </div>

        <section class="mt-6">
            <div class="flex items-baseline justify-between">
                <h2 class="font-display text-screen-title">{{ __('For you') }}</h2>
                <a href="{{ route('notifications.index') }}" class="text-body font-medium text-green hover:underline">
                    {{ __('See all') }}
                </a>
            </div>

            <div class="mt-3 grid gap-3">
                @forelse ($feed as $n)
                    <x-notification-card :n="$n" />
                @empty
                    <p class="card p-6 text-center text-body text-muted">
                        {{ __('Nothing new right now.') }}
                    </p>
                @endforelse
            </div>
        </section>
    </div>

    {{-- Right rail --}}
    <aside class="grid gap-4 content-start">

        <div class="card p-5 text-center">
            <p class="text-meta text-muted">{{ __('Streak') }}</p>
            <p class="numeral font-display text-4xl font-extrabold text-marigold">
                🔥 {{ $streak?->current_streak ?? 0 }}
            </p>
            @if ($streak && $streak->longest_streak > 0)
                <p class="mt-1 text-meta text-muted">
                    {{ __('Best') }}: <span class="numeral">{{ $streak->longest_streak }}</span>
                </p>
            @endif
            @if ($streak && $streak->freezes_left > 0)
                {{-- The freeze is surfaced, not hidden. Knowing the safety net exists is
                     what stops someone abandoning a streak after one missed day. --}}
                <p class="mt-2 rounded-control bg-paper px-2 py-1 text-meta text-muted">
                    {{ __('You have 1 freeze left this month') }}
                </p>
            @endif
        </div>

        <div class="card p-5">
            <h2 class="font-display text-card-title">{{ __('Your exams') }}</h2>
            @forelse ($exams as $exam)
                <a href="{{ route('exams.show', ['slug' => $exam->slug]) }}"
                   class="mt-2 flex items-center justify-between text-body hover:text-green">
                    <span>{{ $exam->name }}</span>
                    @if ($exam->pivot->target_date)
                        <span class="numeral text-meta text-muted">
                            {{ (int) today()->diffInDays($exam->pivot->target_date, false) }}d
                        </span>
                    @endif
                </a>
            @empty
                <p class="mt-2 text-body text-muted">{{ __('No exams followed yet.') }}</p>
                <a href="{{ route('exams.index') }}" class="mt-2 inline-block text-body text-green hover:underline">
                    {{ __('Browse exams') }}
                </a>
            @endforelse
        </div>

        <a href="{{ route('saved') }}" class="card flex items-center justify-between p-5 hover:border-green">
            <span class="text-body font-medium">{{ __('Saved jobs') }}</span>
            <span class="numeral font-semibold text-green">{{ $savedCount }}</span>
        </a>

        @unless ($user->hasCompletedProfile())
            {{-- Without a date of birth and a qualification the eligibility engine cannot
                 run at all, so this prompt is the highest-value thing on the page. --}}
            <div class="card border-marigold bg-marigold-wash p-5">
                <p class="text-body font-medium">{{ __('Finish your profile') }}</p>
                <p class="mt-1 text-body text-ink-soft">
                    {{ __('We need your age and qualification to tell you which jobs you qualify for.') }}
                </p>
                <a href="{{ route('profile.edit') }}" class="btn-primary mt-3 text-body">
                    {{ __('Add details — 30 seconds') }}
                </a>
            </div>
        @endunless

    </aside>
</div>

@endsection
