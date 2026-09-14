@extends('layouts.app')

@section('content')
<article class="max-w-reading">
    <nav class="text-meta text-muted">
        <a href="{{ route('material.index') }}" class="hover:underline">{{ __('Material') }}</a>
        @if ($material->exam)<span aria-hidden="true">/</span> {{ $material->exam->name }}@endif
    </nav>

    <h1 class="mt-2 font-display text-display leading-tight">{{ $material->title }}</h1>

    @if ($material->description)
        <p class="mt-3 text-body text-ink-soft">{{ $material->description }}</p>
    @endif

    {{-- AI-assisted material carries the editor's name publicly. Readers deserve to know,
         and a named editor creates real accountability. --}}
    @if ($material->source_type === 'ai_assisted')
        <p class="mt-4 rounded-control bg-ai-wash px-3 py-2 text-meta text-ai-ink">
            ✦ {{ __('Written with AI assistance and edited by :name before publishing.', ['name' => $material->editor?->name ?? __('our content team')]) }}
        </p>
    @endif

    <div class="mt-5 flex flex-wrap gap-3">
        @if ($material->file_path)
            <a href="{{ route('material.download', ['slug' => $material->slug]) }}" class="btn-primary">
                {{ __('Download PDF') }}
            </a>
        @endif
    </div>

    {{--
        The mobile-readable version is the DEFAULT where one exists.

        A 40 MB scan is unusable on a 2 GB phone and invisible to Google. The HTML twin is
        faster, searchable, and indexable — the PDF is the fallback, not the other way round.
    --}}
    {{-- Select any sentence to have it rewritten in plainer Telugu. Most quality
         preparation material in India is in dense English, and a Telugu-medium
         graduate loses hours decoding it before they can start learning. --}}
    @livewire('ai.explain-simpler', ['materialId' => $material->id])

    @if ($material->html_content)
        <div class="prose mt-8 max-w-none text-body leading-relaxed text-ink-soft">
            {!! $material->html_content !!}
        </div>
    @endif

    <p class="mt-8 text-meta text-muted">
        {{ __('Think this breaches someone\'s copyright?') }}
        <a href="#" class="text-green hover:underline">{{ __('Report it') }}</a>
        — {{ __('we remove first and discuss after.') }}
    </p>
</article>
@endsection
