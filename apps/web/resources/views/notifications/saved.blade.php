@extends('layouts.app')

@section('content')
<h1 class="font-display text-screen-title">{{ __('Saved jobs') }}</h1>

<div class="mt-4 grid gap-3">
    @forelse ($notifications as $n)
        <x-notification-card :n="$n" />
    @empty
        <p class="card p-8 text-center text-body text-muted">
            {{ __('Nothing saved yet. Tap Save on any notification and we will remind you before the last date.') }}
        </p>
    @endforelse
</div>

<div class="mt-6">{{ $notifications->links() }}</div>
@endsection
