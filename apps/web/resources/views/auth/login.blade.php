@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-sm">

    <h1 class="font-display text-display">{{ __('Sign in') }}</h1>
    <p class="mt-2 text-body text-muted">
        {{ __('No password. We send a code to your phone.') }}
    </p>

    <form method="POST" action="{{ route('otp.send') }}" class="card mt-6 p-5">
        @csrf

        <label for="phone" class="block text-body font-medium">{{ __('Phone number') }}</label>

        <div class="mt-1 flex items-stretch overflow-hidden rounded-control border border-ink/20 focus-within:border-green">
            {{-- Country code is fixed, not a dropdown. Every user of this product is in
                 India, and a dropdown is one more thing to get wrong on a small screen. --}}
            <span class="numeral grid min-h-tap place-items-center bg-paper px-3 text-body text-muted">+91</span>
            <input type="tel" id="phone" name="phone" inputmode="numeric" autocomplete="tel"
                   maxlength="10" required autofocus
                   value="{{ old('phone') }}"
                   placeholder="9876543210"
                   class="numeral min-h-tap w-full px-3 text-body focus:outline-none">
        </div>

        @error('phone')
            <p class="mt-1 text-meta text-danger">{{ $message }}</p>
        @enderror

        <button type="submit" class="btn-primary mt-4 w-full">{{ __('Send code') }}</button>

        <p class="mt-4 text-meta text-muted">
            {{ __('By continuing you agree to our terms and privacy notice. You must be 18 or older.') }}
        </p>
    </form>

    <a href="{{ route('notifications.index') }}"
       class="mt-4 block text-center text-body text-green hover:underline">
        {{ __('Look around first') }}
    </a>

</div>
@endsection
