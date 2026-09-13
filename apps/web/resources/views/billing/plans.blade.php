@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-3xl">

    <h1 class="font-display text-display">{{ __('Premium') }}</h1>

    {{-- The promise comes before the price. It is the actual product decision, and a
         visitor who does not believe the core is free will not believe anything else
         on this page either. --}}
    <p class="mt-2 max-w-reading text-body text-ink-soft">
        {{ __('Notifications, the eligibility check, exam pages, the daily quiz and official material are free forever. Premium is for people who want more than that.') }}
    </p>

    @if ($gatesOpen)
        {{--
            Honesty about the current posture.

            While gates are open, everything below is already available to everyone. Selling
            something the buyer would get anyway without saying so is the kind of thing
            people find out about, and they do not forgive it.
        --}}
        <div class="card mt-5 border-marigold bg-marigold-wash p-5">
            <p class="text-body font-semibold">{{ __('Everything here is free right now') }}</p>
            <p class="mt-1 text-body text-ink-soft">
                {{ __('We are in early access, so no feature is withheld. You can support the work by subscribing, and you will keep whatever you bought when limits do start applying — but you do not need to.') }}
            </p>
        </div>
    @endif

    @if ($errors->has('payment'))
        <p class="card mt-4 border-danger bg-danger/5 p-4 text-body text-danger">
            {{ $errors->first('payment') }}
        </p>
    @endif

    <div class="mt-6 grid gap-4 md:grid-cols-3">
        @foreach ($plans as $plan)
            @php
                $isCurrent = $currentPlan?->plan_id === $plan->id;
                $isFree = $plan->price_paise === 0;
            @endphp

            <article class="card flex flex-col p-5 {{ $isCurrent ? 'border-green bg-green-wash' : '' }}">
                <h2 class="font-display text-card-title">{{ $plan->name }}</h2>

                <p class="mt-2">
                    <span class="numeral font-display text-3xl font-extrabold">
                        ₹{{ number_format($plan->price_paise / 100) }}
                    </span>
                    @unless ($isFree)
                        <span class="text-body text-muted">/ {{ __($plan->period) }}</span>
                    @endunless
                </p>

                <ul class="mt-4 grid flex-1 gap-2 text-body">
                    @forelse ($plan->entitlements as $key)
                        <li class="flex items-start gap-2">
                            <span class="text-green" aria-hidden="true">✓</span>
                            <span>{{ config("commerce.entitlements.{$key}", $key) }}</span>
                        </li>
                    @empty
                        <li class="flex items-start gap-2 text-muted">
                            <span class="text-green" aria-hidden="true">✓</span>
                            <span>{{ __('Everything in the free core') }}</span>
                        </li>
                    @endforelse

                    @if ($plan->ai_credits_per_period > 0)
                        <li class="flex items-start gap-2">
                            <span class="text-green" aria-hidden="true">✓</span>
                            <span class="numeral">{{ number_format($plan->ai_credits_per_period) }}</span>
                            <span>{{ __('AI credits') }}</span>
                        </li>
                    @endif
                </ul>

                <div class="mt-5">
                    @if ($isCurrent)
                        <p class="rounded-control bg-white px-3 py-2 text-center text-body font-semibold text-green">
                            {{ __('Your current plan') }}
                        </p>
                    @elseif ($isFree)
                        <p class="text-center text-body text-muted">{{ __('Always free') }}</p>
                    @elseif (auth()->check())
                        <button type="button"
                                data-plan="{{ $plan->slug }}"
                                class="btn-primary w-full js-subscribe">
                            {{ __('Subscribe') }}
                        </button>
                    @else
                        <a href="{{ route('login') }}" class="btn-secondary w-full">{{ __('Sign in to subscribe') }}</a>
                    @endif
                </div>
            </article>
        @endforeach
    </div>

    {{-- Stated plainly, because both are real commitments and both are things people
         have been burned by elsewhere. --}}
    <div class="card mt-8 p-5">
        <h2 class="font-display text-card-title">{{ __('Two things we promise') }}</h2>
        <ul class="mt-3 grid gap-2 text-body text-ink-soft">
            <li>
                <strong class="text-ink">{{ __('No automatic renewal.') }}</strong>
                {{ __('We never charge you again without you asking. When it expires, it expires.') }}
            </li>
            <li>
                <strong class="text-ink">{{ __('Seven-day refund.') }}</strong>
                {{ __('If it is not useful, write to us within seven days and we return the money.') }}
            </li>
        </ul>
    </div>

</div>

@auth
<script>
    /**
     * Checkout is opened on the gateway's own surface. We never see a card number, which
     * is what keeps this application entirely outside PCI scope.
     */
    document.querySelectorAll('.js-subscribe').forEach((button) => {
        button.addEventListener('click', async () => {
            button.disabled = true;
            button.textContent = @json(__('Opening…'));

            try {
                const response = await fetch(@json(route('billing.checkout')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ plan: button.dataset.plan }),
                });

                const data = await response.json();

                if (!response.ok) throw new Error(data.error || 'Could not start checkout');

                // A fully discounted order is already fulfilled — skip the payment screen.
                if (data.complete) {
                    window.location.reload();
                    return;
                }

                const rzp = new Razorpay({
                    ...data.checkout,
                    handler: (result) => {
                        const url = new URL(@json(route('billing.callback')), window.location.origin);
                        url.searchParams.set('order', data.order);
                        Object.entries(result).forEach(([k, v]) => url.searchParams.set(k, v));
                        window.location.href = url.toString();
                    },
                });

                rzp.open();
            } catch (error) {
                alert(error.message);
            } finally {
                button.disabled = false;
                button.textContent = @json(__('Subscribe'));
            }
        });
    });
</script>
<script src="https://checkout.razorpay.com/v1/checkout.js" defer></script>
@endauth
@endsection
