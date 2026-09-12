@extends('layouts.app')

{{--
    The exam hub page — the SEO engine.

    The sections below appear in a FIXED ORDER on every exam page. Consistency is what
    teaches Google what the template means; a page whose structure varies from its siblings
    ranks for nothing in particular.
--}}

@section('content')
<article class="max-w-reading">

    <nav class="text-meta text-muted">
        <a href="{{ route('exams.index') }}" class="hover:underline">{{ __('Exams') }}</a>
        <span aria-hidden="true">/</span> {{ $exam->category?->name }}
    </nav>

    {{-- 1. What the exam is --}}
    <h1 class="mt-2 font-display text-display leading-tight">
        {{ $exam->name }} <span class="numeral">{{ date('Y') }}</span>
    </h1>
    <p class="mt-3 text-body text-ink-soft">{{ $exam->description }}</p>

    {{-- 2. Status right now --}}
    @php $latest = $exam->notifications->first(); @endphp
    <section class="card mt-6 p-5 {{ $latest ? 'bg-green-wash' : '' }}">
        <h2 class="font-display text-card-title">{{ __('Latest notification') }}</h2>
        @if ($latest)
            <a href="{{ route('notifications.show', ['slug' => $latest->slug]) }}"
               class="mt-2 block text-card-title text-green hover:underline">{{ $latest->title }}</a>
            @if ($latest->apply_end_date)
                <p class="mt-1 text-body">
                    {{ __('Last date') }}:
                    <time class="numeral font-semibold {{ $latest->isUrgent() ? 'text-danger' : '' }}">
                        {{ $latest->apply_end_date->format('d M Y') }}
                    </time>
                </p>
            @endif
        @else
            <p class="mt-2 text-body text-muted">
                {{ __('No notification is open right now. We will alert you the day it is released.') }}
            </p>
        @endif
    </section>

    {{-- 3. Eligibility --}}
    @if ($exam->eligibility_summary)
        <section class="mt-8">
            <h2 class="font-display text-screen-title">{{ __('Eligibility') }}</h2>
            <div class="mt-3 text-body text-ink-soft">{!! $exam->eligibility_summary !!}</div>
        </section>
    @endif

    {{-- 4. Exam pattern --}}
    @if ($exam->exam_pattern)
        <section class="mt-8">
            <h2 class="font-display text-screen-title">{{ __('Exam pattern') }}</h2>
            <div class="mt-3 overflow-x-auto text-body text-ink-soft">{!! $exam->exam_pattern !!}</div>
        </section>
    @endif

    {{-- 5. Syllabus, expandable so the page is scannable on a small screen --}}
    @php $sections = $exam->syllabusSections(); @endphp
    @if ($sections)
        <section class="mt-8">
            <h2 class="font-display text-screen-title">{{ __('Syllabus') }}</h2>
            <div class="mt-3 grid gap-2">
                @foreach ($sections as $section)
                    <details class="card p-4">
                        <summary class="cursor-pointer text-card-title">{{ $section['title'] ?? '' }}</summary>
                        <div class="mt-2 text-body text-ink-soft">
                            @foreach ($section['topics'] ?? [] as $topic)
                                <p>• {{ $topic }}</p>
                            @endforeach
                        </div>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

    {{-- 7. Cutoffs: real history, never a prediction. A cutoff depends on vacancy count,
           paper difficulty and turnout, so a confident guess makes someone plan wrongly. --}}
    @if ($exam->cutoffs->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-display text-screen-title">{{ __('Previous cutoffs') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-body">
                    <thead class="text-meta uppercase text-muted">
                        <tr class="border-b border-ink/10">
                            <th class="py-2 text-start">{{ __('Year') }}</th>
                            <th class="py-2 text-start">{{ __('Category') }}</th>
                            <th class="py-2 text-end">{{ __('Cutoff') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($exam->cutoffs as $cutoff)
                            <tr class="border-b border-ink/5">
                                <td class="numeral py-2">{{ $cutoff->year }}</td>
                                <td class="py-2">{{ strtoupper($cutoff->category) }}</td>
                                <td class="numeral py-2 text-end font-semibold">{{ $cutoff->cutoff_marks }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    {{-- 8. Free material --}}
    @if ($exam->materials->isNotEmpty())
        <section class="mt-8">
            <h2 class="font-display text-screen-title">{{ __('Free study material') }}</h2>
            <ul class="mt-3 grid gap-2">
                @foreach ($exam->materials as $material)
                    <li class="card p-4 text-card-title">{{ $material->title }}</li>
                @endforeach
            </ul>
        </section>
    @endif

    {{-- 11. FAQ, marked up as schema.org FAQPage — this is what wins rich results --}}
    @if ($faq)
        <section class="mt-8">
            <h2 class="font-display text-screen-title">{{ __('Frequently asked questions') }}</h2>
            <div class="mt-3 grid gap-2">
                @foreach ($faq as $item)
                    <details class="card p-4">
                        <summary class="cursor-pointer text-card-title">{{ $item['q'] ?? '' }}</summary>
                        <p class="mt-2 text-body text-ink-soft">{{ $item['a'] ?? '' }}</p>
                    </details>
                @endforeach
            </div>
        </section>
    @endif

</article>
@endsection
