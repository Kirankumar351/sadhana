@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    {{-- Score first, because it is what they came for. Everything below it is what
         actually helps. --}}
    <div class="card bg-green-wash p-6 text-center">
        <p class="text-body text-ink-soft">{{ $result->test->title }}</p>
        <p class="numeral mt-1 font-display text-5xl font-extrabold text-green">
            {{ $result->score }}<span class="text-2xl text-ink-soft">/{{ (int) $result->test->total_marks }}</span>
        </p>

        @if ($result->percentile !== null)
            <div class="mt-3 flex justify-center gap-6 text-body">
                <span>
                    <span class="numeral font-bold">{{ $result->percentile }}</span>
                    <span class="text-muted">{{ __('percentile') }}</span>
                </span>
                <span>
                    <span class="text-muted">{{ __('Rank') }}</span>
                    <span class="numeral font-bold">{{ $result->rank }}</span>
                    <span class="text-muted">/ <span class="numeral">{{ $result->total_attempts }}</span></span>
                </span>
            </div>
        @endif
    </div>

    {{--
        THE WEAK AREAS ARE THE PRODUCT.

        A student already knows roughly how they did. What they cannot work out alone is
        which topic is quietly costing them marks — and this is the moment they are most
        willing to act on being told.
    --}}
    @if ($result->weak_areas)
        <div class="card mt-4 border-marigold bg-marigold-wash p-5">
            <h2 class="font-display text-card-title">{{ __('Study these next') }}</h2>
            <ul class="mt-2 grid gap-1 text-body">
                @foreach ($result->weak_areas as $topic)
                    <li>
                        <span class="font-medium">{{ $topic }}</span>
                        <span class="numeral text-muted">— {{ $result->topic_strength[$topic] ?? 0 }}%</span>
                    </li>
                @endforeach
            </ul>
            {{-- Routes back into free material, never into a paywall. --}}
            <a href="{{ route('material.index') }}" class="btn-secondary mt-3 text-body">
                {{ __('Find free material on these') }}
            </a>
        </div>
    @endif

    {{-- Section breakdown. Skipped is shown separately from wrong on purpose: under
         negative marking, choosing not to answer is a legitimate strategy, and collapsing
         the two would hide whether a student is guessing or judging. --}}
    @if ($result->section_breakdown)
        <section class="mt-6">
            <h2 class="font-display text-screen-title">{{ __('Section by section') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-body">
                    <thead class="text-meta uppercase text-muted">
                        <tr class="border-b border-ink/10">
                            <th class="py-2 text-start">{{ __('Section') }}</th>
                            <th class="py-2 text-end">{{ __('Correct') }}</th>
                            <th class="py-2 text-end">{{ __('Wrong') }}</th>
                            <th class="py-2 text-end">{{ __('Skipped') }}</th>
                            <th class="py-2 text-end">{{ __('Score') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->section_breakdown as $section)
                            <tr class="border-b border-ink/5">
                                <td class="py-2">{{ $section['name'] }}</td>
                                <td class="numeral py-2 text-end text-green">{{ $section['correct'] }}</td>
                                <td class="numeral py-2 text-end text-danger">{{ $section['wrong'] }}</td>
                                <td class="numeral py-2 text-end text-muted">{{ $section['skipped'] }}</td>
                                <td class="numeral py-2 text-end font-semibold">{{ $section['score'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- Topic strength as bars rather than numbers alone. On a small screen a student
         should be able to see the shape of their weakness without reading a table. --}}
    @if ($result->topic_strength)
        <section class="mt-6">
            <h2 class="font-display text-screen-title">{{ __('Topic strength') }}</h2>
            <div class="mt-3 grid gap-2">
                @foreach ($result->topic_strength as $topic => $percent)
                    <div>
                        <div class="flex items-baseline justify-between text-body">
                            <span>{{ $topic }}</span>
                            <span class="numeral font-semibold">{{ $percent }}%</span>
                        </div>
                        <div class="mt-1 h-2 overflow-hidden rounded-pill bg-ink/10">
                            <div class="h-full rounded-pill {{ $percent < 50 ? 'bg-danger' : ($percent < 75 ? 'bg-marigold' : 'bg-green') }}"
                                 style="width: {{ $percent }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    @if ($top = data_get($result->comparison_to_top, 'top_10_percent_average'))
        <p class="mt-6 rounded-control bg-paper px-3 py-2 text-body text-muted">
            {{ __('The top 10% averaged') }}
            <span class="numeral font-semibold text-ink">{{ $top }}</span>
            {{ __('on this paper.') }}
        </p>
    @endif

</div>
@endsection
