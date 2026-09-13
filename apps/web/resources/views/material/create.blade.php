@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-screen-title">{{ __('Share your notes') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('Your handwritten notes help the next person preparing. You get credit on the page.') }}
    </p>

    @livewire('material.upload-notes')
</div>
@endsection
