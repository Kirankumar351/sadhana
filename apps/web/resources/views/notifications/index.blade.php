@extends('layouts.app')

@section('content')

<h1 class="font-display text-screen-title">{{ __('Job notifications') }}</h1>
<p class="mt-1 text-body text-muted">
    {{ trans_choice('{0} Nothing open right now|{1} :count open right now|[2,*] :count open right now', $notifications->total(), ['count' => $notifications->total()]) }}
</p>

{{-- Filter chips scroll horizontally and are never a dropdown on mobile: a dropdown
     hides the options, and hidden filters do not get used. --}}
<form method="GET" class="scroll-x mt-4" role="search">
    <button name="closing_soon" value="1"
            class="chip {{ !empty($filters['closing_soon']) ? 'chip-active' : '' }}">
        {{ __('Closing soon') }}
    </button>

    @foreach (['10th' => __('10th'), '12th' => __('Intermediate'), 'degree' => __('Degree'), 'btech' => __('B.Tech')] as $value => $label)
        <button name="qualification" value="{{ $value }}"
                class="chip {{ ($filters['qualification'] ?? null) === $value ? 'chip-active' : '' }}">
            {{ $label }}
        </button>
    @endforeach

    @foreach (['government' => __('Government'), 'private' => __('Private')] as $value => $label)
        <button name="job_type" value="{{ $value }}"
                class="chip {{ ($filters['job_type'] ?? null) === $value ? 'chip-active' : '' }}">
            {{ $label }}
        </button>
    @endforeach

    @if (array_filter($filters))
        <a href="{{ route('notifications.index') }}" class="chip text-danger">{{ __('Clear filters') }}</a>
    @endif
</form>

@guest
    <div class="mt-4 rounded-card border border-dashed border-ink/20 bg-white p-4">
        <p class="text-body font-medium">{{ __("We can't tell if you qualify") }}</p>
        <p class="mt-1 text-body text-muted">
            {{ __('Sign in and add your age, category and qualification once.') }}
        </p>
    </div>
@endguest

<div class="mt-4 grid gap-3">
    @forelse ($notifications as $n)
        <x-notification-card :n="$n" />
    @empty
        <div class="card p-8 text-center">
            <p class="text-body font-medium">{{ __('Nothing matches those filters') }}</p>
            <a href="{{ route('notifications.index') }}" class="mt-3 inline-block text-body text-green hover:underline">
                {{ __('Clear filters') }}
            </a>
        </div>
    @endforelse
</div>

<div class="mt-6">{{ $notifications->links() }}</div>

@endsection
