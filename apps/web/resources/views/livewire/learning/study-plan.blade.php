<div>

    @php
        $plan = $this->plan;
        $data = $plan?->plan ?? [];
        $allocation = $data['allocation'] ?? [];
        $strength = $data['strength'] ?? [];
    @endphp

    {{-- ------------------------------------------------- no exam chosen yet --}}
    @if ($this->exam === null)
        <div class="card p-6 text-center">
            <p class="text-body font-medium">{{ __('Choose the exam you are preparing for') }}</p>
            <p class="mt-1 text-body text-muted">
                {{ __('A plan is built against one exam, its syllabus weights and its date.') }}
            </p>
            <a href="{{ route('exams.index') }}" class="btn-primary mt-4">{{ __('Choose an exam') }}</a>
        </div>

    {{-- ------------------------------------------------- no target date set --}}
    @elseif ($this->targetDate === null)
        <div class="card p-6 text-center">
            <p class="text-body font-medium">{{ __('Set your exam date') }}</p>
            <p class="mt-1 text-body text-muted">
                {{ __('Everything in the plan is measured backwards from the paper. Without a date there is nothing to allocate.') }}
            </p>
            <a href="{{ route('profile.edit') }}" class="btn-primary mt-4">{{ __('Set the date') }}</a>
        </div>

    {{-- --------------------------------------- not enough history to be real --}}
    @elseif ($plan === null && $this->attemptsNeeded > 0)
        <div class="card p-6">
            <p class="text-body font-medium">{{ __('Not enough quizzes yet to build a plan worth following') }}</p>
            <p class="mt-2 text-body text-ink-soft">
                {{ __('A plan built on a handful of attempts is guesswork dressed as advice. Sending you to spend three weeks on a subject because you got two questions wrong in it would be worse than offering nothing.') }}
            </p>
            <p class="mt-3 text-body font-medium">
                {{ trans_choice('{1} :count more daily quiz and we can build it|[2,*] :count more daily quizzes and we can build it',
                    $this->attemptsNeeded, ['count' => $this->attemptsNeeded]) }}
            </p>
            <a href="{{ route('quiz.today') }}" class="btn-primary mt-4">{{ __("Today's quiz") }}</a>
        </div>

    {{-- ------------------------------------------------ ready, but not built --}}
    @elseif ($plan === null)
        <div class="card p-6 text-center">
            <p class="text-body font-medium">{{ __('Your history is ready') }}</p>
            <p class="mt-1 text-body text-muted">
                {{ __('We can build your plan from it now.') }}
            </p>
            <button type="button" wire:click="rebuild" class="btn-primary mt-4" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="rebuild">{{ __('Build my plan') }}</span>
                <span wire:loading wire:target="rebuild">{{ __('Working through your quizzes…') }}</span>
            </button>
        </div>

    @else
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <span class="badge-ai">✦ {{ __('Study plan') }}</span>
                <h1 class="font-display text-screen-title mt-2">
                    {{ __('Your plan to :date', ['date' => $plan->target_date->translatedFormat('j F')]) }}
                </h1>
                <p class="mt-1 text-body text-muted">
                    {{ trans_choice('{1} Built from your quiz history, the syllabus weights and :count day remaining.|[2,*] Built from your quiz history, the syllabus weights and :count days remaining.',
                        $data['days_left'] ?? 0, ['count' => $data['days_left'] ?? 0]) }}
                </p>
            </div>

            <button type="button" wire:click="rebuild" class="btn-secondary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="rebuild">{{ __('Rebuild plan') }}</span>
                <span wire:loading wire:target="rebuild">{{ __('Rebuilding…') }}</span>
            </button>
        </div>

        {{-- ------------------------------------------------------------- KPIs --}}
        <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="card p-4">
                <p class="numeral font-display text-2xl font-bold">{{ $data['days_left'] ?? 0 }}</p>
                <p class="text-meta text-muted">{{ __('Days to :exam', ['exam' => $this->exam->short_name]) }}</p>
            </div>

            <div class="card p-4">
                <p class="numeral font-display text-2xl font-bold">
                    {{ $this->dailyMinutes ? round($this->dailyMinutes / 60, 1).'h' : '—' }}
                </p>
                <p class="text-meta text-muted">{{ __('Your average daily study') }}</p>
            </div>

            @if ($strength !== [])
                <div class="card p-4">
                    <p class="numeral font-display text-2xl font-bold text-danger">{{ reset($strength) }}%</p>
                    <p class="text-meta text-muted">{{ __('Weakest — :subject', ['subject' => array_key_first($strength)]) }}</p>
                </div>

                <div class="card p-4">
                    <p class="numeral font-display text-2xl font-bold text-green">{{ end($strength) }}%</p>
                    <p class="text-meta text-muted">{{ __('Strongest — :subject', ['subject' => array_key_last($strength)]) }}</p>
                </div>
            @endif
        </div>

        <div class="mt-5 grid gap-5 lg:grid-cols-[1fr_18rem]">
            <div>
                {{-- The model explains the allocation. It never decides it. --}}
                @if (! empty($data['narrative']))
                    <article class="card p-5">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="badge-ai">
                                ✦ {{ trans_choice('{1} AI · from your last :count quiz|[2,*] AI · from your last :count quizzes',
                                    $plan->based_on_attempts, ['count' => $plan->based_on_attempts]) }}
                            </span>
                            <span class="text-meta text-muted">
                                {{ __('Updated') }} {{ $plan->generated_at->diffForHumans() }}
                            </span>
                        </div>
                        <div class="mt-3 whitespace-pre-line text-body leading-relaxed">{{ $data['narrative'] }}</div>
                    </article>
                @endif

                <h2 class="font-display text-card-title mt-6">{{ __('Week by week') }}</h2>

                @foreach ($data['weeks'] ?? [] as $i => $week)
                    <section class="card mt-3 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <h3 class="text-card-title">
                                {{ __('Week :number', ['number' => $i + 1]) }} ·
                                <span class="text-muted">
                                    {{ \Carbon\CarbonImmutable::parse($week['from'])->translatedFormat('j M') }}–{{ \Carbon\CarbonImmutable::parse($week['to'])->translatedFormat('j M') }}
                                </span>
                            </h3>

                            @if ($week['consolidation'])
                                <span class="chip text-meta text-green">{{ __('Consolidation') }}</span>
                            @elseif ($week['focus'])
                                <span class="chip text-meta">{{ __(':subject focus', ['subject' => $week['focus']]) }}</span>
                            @endif
                        </div>

                        <div class="mt-3 grid gap-2">
                            @foreach ($week['blocks'] as $block)
                                <div class="grid gap-1 border-t border-ink/10 pt-2 sm:grid-cols-[7rem_1fr]">
                                    <p class="text-body font-semibold">{{ $block['days'] }}</p>
                                    <div>
                                        <p class="text-body">{{ $block['task'] }}</p>
                                        <p class="text-meta text-muted">{{ $block['detail'] }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endforeach
            </div>

            {{-- ---------------------------------------------------- sidebar --}}
            <aside class="grid content-start gap-4">
                <section class="card p-4">
                    <h2 class="text-card-title">{{ __('Time allocation') }}</h2>

                    <div class="mt-3 grid gap-3">
                        @foreach ($allocation as $subject => $share)
                            <div>
                                <div class="flex justify-between text-meta">
                                    <span>{{ $subject === '__mocks' ? __('Mocks and revision') : $subject }}</span>
                                    <span class="numeral font-semibold">{{ $share }}%</span>
                                </div>
                                <div class="mt-1 h-1.5 overflow-hidden rounded-pill bg-ink/10">
                                    <div class="h-full rounded-pill
                                                {{ ($strength[$subject] ?? 100) < 60 ? 'bg-danger' : 'bg-green' }}"
                                         style="width: {{ $share }}%"></div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>

                {{-- Said plainly, on the screen and not only in the code. --}}
                <section class="card border-marigold bg-marigold-wash p-4">
                    <p class="text-body">
                        <strong>{{ __('This is a plan, not a prediction.') }}</strong>
                        {{ __('It cannot tell you whether you will clear the exam, and it will not try. It only allocates the hours you have across the topics where they are worth the most.') }}
                    </p>
                </section>
            </aside>
        </div>
    @endif

</div>
