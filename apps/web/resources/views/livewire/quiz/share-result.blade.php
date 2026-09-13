<div class="mt-4">

    @php $attempt = $this->attempt; @endphp

    <button type="button" wire:click="$toggle('open')" class="btn-accent w-full">
        {{ __('Share my score') }}
    </button>

    @if ($open)
        <div class="card mt-3 p-5">

            {{--
                A preview of the card, rendered in the brand colours.

                Server-side image generation (1080×1920, WhatsApp status shape) is the next
                step and needs the Telugu font subset in place — rendering Telugu into a PNG
                with a missing font produces boxes, which would be worse than no image.
                Until then this previews the text that is actually shared.
            --}}
            <div class="mx-auto max-w-xs rounded-card bg-green p-6 text-center text-white">
                <p class="text-meta opacity-80">{{ __('Today\'s quiz') }}</p>

                <p class="numeral mt-2 font-display text-5xl font-extrabold">
                    {{ $attempt->correctCount() }}<span class="text-2xl opacity-70">/{{ (int) $attempt->total_marks }}</span>
                </p>

                @if ($streak = auth()->user()?->streak?->current_streak)
                    <p class="numeral mt-3 inline-block rounded-pill bg-white/20 px-3 py-1 text-body font-semibold">
                        🔥 {{ $streak }} {{ trans_choice('{1} day|[2,*] days', $streak) }}
                    </p>
                @endif

                <p class="mt-4 text-meta opacity-80">sadhana.study</p>
            </div>

            <div class="mt-4 rounded-control bg-paper p-3">
                <p class="whitespace-pre-line text-body text-ink-soft">{{ $this->shareText }}</p>
            </div>

            <a href="{{ $this->whatsAppUrl }}" target="_blank" rel="noopener"
               class="btn-primary mt-3 w-full">
                {{ __('Share on WhatsApp') }}
            </a>

            {{-- Nothing is posted on the user's behalf, and saying so removes the hesitation
                 that stops people tapping. --}}
            <p class="mt-2 text-center text-meta text-muted">
                {{ __('Opens WhatsApp so you can choose where to post. Nothing is sent automatically.') }}
            </p>
        </div>
    @endif

</div>
