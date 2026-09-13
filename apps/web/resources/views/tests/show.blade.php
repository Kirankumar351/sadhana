@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    <nav class="text-meta text-muted">
        <a href="{{ route('tests.index') }}" class="hover:underline">{{ __('Mock tests') }}</a>
        @if ($series->exam)<span aria-hidden="true">/</span> {{ $series->exam->name }}@endif
    </nav>

    <h1 class="mt-2 font-display text-display leading-tight">{{ $series->title }}</h1>
    <p class="mt-2 text-body text-ink-soft">{{ $series->description }}</p>

    <div class="mt-6 grid gap-3">
        @foreach ($series->tests as $test)
            @php
                // The first mock is free even inside a paid series: people buy what they
                // have tried. Gating the sample loses the sale and the trust together.
                $unlocked = $hasFullAccess || $test->is_free_sample || $loop->first;
                $result = $attempts[$test->id] ?? null;
            @endphp

            <article class="card flex items-center justify-between gap-4 p-4 {{ $unlocked ? '' : 'opacity-70' }}">
                <div class="min-w-0">
                    <h2 class="text-card-title">{{ $test->title }}</h2>
                    <p class="mt-1 text-meta text-muted">
                        <span class="numeral">{{ $test->duration_min }}</span> {{ __('minutes') }}
                        · <span class="numeral">{{ (int) $test->total_marks }}</span> {{ __('marks') }}
                        @if ($test->negative_marking > 0)
                            · <span class="text-danger">−<span class="numeral">{{ $test->negative_marking }}</span> {{ __('per wrong answer') }}</span>
                        @endif
                    </p>
                </div>

                <div class="shrink-0 text-end">
                    @if ($result)
                        {{-- Once taken, the score and the analysis matter more than the
                             button. The analysis is the actual product here. --}}
                        <p class="numeral font-display text-lg font-bold text-green">
                            {{ $result->score }}<span class="text-muted">/{{ (int) $test->total_marks }}</span>
                        </p>
                        @if ($result->percentile !== null)
                            <p class="numeral text-meta text-muted">{{ $result->percentile }}{{ __('th percentile') }}</p>
                        @endif
                        <a href="{{ route('tests.result', ['result' => $result->id]) }}"
                           class="mt-1 block text-meta text-green hover:underline">{{ __('See analysis') }}</a>
                    @elseif ($unlocked)
                        <a href="#" class="btn-primary text-body">{{ __('Start') }}</a>
                    @else
                        <a href="{{ route('billing.plans') }}" class="btn-secondary text-body">🔒 {{ __('Unlock') }}</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    @unless ($hasFullAccess)
        <div class="card mt-6 border-marigold bg-marigold-wash p-5">
            <p class="text-body font-semibold">{{ __('Want the full series?') }}</p>
            <p class="mt-1 text-body text-ink-soft">
                {{ __('Every mock, plus percentile, rank and topic-wise analysis against the top 10%.') }}
            </p>
            <a href="{{ route('billing.plans') }}" class="btn-primary mt-3 text-body">{{ __('See plans') }}</a>
        </div>
    @endunless

</div>
@endsection
