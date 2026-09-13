@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-screen-title">{{ __('Ask a doubt') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('Someone who has written this exam will answer. Usually within a few hours.') }}
    </p>

    @livewire('community.ask-doubt')
</div>
@endsection
