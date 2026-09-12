@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    <h1 class="font-display text-screen-title">రోజు ప్రశ్న — {{ __("Today's questions") }}</h1>

    @if (! $quiz)
        <div class="card mt-6 p-8 text-center">
            <p class="text-body font-medium">{{ __("Today's quiz is being prepared.") }}</p>
            <p class="mt-1 text-body text-muted">{{ __('It goes live at 7 AM every morning.') }}</p>
        </div>

    @elseif ($existing)
        {{--
            One attempt per day, deliberately. A retry would make the streak meaningless
            and turn the leaderboard into a test of persistence rather than knowledge.
        --}}
        <div class="card mt-6 p-6 text-center">
            <p class="text-body text-muted">{{ __('You have already taken the quiz today.') }}</p>
            <p class="numeral mt-2 font-display text-display text-green">
                {{ $existing->correctCount() }}/{{ (int) $existing->total_marks }}
            </p>
            <a href="{{ route('quiz.result', ['attempt' => $existing->id]) }}" class="btn-primary mt-4">
                {{ __('See your answers') }}
            </a>
        </div>

    @else
        @auth
            @livewire('quiz.daily-quiz', ['quiz' => $quiz])
        @else
            <div class="card mt-6 p-6 text-center">
                <p class="text-body">
                    <span class="numeral font-semibold">{{ $questions->count() }}</span>
                    {{ __('questions ready') }}
                </p>
                <p class="mt-2 text-body text-muted">
                    {{ __('Sign in to take the quiz and keep your streak.') }}
                </p>
                <a href="{{ route('login') }}" class="btn-primary mt-4">{{ __('Sign in') }}</a>
            </div>
        @endauth
    @endif

</div>
@endsection
