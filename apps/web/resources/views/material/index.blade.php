@extends('layouts.app')

@section('content')
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="font-display text-screen-title">{{ __('Study material') }}</h1>
        <p class="mt-1 max-w-reading text-body text-muted">
            {{ __('Official syllabus PDFs, previous papers, and notes shared by other aspirants. All free.') }}
        </p>
    </div>
    @auth
        <a href="{{ route('material.create') }}" class="btn-secondary shrink-0">{{ __('Share your notes') }}</a>
    @endauth
</div>

<div class="mt-4 grid gap-3">
    @forelse ($materials as $material)
        <article class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-card-title">
                    <a href="{{ route('material.show', ['slug' => $material->slug]) }}" class="hover:text-green hover:underline">
                        {{ $material->title }}
                    </a>
                </h2>

                {{-- Provenance is always visible. A reader deserves to know whether they
                     are looking at a government PDF, something we wrote, another
                     student's notes, or an AI-assisted draft a person edited. --}}
                <span class="shrink-0">
                    @switch($material->source_type)
                        @case('official')
                            <span class="badge-eligible">{{ __('Official') }}</span> @break
                        @case('ai_assisted')
                            <span class="badge-ai">✦ {{ __('AI-assisted, edited') }}</span> @break
                        @case('user_notes')
                            <span class="badge-partial">{{ __('Student notes') }}</span> @break
                        @default
                            <span class="badge-unknown">{{ __('Original') }}</span>
                    @endswitch
                </span>
            </div>

            <div class="mt-2 flex flex-wrap items-center gap-3 text-meta text-muted">
                @if ($material->exam)<span>{{ $material->exam->name }}</span>@endif
                @if ($material->subject)<span>· {{ $material->subject }}</span>@endif
                <span>· <span class="numeral">{{ number_format($material->download_count) }}</span> {{ __('downloads') }}</span>
                @if ($material->file_size_kb)
                    <span>· <span class="numeral">{{ number_format($material->file_size_kb / 1024, 1) }}</span> MB</span>
                @endif
            </div>
        </article>
    @empty
        <p class="card p-8 text-center text-body text-muted">{{ __('Material is being added.') }}</p>
    @endforelse
</div>

<div class="mt-6">{{ $materials->links() }}</div>
@endsection
