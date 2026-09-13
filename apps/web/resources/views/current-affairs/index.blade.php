@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    <h1 class="font-display text-screen-title">{{ __('Current affairs') }}</h1>
    <p class="mt-1 text-body text-muted">
        <time class="numeral">{{ $readableDate }}</time>
        · {{ __('Filtered to what these exams actually ask.') }}
    </p>

    {{-- Every item was checked by a person before publishing. Saying so plainly is the
         difference between a digest people trust and one they cross-check elsewhere. --}}
    <p class="mt-3 rounded-control bg-green-wash px-3 py-2 text-meta text-green">
        {{ __('AI drafts the summaries. A content editor verifies every fact against the original source before it appears here.') }}
    </p>

    <div class="mt-5 grid gap-4">
        @forelse ($items as $item)
            <article class="card p-5">
                <div class="flex flex-wrap items-center gap-2 text-meta">
                    @if ($item->probability === 'high')
                        <span class="badge-eligible">{{ __('High exam probability') }}</span>
                    @elseif ($item->probability === 'medium')
                        <span class="badge-partial">{{ __('Medium probability') }}</span>
                    @endif
                    <span class="text-muted">{{ $item->source_name }}</span>
                </div>

                <h2 class="mt-2 text-card-title leading-relaxed">{{ $item->title }}</h2>

                <p class="mt-2 whitespace-pre-line text-body text-ink-soft">{{ $item->summary }}</p>

                {{-- The exam angle is the actual value. A news summary without it is just
                     news, and these students have no time for news. --}}
                @if ($item->why_it_matters)
                    <div class="mt-3 rounded-control bg-paper p-3">
                        <p class="text-meta font-semibold text-muted">{{ __('Why it matters for the exam') }}</p>
                        <p class="mt-1 text-body text-ink-soft">{{ $item->why_it_matters }}</p>
                    </div>
                @endif

                {{-- Two independent sources for every factual claim, enforced in data.
                     Shown so a reader can check us rather than take our word. --}}
                @if ($item->sources->isNotEmpty())
                    <div class="mt-3 flex flex-wrap gap-3 text-meta">
                        @foreach ($item->sources as $source)
                            <a href="{{ $source->source_url }}" rel="nofollow noopener" target="_blank"
                               class="text-green hover:underline">↗ {{ $source->source_name }}</a>
                        @endforeach
                    </div>
                @endif
            </article>
        @empty
            <div class="card p-8 text-center">
                <p class="text-body font-medium">{{ __('No digest published yet') }}</p>
                <p class="mt-1 text-body text-muted">{{ __('It goes live every morning at 6:20.') }}</p>
            </div>
        @endforelse
    </div>

    {{-- Each day keeps a permanent URL. This is the most SEO-valuable page type in the
         product: daily fresh Telugu content on high-volume queries, and an archive that
         accumulates rather than being overwritten. --}}
    @if ($archive->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-display text-card-title">{{ __('Earlier days') }}</h2>
            <div class="scroll-x mt-3">
                @foreach ($archive as $day)
                    @php $dayDate = \Illuminate\Support\Carbon::parse($day); @endphp
                    <a href="{{ route('current-affairs.show', ['date' => $dayDate->toDateString()]) }}"
                       class="chip numeral {{ $dayDate->toDateString() === $date ? 'chip-active' : '' }}">
                        {{ $dayDate->format('d M') }}
                    </a>
                @endforeach
            </div>
        </section>
    @endif

</div>
@endsection
