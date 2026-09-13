@extends('layouts.app')

@section('content')
<h1 class="font-display text-screen-title">{{ __('Mock tests') }}</h1>
<p class="mt-1 max-w-reading text-body text-muted">
    {{ __('Full-length papers matching the real pattern and timing, with topic-wise analysis afterwards.') }}
</p>

{{-- The free tier is not a crippled demo, and saying so up front is the point. A student
     can prepare seriously without paying; what is paid is the rest of the series and the
     deeper analysis. --}}
<p class="mt-3 rounded-control bg-green-wash px-3 py-2 text-body text-green">
    {{ __('The first mock in every series is free, along with the daily quiz.') }}
</p>

<div class="mt-6 grid gap-4 md:grid-cols-2">
    @forelse ($series as $item)
        <article class="card p-5">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-card-title">
                    <a href="{{ route('tests.show', ['slug' => $item->slug]) }}" class="hover:text-green hover:underline">
                        {{ $item->title }}
                    </a>
                </h2>
                @if ($item->is_free)
                    <span class="badge-eligible shrink-0">{{ __('Free') }}</span>
                @endif
            </div>

            @if ($item->exam)
                <p class="mt-1 text-meta text-muted">{{ $item->exam->name }}</p>
            @endif

            <p class="mt-2 text-body text-ink-soft">{{ $item->description }}</p>

            <p class="mt-3 text-meta text-muted">
                <span class="numeral">{{ $item->tests->count() }}</span>
                {{ trans_choice('{0} tests|{1} test|[2,*] tests', $item->tests->count()) }}
                @unless ($item->is_free)
                    · <span class="numeral">₹{{ number_format(($item->price_paise ?? 0) / 100) }}</span>
                @endunless
            </p>
        </article>
    @empty
        <p class="card p-8 text-center text-body text-muted md:col-span-2">
            {{ __('Test series are being prepared.') }}
        </p>
    @endforelse
</div>
@endsection
