@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-sm">

    <h1 class="font-display text-display">{{ __('Enter the code') }}</h1>
    <p class="mt-2 text-body text-muted">
        {{ __('Sent to') }} <span class="numeral font-semibold text-ink">+91 {{ $phone }}</span>
    </p>

    <form method="POST" action="{{ route('otp.verify') }}" class="card mt-6 p-5">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">

        <label for="code" class="block text-body font-medium">{{ __('6-digit code') }}</label>

        {{-- One wide field rather than six boxes. Six boxes look neater and behave badly:
             paste breaks, autofill from the SMS breaks, and backspace jumps unpredictably
             on the Android keyboards this audience actually uses. --}}
        <input type="text" id="code" name="code"
               inputmode="numeric" autocomplete="one-time-code"
               maxlength="6" required autofocus
               class="numeral mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3
                      text-center text-2xl font-bold tracking-[0.4em]
                      focus:border-green focus:outline-none">

        @error('code')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
        @error('phone')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

        <button type="submit" class="btn-primary mt-4 w-full">{{ __('Verify and continue') }}</button>
    </form>

    <form method="POST" action="{{ route('otp.send') }}" class="mt-4 text-center">
        @csrf
        <input type="hidden" name="phone" value="{{ $phone }}">
        <button type="submit" class="text-body text-green hover:underline">{{ __('Send a new code') }}</button>
    </form>

    @if (app()->isLocal())
        {{-- Local only. The code is written to the log so the flow can be exercised
             without an SMS provider; this block is never rendered outside local. --}}
        <p class="mt-6 rounded-control bg-marigold-wash px-3 py-2 text-meta text-ink-soft">
            Local development: the code is in <code>storage/logs/laravel.log</code>.
        </p>
    @endif

</div>
@endsection
