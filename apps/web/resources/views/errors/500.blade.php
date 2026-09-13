@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md py-12 text-center">
    <h1 class="font-display text-screen-title">{{ __('Something broke on our side') }}</h1>

    {{-- Plainly ours, not theirs. And an explicit reassurance about the deadline, because
         on a notification day the first fear is that they have missed something. --}}
    <p class="mt-2 text-body text-muted">
        {{ __('This is our fault, not yours. We have been told about it automatically.') }}
    </p>
    <p class="mt-2 text-body text-muted">
        {{ __('Nothing you saved has been lost, and no deadline has changed.') }}
    </p>

    <a href="{{ route('home') }}" class="btn-primary mt-6">{{ __('Back to the home page') }}</a>
</div>
@endsection
