@extends('layouts.app')

@section('content')
<div class="mb-5">
    <span class="badge-ai">✦ {{ __('Answer evaluation') }}</span>
    <h1 class="font-display text-screen-title mt-2">{{ __('Descriptive answer feedback') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('For Group 1 mains. Scored against a published rubric, with what was missing.') }}
    </p>
</div>

@livewire('ai.evaluate-answer')
@endsection
