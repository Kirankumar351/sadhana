@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-display">{{ __('Ask Sadhana') }}</h1>
    <p class="mt-2 text-body text-muted">
        {{ __('Any question about an exam, a syllabus, a notification or a cutoff — answered from our own material, with the sources shown.') }}
    </p>

    <div class="mt-6">
        @livewire('ai.ask-sadhana')
    </div>
</div>
@endsection
