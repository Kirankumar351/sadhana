@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md py-12 text-center">
    <h1 class="font-display text-screen-title">{{ __('Slow down a moment') }}</h1>
    <p class="mt-2 text-body text-muted">
        {{ __('Too many requests from this device. Wait a minute and try again.') }}
    </p>
</div>
@endsection
