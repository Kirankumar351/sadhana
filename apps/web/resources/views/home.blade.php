@extends('layouts.app')

@section('content')

{{-- Hero. The promise is "find out if you actually qualify" — not a feature list.
     Eligibility is the one thing no competitor does, so it leads. --}}
<section class="grid gap-6 md:grid-cols-2 md:items-center">
    <div>
        <h1 class="font-display text-display text-ink">
            {{ __('Find out if you actually qualify.') }}
        </h1>
        <p class="mt-3 max-w-reading text-body text-ink-soft">
            {{ __('Every government and private job notification for Telangana and Andhra Pradesh, in Telugu, checked against your own age, category and qualification.') }}
        </p>

        <div class="mt-5 flex flex-wrap gap-3">
            <a href="{{ auth()->check() ? route('profile.edit') : route('notifications.index') }}" class="btn-primary">
                {{ __('Check my eligibility — free') }}
            </a>
            <a href="{{ route('notifications.index') }}" class="btn-secondary">{{ __('Browse all jobs') }}</a>
        </div>

        <dl class="mt-6 flex flex-wrap gap-6">
            <div>
                <dt class="text-meta text-muted">{{ __('Jobs open right now') }}</dt>
                <dd class="numeral font-display text-2xl font-extrabold text-green">{{ number_format($stats['open']) }}</dd>
            </div>
            <div>
                <dt class="text-meta text-muted">{{ __('Vacancies') }}</dt>
                <dd class="numeral font-display text-2xl font-extrabold text-green">{{ number_format($stats['vacancies']) }}</dd>
            </div>
        </dl>
    </div>

    {{-- The daily habit, above the fold. This is the retention engine, so it is not
         buried three taps deep. --}}
    <div class="card bg-marigold-wash p-5">
        <p class="font-display text-screen-title">{{ __('Question of the day') }}</p>
        <p class="mt-1 text-body text-ink-soft">
            {{ __('Ten free questions every morning at 7 AM, in Telugu and English.') }}
        </p>

        @if ($quiz)
            <a href="{{ route('quiz.today') }}" class="btn-accent mt-4 w-full">{{ __("Start today's quiz") }}</a>
        @else
            <p class="mt-4 rounded-control bg-white/70 px-3 py-2 text-body text-muted">
                {{ __("Today's quiz is being prepared. It goes live at 7 AM.") }}
            </p>
        @endif
    </div>
</section>

{{-- Latest notifications --}}
<section class="mt-10">
    <div class="flex items-baseline justify-between">
        <h2 class="font-display text-screen-title">{{ __('Latest notifications') }}</h2>
        <a href="{{ route('notifications.index') }}" class="text-body font-medium text-green hover:underline">
            {{ __('See all') }}
        </a>
    </div>

    <div class="mt-4 grid gap-3">
        @forelse ($latest as $n)
            <x-notification-card :n="$n" />
        @empty
            <p class="card p-6 text-center text-body text-muted">
                {{ __('No notifications yet. Check back shortly.') }}
            </p>
        @endforelse
    </div>
</section>

{{-- Popular exams — these are the SEO pages, linked from the highest-authority page
     on the site so they are crawled early and often. --}}
@if ($popularExams->isNotEmpty())
<section class="mt-10">
    <h2 class="font-display text-screen-title">{{ __('Popular exams') }}</h2>
    <div class="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($popularExams as $exam)
            <a href="{{ route('exams.show', ['slug' => $exam->slug]) }}"
               class="card flex items-center justify-between p-4 transition hover:border-green hover:bg-green-wash/40">
                <span class="text-card-title">{{ $exam->name }}</span>
                <span class="text-muted" aria-hidden="true">›</span>
            </a>
        @endforeach
    </div>
</section>
@endif

@endsection
