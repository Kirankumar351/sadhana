@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    <form method="GET" action="{{ route('search') }}" role="search">
        <label for="q" class="sr-only">{{ __('Search') }}</label>
        <div class="flex items-stretch overflow-hidden rounded-control border border-ink/20 focus-within:border-green">
            <input type="search" id="q" name="q" value="{{ $query }}" autofocus
                   placeholder="{{ __('Exam, notification, syllabus, doubt…') }}"
                   class="min-h-tap w-full px-3 text-body focus:outline-none">
            <button type="submit" class="tap bg-green px-5 font-semibold text-white">{{ __('Search') }}</button>
        </div>
    </form>

    @if ($query === '')
        {{-- An empty search box is a dead end. Offering the things people actually search
             for turns it into a starting point. --}}
        <div class="mt-6">
            <p class="text-meta font-semibold text-muted">{{ __('People usually search for') }}</p>
            <div class="scroll-x mt-2">
                @foreach (['TGPSC Group 2', 'Police Constable', 'SSC CGL', 'DSC', 'RRB NTPC', 'Polity'] as $suggestion)
                    <a href="{{ route('search', ['q' => $suggestion]) }}" class="chip">{{ $suggestion }}</a>
                @endforeach
            </div>
        </div>

    @else
        @php
            $total = $notifications->count() + $exams->count() + $materials->count() + $doubts->count();
        @endphp

        @if ($total === 0)
            <div class="card mt-6 p-8 text-center">
                <p class="text-body font-medium">{{ __('Nothing found for ":q"', ['q' => $query]) }}</p>
                <p class="mt-2 text-body text-muted">
                    {{ __('Try a shorter phrase, or the exam name on its own.') }}
                </p>

                {{-- Two honest exits rather than a dead end. The assistant may find it in
                     material that did not match on keywords, and the community can answer
                     what we simply do not hold. --}}
                <div class="mt-4 grid gap-2">
                    <a href="{{ route('ask', ['q' => $query]) }}" class="btn-primary">
                        {{ __('Ask Sadhana instead') }}
                    </a>
                    <a href="{{ route('community.index') }}" class="btn-secondary">
                        {{ __('Ask the community') }}
                    </a>
                </div>
            </div>
        @else
            <p class="mt-4 text-meta text-muted">
                <span class="numeral">{{ $total }}</span>
                {{ trans_choice('{1} result|[2,*] results', $total) }}
                {{ __('for') }} "{{ $query }}"
            </p>

            {{-- Grouped by type, not blended by score. A blended list looks smarter and is
                 harder to scan: someone after a syllabus wants the exam pages together. --}}

            @if ($exams->isNotEmpty())
                <section class="mt-6">
                    <h2 class="font-display text-card-title">{{ __('Exams') }}</h2>
                    <div class="mt-2 grid gap-2">
                        @foreach ($exams as $exam)
                            <a href="{{ route('exams.show', ['slug' => $exam->slug]) }}"
                               class="card flex items-center justify-between p-4 hover:border-green">
                                <span>
                                    <span class="block text-card-title">{{ $exam->name }}</span>
                                    <span class="block text-meta text-muted">{{ $exam->conducting_body }}</span>
                                </span>
                                <span class="text-muted" aria-hidden="true">›</span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($notifications->isNotEmpty())
                <section class="mt-6">
                    <h2 class="font-display text-card-title">{{ __('Notifications') }}</h2>
                    <div class="mt-2 grid gap-2">
                        @foreach ($notifications as $n)
                            <a href="{{ route('notifications.show', ['slug' => $n->slug]) }}"
                               class="card block p-4 hover:border-green">
                                <span class="block text-card-title">{{ $n->title }}</span>
                                <span class="mt-1 block text-meta text-muted">
                                    {{ $n->organisation }}
                                    @if ($n->apply_end_date)
                                        · {{ __('Last date') }}
                                        <time class="numeral {{ $n->isUrgent() ? 'text-danger' : '' }}">
                                            {{ $n->apply_end_date->format('d M Y') }}
                                        </time>
                                    @endif
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($materials->isNotEmpty())
                <section class="mt-6">
                    <h2 class="font-display text-card-title">{{ __('Study material') }}</h2>
                    <div class="mt-2 grid gap-2">
                        @foreach ($materials as $material)
                            <a href="{{ route('material.show', ['slug' => $material->slug]) }}"
                               class="card block p-4 hover:border-green">
                                <span class="block text-card-title">{{ $material->title }}</span>
                                <span class="mt-1 block text-meta text-muted">
                                    <span class="numeral">{{ number_format($material->download_count) }}</span>
                                    {{ __('downloads') }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif

            @if ($doubts->isNotEmpty())
                <section class="mt-6">
                    <h2 class="font-display text-card-title">{{ __('Answered doubts') }}</h2>
                    <div class="mt-2 grid gap-2">
                        @foreach ($doubts as $doubt)
                            <a href="{{ route('community.show', ['slug' => $doubt->slug]) }}"
                               class="card block p-4 hover:border-green">
                                <span class="block text-card-title">{{ $doubt->title }}</span>
                                <span class="mt-1 block text-meta text-muted">
                                    <span class="numeral">{{ $doubt->answer_count }}</span>
                                    {{ trans_choice('{1} answer|[2,*] answers', $doubt->answer_count) }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </section>
            @endif
        @endif
    @endif

</div>
@endsection
