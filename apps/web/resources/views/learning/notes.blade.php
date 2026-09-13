@extends('layouts.app')

@section('content')
<div class="mb-5">
    <span class="badge-ai">✦ {{ __('Notes generator') }}</span>
    <h1 class="font-display text-screen-title mt-2">{{ __('Make notes on any syllabus topic') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('Structured Telugu notes built from our own indexed material, not from the open internet.') }}
    </p>
</div>

@livewire('ai.notes-generator')
@endsection
