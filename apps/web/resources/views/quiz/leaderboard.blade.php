@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">
    <h1 class="font-display text-screen-title">{{ __('Longest streaks this week') }}</h1>
    <p class="mt-1 text-body text-muted">{{ __('Resets every Monday.') }}</p>

    <ol class="card mt-4 divide-y divide-ink/10">
        @forelse ($rows as $i => $row)
            @php $isMe = auth()->id() === $row['user_id']; @endphp

            {{-- The viewer's own row is washed marigold so they can find themselves
                 without reading every name. --}}
            <li class="flex items-center gap-3 px-4 py-3 {{ $isMe ? 'bg-marigold-wash' : '' }}">
                <span class="numeral w-8 text-body font-semibold text-muted">{{ $i + 1 }}</span>
                <span class="flex-1 text-body {{ $isMe ? 'font-semibold' : '' }}">
                    {{ $users[$row['user_id']]->name ?? __('Aspirant') }}
                </span>
                <span class="numeral text-body font-semibold text-marigold">🔥 {{ $row['streak'] }}</span>
            </li>
        @empty
            <li class="p-8 text-center text-body text-muted">
                {{ __('No streaks yet this week. Be the first.') }}
            </li>
        @endforelse
    </ol>
</div>
@endsection
