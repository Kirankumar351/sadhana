<div class="mx-auto max-w-2xl">

    {{-- The honest framing, up front. A real board reads composure and a file; this reads
         neither and must not imply otherwise. --}}
    <div class="card border-marigold bg-marigold-wash p-4">
        <p class="text-body">
            <strong>{{ __('Practice, not simulation.') }}</strong>
            {{ __('A real board reads your body language, your composure and your file. This gives you repetitions on the questions your bio-data invites — which is the part you can actually prepare.') }}
        </p>
    </div>

    @if ($error)
        <p class="mt-3 rounded-control bg-danger/10 px-3 py-2 text-body text-danger">{{ $error }}</p>
    @endif

    @if ($this->session === null)
        {{-- ------------------------------------------------------- bio-data --}}
        <form wire:submit="start" class="card mt-4 p-4">
            <h2 class="text-card-title">{{ __('Your bio-data — the board will ask from this') }}</h2>

            <div class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="district" class="block text-meta font-medium text-muted">{{ __('Home district') }}</label>
                    <input id="district" wire:model="district" type="text"
                           class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                    @error('district')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="graduation" class="block text-meta font-medium text-muted">{{ __('Graduation subject') }}</label>
                    <input id="graduation" wire:model="graduation" type="text"
                           class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                    @error('graduation')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label for="hobbies" class="block text-meta font-medium text-muted">
                        {{ __('Hobbies as written in the form') }}
                    </label>
                    <input id="hobbies" wire:model="hobbies" type="text"
                           class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                </div>

                <div>
                    <label for="optional" class="block text-meta font-medium text-muted">{{ __('Optional subject') }}</label>
                    <input id="optional" wire:model="optional" type="text"
                           class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                </div>
            </div>

            <div class="mt-3">
                <label for="language" class="block text-meta font-medium text-muted">{{ __('Interview language') }}</label>
                <select id="language" wire:model="language"
                        class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                    <option value="te">{{ __('Telugu') }}</option>
                    <option value="en">{{ __('English') }}</option>
                    <option value="mixed">{{ __('Mixed — as I would actually answer') }}</option>
                </select>
            </div>

            {{-- Written on the form is what matters: a board asks about the hobby you wrote
                 down, not the one you have. --}}
            <p class="mt-3 text-meta text-muted">
                {{ __('Fill this in exactly as it appears on your application. The board reads that form, not this one.') }}
            </p>

            <button type="submit" class="btn-primary mt-4 w-full" wire:loading.attr="disabled" wire:target="start">
                <span wire:loading.remove wire:target="start">✦ {{ __('Start a 20-minute mock') }}</span>
                <span wire:loading wire:target="start">{{ __('The board is reading your form…') }}</span>
            </button>
        </form>

    @else
        {{-- ------------------------------------------------------ the board --}}
        <div class="mt-4 grid gap-4">
            @foreach ($this->turns as $turn)
                <div class="card p-4">
                    <span class="chip text-meta">
                        {{ __('Board member :number', ['number' => $turn->board_member]) }}
                        @if ($turn->turn_index === 0) · {{ __('warm-up') }} @endif
                    </span>
                    <p class="mt-2 text-body leading-relaxed">{{ $turn->question }}</p>
                </div>

                @if ($turn->answer)
                    <div class="ms-8 rounded-control bg-green-wash p-3">
                        <p class="text-body leading-relaxed">{{ $turn->answer }}</p>
                    </div>

                    @if ($turn->feedback)
                        <article class="ms-8 card p-4">
                            <span class="badge-ai">✦ {{ __('Feedback on your last answer') }}</span>
                            <ul class="mt-2 grid gap-1">
                                @foreach ($turn->feedback as $note)
                                    <li class="flex gap-2 text-body">
                                        <span class="{{ ($note['kind'] ?? 'warn') === 'good' ? 'text-green' : 'text-marigold-ink' }}"
                                              aria-hidden="true">{{ ($note['kind'] ?? 'warn') === 'good' ? '✓' : '!' }}</span>
                                        <span>{{ $note['note'] ?? '' }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </article>
                    @endif
                @endif
            @endforeach
        </div>

        @if ($this->session->completed_at)
            <article class="card mt-4 p-5">
                <h2 class="text-card-title">{{ __('Your session report') }}</h2>

                @if (! empty($this->session->report['summary']))
                    <p class="mt-2 text-body leading-relaxed">{{ $this->session->report['summary'] }}</p>
                @endif

                @if (! empty($this->session->report['strengths']))
                    <h3 class="mt-4 text-body font-semibold">{{ __('What went well') }}</h3>
                    <ul class="mt-1 grid gap-1">
                        @foreach ($this->session->report['strengths'] as $item)
                            <li class="flex gap-2 text-body"><span class="text-green" aria-hidden="true">✓</span>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif

                @if (! empty($this->session->report['work_on']))
                    <h3 class="mt-4 text-body font-semibold">{{ __('Work on this') }}</h3>
                    <ul class="mt-1 grid gap-1">
                        @foreach ($this->session->report['work_on'] as $item)
                            <li class="flex gap-2 text-body"><span class="text-marigold-ink" aria-hidden="true">!</span>{{ $item }}</li>
                        @endforeach
                    </ul>
                @endif
            </article>

        @else
            <form wire:submit="reply" class="card mt-4 p-3">
                <label for="answer" class="sr-only">{{ __('Your answer') }}</label>
                <textarea id="answer" wire:model="answer" rows="3"
                          placeholder="{{ __('Type your answer…') }}"
                          class="w-full rounded-control border border-ink/20 px-3 py-2 text-body leading-relaxed
                                 focus:border-green focus:outline-none"></textarea>
                @error('answer')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

                <button type="submit" class="btn-primary mt-2 w-full" wire:loading.attr="disabled" wire:target="reply">
                    <span wire:loading.remove wire:target="reply">{{ __('Answer') }}</span>
                    <span wire:loading wire:target="reply">{{ __('The board is considering…') }}</span>
                </button>
            </form>

            <p class="mt-2 text-center text-meta text-muted">
                {{ __('Question :number of about :total', [
                    'number' => $this->session->question_count,
                    'total' => \App\Services\AI\Features\MockInterview::TARGET_QUESTIONS,
                ]) }}
                · {{ trans_choice('{0,1} :count minute elapsed|[2,*] :count minutes elapsed', $this->elapsed, ['count' => $this->elapsed]) }}
                · {{ __('full transcript and report at the end') }}
            </p>
        @endif
    @endif

</div>
