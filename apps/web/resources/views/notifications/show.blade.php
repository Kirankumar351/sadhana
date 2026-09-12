@extends('layouts.app')

@section('content')

<article class="max-w-reading">

    <nav class="text-meta text-muted">
        <a href="{{ route('notifications.index') }}" class="hover:underline">{{ __('Notifications') }}</a>
        @if ($n->exam)
            <span aria-hidden="true">/</span>
            <a href="{{ route('exams.show', ['slug' => $n->exam->slug]) }}" class="hover:underline">{{ $n->exam->name }}</a>
        @endif
    </nav>

    <h1 class="mt-2 font-display text-display leading-tight">{{ $n->title }}</h1>
    <p class="mt-2 text-body text-ink-soft">{{ $n->organisation }}</p>

    {{-- Eligibility, with the criteria broken out.

         Showing WHICH criteria matched and which failed is the whole differentiator. A
         bare yes/no would be a worse product than a notice board, because a user cannot
         tell whether to trust it. --}}
    <section class="card mt-5 p-5">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-display text-screen-title">{{ __('Am I eligible?') }}</h2>
            <x-eligibility-badge :eligibility="$eligibility" />
        </div>

        @if ($eligibility['matched'] || $eligibility['failed'])
            <ul class="mt-4 grid gap-2">
                @foreach ($eligibility['matched'] as $item)
                    <li class="flex items-start gap-2 text-body">
                        <span class="text-green" aria-hidden="true">✓</span>
                        <span>{{ $item['label'] }}</span>
                    </li>
                @endforeach
                @foreach ($eligibility['failed'] as $item)
                    <li class="flex items-start gap-2 text-body">
                        <span class="text-danger" aria-hidden="true">✕</span>
                        <span>
                            <strong class="font-semibold">{{ $item['label'] }}</strong>
                            <span class="text-muted">— {{ $item['reason'] }}</span>
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif

        {{-- Legally required, and honest. An automated check is guidance, not a
             determination, and saying so protects the user as much as us. --}}
        <p class="mt-4 rounded-control bg-paper px-3 py-2 text-meta text-muted">
            {{ $eligibility['caveat'] }}
        </p>
    </section>

    {{-- Quick facts --}}
    <section class="mt-6">
        <h2 class="font-display text-screen-title">{{ __('Quick facts') }}</h2>
        <dl class="card mt-3 divide-y divide-ink/10">
            @php
                $facts = array_filter([
                    __('vacancies')      => $n->total_vacancies ? number_format($n->total_vacancies) : null,
                    __('Qualification')  => $n->min_qualification ? __($n->min_qualification) : null,
                    __('Age limit')      => $n->min_age ? $n->min_age.'–'.($n->max_age ?? '-') : null,
                    __('Apply start')    => $n->apply_start_date?->format('d M Y'),
                    __('Last date')      => $n->apply_end_date?->format('d M Y'),
                    __('Exam date')      => $n->exam_date?->format('d M Y'),
                ]);
            @endphp
            @foreach ($facts as $label => $value)
                <div class="flex justify-between gap-4 px-4 py-3">
                    <dt class="text-body text-muted">{{ $label }}</dt>
                    <dd class="numeral text-body font-semibold {{ $label === __('Last date') && $n->isUrgent() ? 'text-danger' : '' }}">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    </section>

    @if ($n->description)
        <section class="mt-6">
            <h2 class="font-display text-screen-title">{{ __('Details') }}</h2>
            <div class="prose mt-3 text-body text-ink-soft">{!! $n->description !!}</div>
        </section>
    @endif

    {{-- Always link the official source. Non-negotiable for trust: we are an index of
         official information, never a replacement for it. --}}
    <section class="card mt-6 bg-green-wash p-5">
        <h2 class="font-display text-card-title">{{ __('Official source') }}</h2>
        <div class="mt-3 flex flex-wrap gap-2">
            @if ($n->official_pdf_url)
                <a href="{{ $n->official_pdf_url }}" rel="nofollow noopener" target="_blank" class="btn-secondary text-body">
                    {{ __('Official notification PDF') }} ↗
                </a>
            @endif
            @if ($n->apply_url)
                <a href="{{ $n->apply_url }}" rel="nofollow noopener" target="_blank" class="btn-primary text-body">
                    {{ __('Apply now') }} ↗
                </a>
            @endif
        </div>
        @if ($n->verified_at)
            <p class="mt-3 text-meta text-muted">
                {{ __('Last verified') }}: <time class="numeral">{{ $n->verified_at->format('d M Y') }}</time>
            </p>
        @endif
    </section>

    <p class="mt-4 text-meta text-muted">
        {{ __('Something wrong on this page?') }}
        <a href="#" class="text-green hover:underline">{{ __('Report an error') }}</a>
    </p>

</article>

@endsection
