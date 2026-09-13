@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-md py-12 text-center">
    <p class="numeral font-display text-5xl font-extrabold text-muted">404</p>

    <h1 class="mt-3 font-display text-screen-title">{{ __('This page is not here') }}</h1>

    {{-- Named the most likely cause rather than a generic apology. An expired notification
         is by far the commonest way someone lands here — a WhatsApp forward of a job that
         closed last month — and saying so answers the real question. --}}
    <p class="mt-2 text-body text-muted">
        {{ __('It may have been an old notification that has since closed, or a link that was typed slightly wrong.') }}
    </p>

    <div class="mt-6 grid gap-2">
        <a href="{{ route('notifications.index') }}" class="btn-primary">{{ __('See open jobs') }}</a>
        <a href="{{ route('exams.index') }}" class="btn-secondary">{{ __('Browse exams') }}</a>
    </div>
</div>
@endsection
