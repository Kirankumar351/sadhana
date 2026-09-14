@extends('layouts.app')

@section('content')
<article class="max-w-reading">
    <nav class="text-meta text-muted">
        <a href="{{ route('community.index') }}" class="hover:underline">{{ __('Doubts') }}</a>
        @if ($post->exam)
            <span aria-hidden="true">/</span> {{ $post->exam->name }}
        @endif
    </nav>

    <h1 class="mt-2 font-display text-screen-title leading-snug">{{ $post->title }}</h1>

    <div class="mt-2 flex flex-wrap items-center gap-3 text-meta text-muted">
        <x-user-badge :user="$post->user" />
        <span>· {{ $post->created_at->diffForHumans() }}</span>
        <span>· <span class="numeral">{{ $post->view_count }}</span> {{ __('views') }}</span>
    </div>

    <div class="prose mt-4 whitespace-pre-line text-body text-ink-soft">{{ $post->body }}</div>

    {{-- Photo and voice: typing Telugu on a phone is painful, so a doubt is often a
         photograph of one line in a textbook. --}}
    @if ($post->image_path)
        <img src="{{ route('community.attachment', ['slug' => $post->slug, 'type' => 'image']) }}" alt="{{ __('Attached image') }}"
             class="mt-4 rounded-card border border-ink/10" loading="lazy">
    @endif

    @if ($post->audio_path)
        <audio controls src="{{ route('community.attachment', ['slug' => $post->slug, 'type' => 'audio']) }}" class="mt-4 w-full"></audio>
    @endif

    @livewire('community.answer-thread', ['post' => $post])
</article>
@endsection
