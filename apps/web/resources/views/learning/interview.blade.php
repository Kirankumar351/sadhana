@extends('layouts.app')

@section('content')
<div class="mx-auto mb-5 max-w-2xl">
    <span class="badge-ai">✦ {{ __('Mock interview') }}</span>
    <h1 class="font-display text-screen-title mt-2">{{ __('Group 1 interview practice') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('Questions built from your own bio-data, in Telugu or English, as long as you want.') }}
    </p>
</div>

@livewire('ai.mock-interview')
@endsection
