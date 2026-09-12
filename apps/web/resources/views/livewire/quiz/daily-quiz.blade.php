<div class="mt-5">

    @php
        $question = $this->current;
        $total = $this->questions->count();
        $answered = count($answers);
    @endphp

    {{-- Progress. Shows how many are answered, not just position, so a user who skipped
         one knows there is something to come back to. --}}
    <div class="flex items-center justify-between text-meta text-muted">
        <span class="numeral">{{ __('Question') }} {{ $index + 1 }} / {{ $total }}</span>
        <span class="numeral">{{ $answered }}/{{ $total }} {{ __('answered') }}</span>
    </div>

    <div class="mt-2 h-1.5 overflow-hidden rounded-pill bg-ink/10">
        <div class="h-full rounded-pill bg-green transition-all duration-300"
             style="width: {{ $total ? round(($index + 1) / $total * 100) : 0 }}%"></div>
    </div>

    @if ($question)
        <article class="card mt-4 p-5">

            <div class="flex items-start justify-between gap-3">
                <span class="text-meta text-muted">
                    {{ $question->subject ?? __('General') }}
                    @if ($question->is_current_affairs)
                        · {{ __('Current affairs') }}
                    @endif
                </span>

                {{-- Per-question toggle. A Telugu-medium student often knows a technical
                     term only in English; flipping one question is faster than switching
                     the whole interface and losing their place. --}}
                <button type="button" wire:click="toggleLanguage"
                        class="shrink-0 rounded-pill border border-ink/15 px-2.5 py-1 text-meta font-semibold text-ink-soft">
                    {{ $this->locale === 'te' ? 'English' : 'తెలుగు' }}
                </button>
            </div>

            <h2 class="mt-3 text-card-title leading-relaxed {{ $this->locale === 'te' ? 'font-telugu' : '' }}">
                {{ $question->getTranslation('question', $this->locale, useFallbackLocale: true) }}
            </h2>

            <div class="mt-4 grid gap-2" role="radiogroup">
                @foreach ($question->optionsFor($this->locale) as $i => $option)
                    @php $chosen = ($answers[$question->id] ?? null) === $i; @endphp
                    <button type="button"
                            wire:click="choose({{ $question->id }}, {{ $i }})"
                            role="radio"
                            aria-checked="{{ $chosen ? 'true' : 'false' }}"
                            class="flex min-h-tap w-full items-center gap-3 rounded-control border px-4 py-3 text-start text-body transition
                                   {{ $chosen
                                        ? 'border-green bg-green-wash font-semibold text-green'
                                        : 'border-ink/15 bg-white hover:border-ink/30' }}">
                        <span class="grid h-6 w-6 shrink-0 place-items-center rounded-pill border text-meta font-semibold
                                     {{ $chosen ? 'border-green bg-green text-white' : 'border-ink/25 text-muted' }}">
                            {{ chr(65 + $i) }}
                        </span>
                        <span class="{{ $this->locale === 'te' ? 'font-telugu' : '' }}">{{ $option }}</span>
                    </button>
                @endforeach
            </div>
        </article>

        {{-- Navigation --}}
        <div class="mt-4 flex items-center gap-3">
            <button type="button" wire:click="previous" @disabled($index === 0)
                    class="btn-secondary flex-1 disabled:opacity-40">
                {{ __('Back') }}
            </button>

            @if ($index < $total - 1)
                <button type="button" wire:click="next" class="btn-primary flex-1">{{ __('Next') }}</button>
            @else
                <button type="button" wire:click="submit" wire:loading.attr="disabled"
                        class="btn-primary flex-1">
                    <span wire:loading.remove wire:target="submit">{{ __('Submit') }}</span>
                    <span wire:loading wire:target="submit">{{ __('Checking…') }}</span>
                </button>
            @endif
        </div>

        {{-- Question dots. Lets a user jump back to one they skipped without stepping
             through every screen between. --}}
        <div class="mt-4 flex flex-wrap justify-center gap-1.5">
            @foreach ($this->questions as $i => $q)
                <button type="button" wire:click="jumpTo({{ $i }})"
                        aria-label="{{ __('Question') }} {{ $i + 1 }}"
                        class="numeral h-8 w-8 rounded-control text-meta font-semibold transition
                               {{ $i === $index
                                    ? 'bg-ink text-white'
                                    : (isset($answers[$q->id]) ? 'bg-green-wash text-green' : 'bg-ink/5 text-muted') }}">
                    {{ $i + 1 }}
                </button>
            @endforeach
        </div>

        @if ($answered < $total)
            <p class="mt-3 text-center text-meta text-muted">
                {{ __('You can submit with unanswered questions — they count as wrong.') }}
            </p>
        @endif
    @endif

</div>
