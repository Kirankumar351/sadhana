<div class="grid gap-5 lg:grid-cols-[1fr_20rem]">

    <div>
        {{-- ------------------------------------------------------------ the form --}}
        <form wire:submit="generate" class="card p-4">
            <div class="grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="paper" class="block text-meta font-medium text-muted">{{ __('Paper') }}</label>
                    <select id="paper" wire:model.live="paper"
                            class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                        <option value="">{{ __('Any paper') }}</option>
                        @foreach (array_keys($this->syllabus) as $paperName)
                            <option value="{{ $paperName }}">{{ $paperName }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="topic" class="block text-meta font-medium text-muted">
                        {{ __('Topic') }}
                        <span class="font-normal">— {{ __('from the structured syllabus') }}</span>
                    </label>

                    {{-- Picked, not typed. Every topic here is actually on the paper, which
                         is what keeps the corpus able to answer it. A free-text box invites
                         questions our material was never meant to cover, and the refusal
                         reads as the product being broken rather than the question being wrong. --}}
                    <select id="topic" wire:model.live="topic"
                            class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                        <option value="">{{ __('Choose a topic') }}</option>
                        @foreach ($this->topics as $syllabusTopic)
                            <option value="{{ $syllabusTopic }}">{{ $syllabusTopic }}</option>
                        @endforeach
                    </select>
                    @error('topic')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="depth" class="block text-meta font-medium text-muted">{{ __('Depth') }}</label>
                    <select id="depth" wire:model="depth"
                            class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                        <option value="exam_focused">{{ __('Exam-focused — facts and dates') }}</option>
                        <option value="detailed">{{ __('Detailed — with context') }}</option>
                        <option value="revision">{{ __('Revision — one-liners only') }}</option>
                    </select>
                </div>

                <div>
                    <label for="language" class="block text-meta font-medium text-muted">{{ __('Language') }}</label>
                    <select id="language" wire:model="language"
                            class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                        <option value="te">{{ __('Telugu') }}</option>
                        <option value="en">{{ __('English') }}</option>
                        <option value="both">{{ __('Both, side by side') }}</option>
                    </select>
                </div>
            </div>

            {{-- Said before the generation is spent, not after. A thin topic produces thin
                 notes, and knowing that is more useful than receiving them. --}}
            @if ($this->thinCoverage)
                <p class="mt-3 rounded-control bg-marigold-wash px-3 py-2 text-meta text-ink-soft">
                    {{ __('We hold little indexed material on this topic, so the notes will be thin. It is on our list to fill.') }}
                </p>
            @endif

            <button type="submit" class="btn-primary mt-4 w-full" wire:loading.attr="disabled" wire:target="generate">
                <span wire:loading.remove wire:target="generate">✦ {{ __('Generate notes') }}</span>
                <span wire:loading wire:target="generate">{{ __('Reading our material…') }}</span>
            </button>
        </form>

        {{-- ---------------------------------------------------------- the notes --}}
        @if ($result)
            @if ($result->status === 'answered')
                <article class="card mt-4 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="badge-ai">
                            ✦ {{ trans_choice('{1} AI · generated from :count source|[2,*] AI · generated from :count sources',
                                count($result->sources), ['count' => count($result->sources)]) }}
                        </span>

                        <div class="flex gap-2">
                            @if ($savedNoteId)
                                <span class="text-meta text-green">✓ {{ __('Saved') }}</span>
                            @else
                                <button type="button" wire:click="save" class="btn-secondary text-meta">
                                    {{ __('Save to my notes') }}
                                </button>
                            @endif
                        </div>
                    </div>

                    <div class="mt-4 whitespace-pre-line text-body leading-relaxed">{{ $result->text }}</div>

                    @if ($result->sources)
                        <div class="mt-5 border-t border-ink/10 pt-3">
                            <p class="text-meta font-semibold text-muted">{{ __('Built from') }}</p>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                @foreach ($result->sources as $i => $source)
                                    <span class="chip text-meta">
                                        <span class="numeral text-muted">{{ $i + 1 }}</span>
                                        {{ $source->title ?? $source->sourceType }}
                                    </span>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        <a href="{{ route('flashcards') }}" class="btn-secondary text-meta">{{ __('Make flashcards') }}</a>
                        <a href="{{ route('quiz.today') }}" class="btn-secondary text-meta">{{ __('Practice questions on this') }}</a>
                        <a href="{{ route('ask') }}" class="btn-secondary text-meta">{{ __('Report a mistake') }}</a>
                    </div>

                    <p class="mt-4 text-meta text-muted">
                        {{ __('Always confirm dates and eligibility against the official notification.') }}
                    </p>
                </article>

            {{-- The honest failure. Padding a thin corpus into confident notes is the one
                 outcome worse than saying we do not have the material. --}}
            @elseif ($result->status === 'no_sources' || $result->status === 'low_confidence')
                <article class="card mt-4 p-5">
                    <p class="text-body font-medium">{{ __('We do not hold enough material on this topic yet.') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('Rather than pad it out, we would rather say so. This topic is now flagged for our content team.') }}
                    </p>
                    <a href="{{ route('material.index') }}" class="btn-secondary mt-3 text-body">{{ __('Browse the material library') }}</a>
                </article>

            @elseif ($result->status === 'cap_reached')
                <article class="card mt-4 border-marigold bg-marigold-wash p-5">
                    <p class="text-body font-medium">{{ __('You have used this month’s notes') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('The limit exists so generation stays free for everyone. Your saved notes are not affected.') }}
                    </p>
                    <p class="numeral mt-2 text-meta text-muted">
                        {{ $result->used }}/{{ $result->limit }} · {{ __('resets') }} {{ $result->resetsAt?->diffForHumans() }}
                    </p>
                </article>

            @else
                <article class="card mt-4 p-5">
                    <p class="text-body font-medium">{{ __('We could not build notes on that just now.') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('The material library and the exam pages still hold the verified facts.') }}
                    </p>
                </article>
            @endif
        @endif
    </div>

    {{-- ------------------------------------------------------------- sidebar --}}
    <aside class="grid content-start gap-4">
        <section class="card p-4">
            <h2 class="text-card-title">{{ __('Why it is grounded') }}</h2>
            <p class="mt-1 text-meta leading-relaxed text-muted">
                {{ __('Notes are assembled from our own syllabus, past papers and approved material — never from a coaching book and never from open-web scraping. That keeps them legal and keeps them accurate to the actual paper.') }}
            </p>
        </section>

        <section class="card p-4">
            <h2 class="text-card-title">{{ __('Your generated notes') }}</h2>
            <dl class="mt-2 grid gap-1 text-body">
                <div class="flex justify-between">
                    <dt class="text-muted">{{ __('Saved') }}</dt>
                    <dd class="numeral font-semibold">{{ $this->saved->count() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-muted">{{ __('This month') }}</dt>
                    <dd class="numeral font-semibold">{{ $this->usage['used'] }} {{ __('of') }} {{ $this->usage['limit'] }}</dd>
                </div>
            </dl>

            @if ($this->saved->isNotEmpty())
                <ul class="mt-3 grid gap-2 border-t border-ink/10 pt-3">
                    @foreach ($this->saved as $note)
                        <li class="text-body">
                            <a href="{{ route('notes.show', ['note' => $note->id]) }}" class="hover:text-green">
                                {{ $note->title }}
                            </a>
                            <span class="block text-meta text-muted">{{ $note->created_at->diffForHumans() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </aside>
</div>
