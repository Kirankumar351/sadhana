<div class="mx-auto max-w-2xl">

    <form wire:submit="ask" class="card p-4">
        <label for="ask" class="block text-body font-medium">
            {{ __('Ask about any exam, syllabus or notification') }}
        </label>

        <textarea id="ask" wire:model="question" rows="3"
                  placeholder="{{ __('e.g. What is in Paper IV of Group 2, and how much is it worth?') }}"
                  class="mt-2 w-full rounded-control border border-ink/20 px-3 py-2 text-body
                         focus:border-green focus:outline-none"></textarea>

        @error('question')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

        <div class="mt-3 flex items-center gap-3">
            <button type="submit" class="btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="ask">{{ __('Ask') }}</span>
                <span wire:loading wire:target="ask">{{ __('Searching our material…') }}</span>
            </button>

            @if ($asked)
                <button type="button" wire:click="reset_" class="text-body text-muted hover:text-ink">
                    {{ __('Clear') }}
                </button>
            @endif
        </div>

        {{-- Said plainly and up front. The constraint is the feature: answers come only
             from material we hold legally and have verified, which is what stops the
             assistant inventing an exam pattern that does not exist. --}}
        <p class="mt-3 text-meta text-muted">
            {{ __('Answers come only from Sadhana\'s own material — never from the open internet.') }}
        </p>
    </form>

    @if ($result)
        <div class="mt-4">

            {{-- ------------------------------------------------ a grounded answer --}}
            @if ($result->status === 'answered')
                <article class="card p-5">
                    <div class="flex items-center gap-2">
                        <span class="badge-ai">✦ {{ __('AI') }}</span>
                        <span class="text-meta text-muted">
                            {{ trans_choice('{1} grounded in :count source|[2,*] grounded in :count sources',
                                count($result->sources), ['count' => count($result->sources)]) }}
                        </span>
                        @if ($result->fromCache)
                            <span class="text-meta text-muted">· {{ __('cached') }}</span>
                        @endif
                    </div>

                    <div class="mt-3 whitespace-pre-line text-body leading-relaxed">{{ $result->text }}</div>

                    {{-- Source cards the student can actually open. A citation that cannot
                         be checked is not grounding, it is decoration. --}}
                    @if ($result->sources)
                        <div class="mt-4 border-t border-ink/10 pt-3">
                            <p class="text-meta font-semibold text-muted">{{ __('Sources used') }}</p>
                            <ol class="mt-2 grid gap-1">
                                @foreach ($result->sources as $i => $source)
                                    <li class="text-body">
                                        <span class="numeral text-muted">{{ $i + 1 }}.</span>
                                        @if ($source->url)
                                            <a href="{{ $source->url }}" class="text-green hover:underline">
                                                {{ $source->title ?? $source->sourceType }}
                                            </a>
                                        @else
                                            {{ $source->title ?? $source->sourceType }}
                                        @endif
                                    </li>
                                @endforeach
                            </ol>
                        </div>
                    @endif

                    <p class="mt-4 text-meta text-muted">
                        {{ __('Always confirm dates and eligibility against the official notification.') }}
                        · <button type="button" class="text-green hover:underline">{{ __('Report a wrong answer') }}</button>
                    </p>
                </article>

            {{-- ---------------------------- routed to the deterministic engine --}}
            @elseif ($result->status === 'handed_off')
                <article class="card border-green bg-green-wash p-5">
                    @if ($result->handler === 'eligibility_engine')
                        <p class="text-body font-medium">{{ __('We can answer that exactly, not by guessing.') }}</p>
                        <p class="mt-1 text-body text-ink-soft">
                            {{ __('Eligibility is checked against your own age, category and qualification — not estimated by a model.') }}
                        </p>
                        <a href="{{ auth()->check() ? route('profile.edit') : route('login') }}" class="btn-primary mt-3 text-body">
                            {{ auth()->check() ? __('Check my eligibility') : __('Sign in to check') }}
                        </a>
                    @else
                        {{-- A refused cutoff prediction always offers the real history
                             instead. A refusal is only useful if it hands over something
                             better than the guess. --}}
                        <p class="text-body font-medium">{{ __('We do not predict cutoffs.') }}</p>
                        <p class="mt-1 text-body text-ink-soft">
                            {{ __('A cutoff depends on the vacancy count, the paper difficulty and how many people sat the exam. A guess would make you plan wrongly. Here is what actually happened in previous years.') }}
                        </p>
                        <a href="{{ route('exams.index') }}" class="btn-secondary mt-3 text-body">
                            {{ __('See real cutoffs by year and category') }}
                        </a>
                    @endif
                </article>

            {{-- ------------------------------ nothing retrievable / low confidence --}}
            @elseif ($result->shouldOfferCommunity())
                <article class="card p-5">
                    {{-- An outage is not a content gap. Saying "I do not have material on this"
                         when the provider or the vector store is down tells the student the
                         question was the problem, and they stop asking. --}}
                    @if ($result->status === 'unavailable')
                        <p class="text-body font-medium">{{ __('The assistant is not available right now.') }}</p>
                        <p class="mt-1 text-body text-ink-soft">
                            {{ __('Please try again in a few minutes. Everything else on Sadhana keeps working.') }}
                        </p>
                    @else
                        <p class="text-body font-medium">
                            {{ $result->status === 'low_confidence'
                                ? __('I am not confident about this one.')
                                : __('I do not have material on this yet.') }}
                        </p>
                        <p class="mt-1 text-body text-ink-soft">
                            {{ __('I would rather tell you that than guess. Someone who has actually written this exam will answer better.') }}
                        </p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" wire:click="reset_" class="btn-secondary text-body">
                            {{ __('Try rewording it') }}
                        </button>
                        <a href="{{ route('exams.index') }}" class="btn-primary text-body">{{ __('Browse exams') }}</a>
                    </div>
                </article>

            {{-- ------------------------------------------------------ daily cap --}}
            @elseif ($result->status === 'cap_reached')
                <article class="card border-marigold bg-marigold-wash p-5">
                    <p class="text-body font-medium">{{ __('You have used today\'s questions') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('The limit exists so the assistant stays free for everyone. It resets at midnight.') }}
                    </p>
                    <p class="numeral mt-2 text-meta text-muted">
                        {{ $result->used }}/{{ $result->limit }} · {{ __('resets') }} {{ $result->resetsAt?->diffForHumans() }}
                    </p>
                    <p class="mt-2 text-body text-ink-soft">
                        {{ __('Everything else keeps working — notes, flashcards and the quiz are not capped.') }}
                    </p>
                </article>

            {{-- -------------------------------------------------- guard fired --}}
            @else
                <article class="card p-5">
                    <p class="text-body font-medium">{{ __('I cannot answer that one.') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('Ask the community, or browse the exam pages — they hold the verified facts.') }}
                    </p>
                </article>
            @endif
        </div>
    @endif

</div>
