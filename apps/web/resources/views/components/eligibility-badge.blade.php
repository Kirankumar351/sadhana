@props(['eligibility'])

{{--
    The most important component in the product.

    Four states, and the fourth is deliberate: a logged-out user, or one who has not
    entered a date of birth, sees "add your details" rather than a badge. Showing
    "eligible" to someone whose age we do not know would be a guess about their career.
--}}
@php
    $status = $eligibility['status'] ?? 'unknown';
    $matched = count($eligibility['matched'] ?? []);
    $failed  = count($eligibility['failed'] ?? []);
    $total   = $matched + $failed;
@endphp

@if ($status === 'eligible')
    <span class="badge-eligible">✓ {{ __('You are eligible') }}</span>

@elseif ($status === 'partial')
    <span class="badge-partial">
        {{ __(':n of :t match', ['n' => $matched, 't' => $total]) }}
    </span>

@elseif ($status === 'not_eligible')
    <span class="badge-not-eligible">
        {{ $eligibility['failed'][0]['reason'] ?? __('Not eligible') }}
    </span>

@else
    <a href="{{ auth()->check() ? route('profile.edit') : '#' }}" class="badge-unknown">
        {{ __('Add your details') }}
    </a>
@endif
