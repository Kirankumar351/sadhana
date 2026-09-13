@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl">
    <h1 class="font-display text-screen-title">{{ __('Flashcards') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('The scheduler decides what you see today. Your job is only to answer.') }}
    </p>
</div>

<div class="mt-5">
    @livewire('learning.flashcard-review')
</div>
@endsection
