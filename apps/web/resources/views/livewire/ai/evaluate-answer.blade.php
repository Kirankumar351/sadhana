<div class="grid gap-5 lg:grid-cols-[1fr_19rem]">

    <div>
        {{-- Said once, at the top, before anything else. The distinction between feedback
             and a mark is the whole trust question for this feature: a serious candidate
             will check a predicted mark against their real result. --}}
        <div class="card border-marigold bg-marigold-wash p-4">
            <p class="text-body">
                <strong>{{ __('This is feedback, not a mark.') }}</strong>
                {{ __('No AI can predict what an actual examiner will award. What it can do reliably is tell you which required points you did not write, whether your structure follows the expected pattern, and where you wasted words — and that is what improves scores.') }}
            </p>
        </div>

        <form wire:submit="evaluate" class="card mt-4 p-4">
            <label for="question" class="block text-meta font-medium text-muted">{{ __('Question') }}</label>
            <select id="question" wire:model.live="questionId"
                    class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none">
                <option value="">{{ __('Choose a mains question') }}</option>
                @foreach ($this->questions as $q)
                    <option value="{{ $q->id }}">{{ Str::limit($q->question, 90) }}</option>
                @endforeach
            </select>
            @error('questionId')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

            @if ($this->question)
                <div class="mt-4 rounded-control bg-paper p-3">
                    <div class="flex flex-wrap items-center gap-2 text-meta text-muted">
                        @if ($this->question->paper)<span class="chip">{{ $this->question->paper }}</span>@endif
                        <span class="chip numeral">{{ $this->question->marks }} {{ __('marks') }}</span>
                        @if ($this->question->word_limit)
                            <span class="chip numeral">{{ $this->question->word_limit }} {{ __('words') }}</span>
                        @endif
                        @if ($this->question->directive)
                            <span class="chip">{{ $this->question->directive }}</span>
                        @endif
                    </div>
                    <p class="mt-2 text-body leading-relaxed">{{ $this->question->question }}</p>
                </div>

                <div class="mt-4">
                    <label for="answer" class="block text-meta font-medium text-muted">
                        {{ __('Your answer') }}
                        <span class="font-normal">— {{ __('type, or photograph your handwriting') }}</span>
                    </label>
                    <textarea id="answer" wire:model.live.debounce.500ms="answer" rows="10"
                              class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body leading-relaxed
                                     focus:border-green focus:outline-none"></textarea>

                    <div class="mt-1 flex justify-between text-meta text-muted">
                        <span class="numeral">
                            {{ $this->words }}@if ($this->question->word_limit) / {{ $this->question->word_limit }} @endif
                            {{ __('words') }}
                        </span>
                        @error('answer')<span class="text-danger">{{ $message }}</span>@enderror
                    </div>
                </div>

                <div class="mt-3 flex flex-wrap gap-2">
                    <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="evaluate">
                        <span wire:loading.remove wire:target="evaluate">✦ {{ __('Evaluate') }}</span>
                        <span wire:loading wire:target="evaluate">{{ __('Reading your answer…') }}</span>
                    </button>
                </div>
            @endif
        </form>

        {{-- ------------------------------------------------------ the feedback --}}
        @if ($result)
            @if ($result->status === 'ok')
                @php $data = $result->data; @endphp

                <article class="card mt-4 p-5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <span class="badge-ai">✦ {{ __('AI · rubric-based feedback') }}</span>
                        <span class="chip text-meta">{{ __('Indicative only') }}</span>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div class="card p-3 text-center">
                            <p class="font-display text-2xl font-bold">
                                <span class="numeral">{{ $data['band'] ?? '—' }}</span><span class="text-body text-muted">/{{ $this->question->marks }}</span>
                            </p>
                            <p class="text-meta text-muted">{{ __('Indicative band') }}</p>
                        </div>
                        <div class="card p-3 text-center">
                            <p class="numeral font-display text-2xl font-bold">{{ $this->words }}</p>
                            <p class="text-meta text-muted">
                                {{ $this->question->word_limit ? __('Words of :limit', ['limit' => $this->question->word_limit]) : __('Words') }}
                            </p>
                        </div>
                        <div class="card p-3 text-center">
                            <p class="numeral font-display text-2xl font-bold">{{ count($data['points_hit'] ?? []) }}</p>
                            <p class="text-meta text-muted">{{ __('Required points hit') }}</p>
                        </div>
                    </div>

                    @if (! empty($data['points_hit']))
                        <h3 class="mt-5 text-card-title">{{ __('Points you covered') }}</h3>
                        <ul class="mt-2 grid gap-1">
                            @foreach ($data['points_hit'] as $point)
                                <li class="flex gap-2 text-body">
                                    <span class="text-green" aria-hidden="true">✓</span>
                                    <span>{{ is_array($point) ? ($point['point'] ?? '') : $point }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($data['points_missed']))
                        <h3 class="mt-5 text-card-title">{{ __('Points you missed') }}</h3>
                        <ul class="mt-2 grid gap-2">
                            @foreach ($data['points_missed'] as $missed)
                                <li class="flex gap-2">
                                    <span class="text-danger" aria-hidden="true">✕</span>
                                    <div>
                                        <p class="text-body font-semibold">
                                            {{ is_array($missed) ? ($missed['point'] ?? '') : $missed }}
                                        </p>
                                        {{-- Why it matters, not just that it is absent. --}}
                                        @if (is_array($missed) && ! empty($missed['why']))
                                            <p class="text-meta text-muted">{{ $missed['why'] }}</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    @if (! empty($data['single_biggest_gain']))
                        <div class="mt-5 rounded-control bg-green-wash p-3">
                            <p class="text-body">
                                <strong>{{ __('The single change that would gain the most:') }}</strong>
                                {{ $data['single_biggest_gain'] }}
                            </p>
                        </div>
                    @endif

                    <div class="mt-4 flex flex-wrap gap-2">
                        @if ($this->question->model_answer)
                            <button type="button" class="btn-secondary text-meta"
                                    onclick="document.getElementById('model-answer').hidden = !document.getElementById('model-answer').hidden">
                                {{ __('See a model answer') }}
                            </button>
                        @endif
                        <button type="button" wire:click="$set('result', null)" class="btn-secondary text-meta">
                            {{ __('Rewrite and re-evaluate') }}
                        </button>
                        <a href="{{ route('ask') }}" class="btn-secondary text-meta">{{ __('Disagree with this') }}</a>
                    </div>

                    @if ($this->question->model_answer)
                        <div id="model-answer" hidden class="mt-4 border-t border-ink/10 pt-3">
                            <p class="text-meta font-semibold text-muted">{{ __('Model answer') }}</p>
                            <div class="mt-2 whitespace-pre-line text-body leading-relaxed">
                                {{ \App\Support\Translated::from($this->question->model_answer) }}
                            </div>
                        </div>
                    @endif
                </article>

            @elseif ($result->status === 'cap_reached')
                <article class="card mt-4 border-marigold bg-marigold-wash p-5">
                    <p class="text-body font-medium">{{ __('You have used this month’s evaluations') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('Reading a mains answer properly is the most expensive thing we do, which is why the allowance is small. Your previous evaluations stay available.') }}
                    </p>
                    <p class="numeral mt-2 text-meta text-muted">
                        {{ $result->used }}/{{ $result->limit }} · {{ __('resets') }} {{ $result->resetsAt?->diffForHumans() }}
                    </p>
                </article>

            @else
                <article class="card mt-4 p-5">
                    <p class="text-body font-medium">{{ __('We could not read that answer just now.') }}</p>
                    <p class="mt-1 text-body text-ink-soft">
                        {{ __('Nothing has been counted against your allowance. Please try again in a moment.') }}
                    </p>
                </article>
            @endif
        @endif
    </div>

    {{-- ------------------------------------------------------------- sidebar --}}
    <aside class="grid content-start gap-4">
        {{-- Published, not hidden, and shown BEFORE they write. --}}
        <section class="card p-4">
            <h2 class="text-card-title">{{ __('The rubric') }}</h2>
            <p class="mt-1 text-meta text-muted">{{ __('Published, not hidden. You can see exactly what it is scoring.') }}</p>

            @if ($this->question?->rubric)
                <dl class="mt-3 grid gap-1 text-body">
                    @foreach ($this->question->rubric as $criterion => $weight)
                        <div class="flex justify-between gap-2 border-t border-ink/10 pt-1">
                            <dt class="text-muted">{{ is_string($criterion) ? $criterion : ($weight['name'] ?? '') }}</dt>
                            <dd class="numeral font-semibold">
                                {{ is_array($weight) ? ($weight['weight'] ?? '') : $weight }}@if (! is_array($weight))%@endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @else
                <p class="mt-2 text-meta text-muted">{{ __('Choose a question to see its rubric.') }}</p>
            @endif
        </section>

        <section class="card p-4">
            <h2 class="text-card-title">{{ __('Your trend') }}</h2>
            <dl class="mt-2 grid gap-1 text-body">
                <div class="flex justify-between">
                    <dt class="text-muted">{{ __('Answers evaluated') }}</dt>
                    <dd class="numeral font-semibold">{{ $this->history->count() }}</dd>
                </div>
                @if ($this->averageBand !== null)
                    <div class="flex justify-between">
                        <dt class="text-muted">{{ __('Average band') }}</dt>
                        <dd class="numeral font-semibold">{{ $this->averageBand }}</dd>
                    </div>
                @endif
            </dl>

            {{-- The thing the feature knows that the student cannot: one habit repeating
                 across many answers, which no single evaluation reveals. --}}
            @if ($this->recurringGap)
                <p class="mt-3 rounded-control bg-marigold-wash px-3 py-2 text-meta text-ink-soft">
                    {{ __('You have missed :gap in :count of :total answers. That one habit is costing more than any content gap.', [
                        'gap' => $this->recurringGap['gap'],
                        'count' => $this->recurringGap['count'],
                        'total' => $this->recurringGap['total'],
                    ]) }}
                </p>
            @endif
        </section>
    </aside>
</div>
