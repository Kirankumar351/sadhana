@extends('layouts.app')

@section('content')
<article class="mx-auto max-w-2xl">

    <nav class="text-meta text-muted">
        <a href="{{ route('notes.index') }}" class="hover:text-green">{{ __('Notes generator') }}</a>
    </nav>

    <h1 class="font-display text-screen-title mt-2">{{ $note->title }}</h1>

    <p class="mt-1 text-meta text-muted">
        {{ $note->topic }}
        @if ($note->paper) · {{ $note->paper }} @endif
        · {{ $note->created_at->translatedFormat('j F Y') }}
    </p>

    {{-- The snapshot rule, said out loud. A student revising from these needs to know that
         what they saved is what they will read, whatever changed behind it since. --}}
    <div class="mt-5 whitespace-pre-line text-body leading-relaxed">{{ $note->body }}</div>

    @if ($note->sources)
        <div class="mt-6 border-t border-ink/10 pt-3">
            <p class="text-meta font-semibold text-muted">{{ __('Built from') }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
                @foreach ($note->sources as $i => $source)
                    <span class="chip text-meta">
                        <span class="numeral text-muted">{{ $i + 1 }}</span> {{ $source }}
                    </span>
                @endforeach
            </div>
        </div>
    @endif

    <p class="mt-4 text-meta text-muted">
        {{ __('Always confirm dates and eligibility against the official notification.') }}
    </p>

    <div class="mt-6 flex flex-wrap items-center gap-3">
        <a href="{{ route('flashcards') }}" class="btn-secondary">{{ __('Make flashcards') }}</a>

        <form method="POST" action="{{ route('notes.destroy', ['note' => $note->id]) }}"
              onsubmit="return confirm('{{ __('Delete this note?') }}')">
            @csrf
            @method('DELETE')
            <button type="submit" class="text-body text-muted hover:text-danger">{{ __('Delete') }}</button>
        </form>
    </div>

</article>
@endsection
