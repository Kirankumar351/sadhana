<div
    x-data="{
        /**
         * Selection handling lives in Alpine rather than on the server, because a round
         * trip on every text selection would make the page feel broken on a 3G connection.
         * Only the explain request goes to the server.
         */
        selected: '',
        show: false,
        capture() {
            const text = window.getSelection().toString().trim();
            if (text.length >= 20) { this.selected = text; this.show = true; }
        },
    }"
    @mouseup.window="capture()"
    @touchend.window="capture()"
>
    {{-- Floating action, only once something worth explaining is selected. --}}
    <div x-show="show && !$wire.result" x-cloak
         class="fixed inset-x-4 bottom-24 z-30 md:inset-x-auto md:left-1/2 md:-translate-x-1/2">
        <button type="button"
                @click="$wire.explain(selected); show = false"
                class="btn-primary w-full shadow-lg md:w-auto">
            ✦ {{ __('Explain this simpler') }}
        </button>
    </div>

    @if ($result)
        <div class="card mt-4 border-ai-border bg-ai-wash/50 p-5">
            <div class="flex items-center gap-2">
                <span class="badge-ai">✦ {{ __('AI') }}</span>
                <span class="text-meta text-muted">
                    {{ $simpler ? __('Even simpler') : __('In plain language') }}
                    @if ($result->fromCache) · {{ __('cached') }} @endif
                </span>
            </div>

            @if ($result->isAnswer())
                <div class="mt-3 whitespace-pre-line text-body leading-relaxed">{{ $result->text }}</div>

                <div class="mt-4 flex flex-wrap gap-2">
                    @unless ($simpler)
                        <button type="button" wire:click="evenSimpler" class="btn-secondary text-body">
                            {{ __('Even simpler') }}
                        </button>
                    @endunless
                    <button type="button" wire:click="$set('result', null)" class="btn-secondary text-body">
                        {{ __('Close') }}
                    </button>
                </div>

                {{-- Rewrites only; adds nothing. Saying so is what lets a student trust it
                     enough to rely on the rewrite instead of re-reading the original. --}}
                <p class="mt-3 text-meta text-muted">
                    {{ __('This only rewrites the passage you selected. It never adds new facts.') }}
                </p>
            @else
                <p class="mt-3 text-body text-ink-soft">
                    {{ __('I could not explain that one. Try selecting a full sentence or two.') }}
                </p>
            @endif
        </div>
    @endif
</div>
