<div class="mx-auto max-w-xl">

    {{-- Deck picker. Due counts rather than totals, because the only number that decides
         what to do next is how many are waiting. --}}
    @if ($this->decks->isNotEmpty())
        <div class="scroll-x mb-4">
            <button type="button" wire:click="selectDeck(null)"
                    class="chip {{ $deck === null ? 'chip-active' : '' }}">
                {{ __('All decks') }}
            </button>

            @foreach ($this->decks as $d)
                <button type="button" wire:click="selectDeck('{{ $d->deck }}')"
                        class="chip {{ $deck === $d->deck ? 'chip-active' : '' }}">
                    {{ $d->deck }}
                    @if ($d->due > 0)
                        <span class="numeral ml-1 rounded-pill bg-marigold px-1.5 text-ink">{{ $d->due }}</span>
                    @endif
                </button>
            @endforeach
        </div>
    @endif

    @if ($card === null)
        <div class="card p-8 text-center">
            @if ($reviewed > 0)
                {{-- Finishing a session is the moment to stop, not to offer more. Spaced
                     repetition works because the scheduler decides the volume, and a
                     "keep going" button here would undo exactly that. --}}
                <p class="text-4xl" aria-hidden="true">✓</p>
                <p class="mt-3 text-body font-semibold">
                    {{ trans_choice('{1} :count card done today|[2,*] :count cards done today', $reviewed, ['count' => $reviewed]) }}
                </p>
                <p class="mt-1 text-body text-muted">
                    {{ __('Nothing else is due. Come back tomorrow — that is how spaced repetition works.') }}
                </p>
            @else
                <p class="text-body font-medium">{{ __('No cards due today') }}</p>
                <p class="mt-1 text-body text-muted">
                    {{ __('Cards appear here from your wrong quiz answers and from any topic you turn into a deck.') }}
                </p>
                <a href="{{ route('quiz.today', ['locale' => app()->getLocale()]) }}" class="btn-primary mt-4">{{ __("Today's quiz") }}</a>
            @endif
        </div>

    @else
        <div class="flex items-center justify-between text-meta text-muted">
            <span class="numeral">{{ $index + 1 }} / {{ $total }}</span>
            <span>{{ $card->deck }}</span>
        </div>

        <div class="mt-2 h-1.5 overflow-hidden rounded-pill bg-ink/10">
            <div class="h-full rounded-pill bg-green transition-all"
                 style="width: {{ $total ? round(($index) / $total * 100) : 0 }}%"></div>
        </div>

        <article class="card mt-4 min-h-[16rem] p-6">
            <p class="text-meta text-muted">{{ __('Question') }}</p>
            <p class="mt-2 text-card-title leading-relaxed">
                {{ $card->front[app()->getLocale()] ?? reset($card->front) }}
            </p>

            @if ($revealed)
                <div class="mt-5 border-t border-ink/10 pt-4">
                    <p class="text-meta text-muted">{{ __('Answer') }}</p>
                    <p class="mt-2 text-card-title leading-relaxed text-green">
                        {{ $card->back[app()->getLocale()] ?? reset($card->back) }}
                    </p>
                </div>
            @endif

            {{-- A card the student keeps forgetting is flagged, because knowing "this one
                 keeps catching me" is itself useful information. --}}
            @if ($card->lapse_count >= 3)
                <p class="mt-4 rounded-control bg-marigold-wash px-3 py-2 text-meta text-ink-soft">
                    {{ __('You have forgotten this one :count times. It may be worth reading the topic again rather than drilling it.', ['count' => $card->lapse_count]) }}
                </p>
            @endif
        </article>

        @if (! $revealed)
            <button type="button" wire:click="reveal" class="btn-primary mt-4 w-full">
                {{ __('Show answer') }}
            </button>
        @else
            {{-- Four grades, phrased as how it felt rather than as a 0-5 scale. Nobody
                 answers a numeric self-rating honestly in the half-second after seeing
                 the answer. --}}
            <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4">
                <button type="button" wire:click="grade('forgot')"
                        class="tap flex-col rounded-control border border-danger/40 py-2 text-danger">
                    <span class="text-body font-semibold">{{ __('Forgot') }}</span>
                    <span class="text-meta opacity-70">{{ __('again today') }}</span>
                </button>

                <button type="button" wire:click="grade('hard')"
                        class="tap flex-col rounded-control border border-marigold py-2 text-ink-soft">
                    <span class="text-body font-semibold">{{ __('Hard') }}</span>
                    <span class="text-meta opacity-70">{{ __('soon') }}</span>
                </button>

                <button type="button" wire:click="grade('good')"
                        class="tap flex-col rounded-control border border-green py-2 text-green">
                    <span class="text-body font-semibold">{{ __('Good') }}</span>
                    <span class="text-meta opacity-70">{{ __('later') }}</span>
                </button>

                <button type="button" wire:click="grade('easy')"
                        class="tap flex-col rounded-control border border-green bg-green-wash py-2 text-green">
                    <span class="text-body font-semibold">{{ __('Easy') }}</span>
                    <span class="text-meta opacity-70">{{ __('much later') }}</span>
                </button>
            </div>
        @endif
    @endif

</div>
