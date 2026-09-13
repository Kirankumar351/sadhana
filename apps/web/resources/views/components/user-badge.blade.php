@props(['user', 'showReputation' => true])

{{--
    How a person is presented in the community.

    The verified badge is the single strongest trust signal in the product, so it is given
    real visual weight rather than a subtle grey tick. Someone who has actually cleared the
    exam answering your doubt is a different thing from a stranger with a high score, and
    the interface should say so.
--}}
<span class="inline-flex items-center gap-1.5 text-meta">
    <span class="font-medium text-ink-soft">{{ $user?->name ?? __('Aspirant') }}</span>

    @if ($user?->is_verified_selected)
        <span class="inline-flex items-center gap-1 rounded-pill bg-green-wash px-2 py-0.5 font-semibold text-green"
              title="{{ __('Verified — cleared this exam') }}">
            ✓ {{ $user->verified_selected_exam ?: __('Selected') }}
        </span>
    @endif

    @if ($showReputation && ($user?->reputation ?? 0) > 0)
        <span class="numeral text-muted">{{ number_format($user->reputation) }}</span>
    @endif
</span>
