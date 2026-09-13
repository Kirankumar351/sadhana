@extends('layouts.app')

@section('content')
<div class="flex items-start justify-between gap-4">
    <div>
        <h1 class="font-display text-screen-title">{{ __('Doubts') }}</h1>
        <p class="mt-1 text-body text-muted">
            {{ __('Ask in Telugu. Answered by people who have written the exam.') }}
        </p>
    </div>

    @auth
        <a href="{{ route('community.create') }}" class="btn-primary shrink-0">{{ __('Ask a doubt') }}</a>
    @endauth
</div>

{{-- Unanswered is a first-class filter, not buried. A question nobody answered is the one
     failing the person who asked it. --}}
<div class="scroll-x mt-4">
    <a href="{{ route('community.index') }}"
       class="chip {{ $filter === '' ? 'chip-active' : '' }}">{{ __('Newest') }}</a>
    <a href="{{ route('community.index', ['filter' => 'unanswered']) }}"
       class="chip {{ $filter === 'unanswered' ? 'chip-active' : '' }}">{{ __('Unanswered') }}</a>
    <a href="{{ route('community.index', ['filter' => 'top']) }}"
       class="chip {{ $filter === 'top' ? 'chip-active' : '' }}">{{ __('Most helpful') }}</a>
</div>

<div class="mt-4 grid gap-3">
    @forelse ($posts as $post)
        <article class="card p-4">
            <div class="flex items-start justify-between gap-3">
                <h2 class="text-card-title">
                    <a href="{{ route('community.show', ['slug' => $post->slug]) }}" class="hover:text-green hover:underline">
                        {{ $post->title }}
                    </a>
                </h2>

                <span class="shrink-0 {{ $post->answer_count > 0 ? 'badge-eligible' : 'badge-unknown' }}">
                    <span class="numeral">{{ $post->answer_count }}</span>
                    {{ trans_choice('{0} answers|{1} answer|[2,*] answers', $post->answer_count) }}
                </span>
            </div>

            <p class="mt-2 line-clamp-2 text-body text-muted">{{ Str::limit(strip_tags($post->body), 160) }}</p>

            <div class="mt-3 flex flex-wrap items-center gap-3 text-meta text-muted">
                <x-user-badge :user="$post->user" />
                @if ($post->exam)
                    <span>· {{ $post->exam->name }}</span>
                @endif
                @if ($post->subject)
                    <span>· {{ $post->subject }}</span>
                @endif
                <span>· {{ $post->created_at->diffForHumans() }}</span>
            </div>
        </article>
    @empty
        <div class="card p-8 text-center">
            <p class="text-body font-medium">{{ __('No doubts here yet') }}</p>
            <p class="mt-1 text-body text-muted">{{ __('Be the first to ask.') }}</p>
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $posts->links() }}</div>
@endsection
