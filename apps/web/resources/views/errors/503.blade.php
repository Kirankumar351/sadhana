@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md py-12 text-center">
    <h1 class="font-display text-screen-title">{{ __('Back in a few minutes') }}</h1>

    {{-- Shown during a deploy and during the notification-day static fallback. Degraded is
         always better than absent, so this page exists to keep the promise visible rather
         than leaving a browser error. --}}
    <p class="mt-2 text-body text-muted">
        {{ __('We are updating the site. It will be back shortly — no deadline has changed.') }}
    </p>
</div>
@endsection
