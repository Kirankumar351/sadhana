<div class="mt-8">

    <h2 class="font-display text-card-title">
        <span class="numeral">{{ $post->answers->count() }}</span>
        {{ trans_choice('{0} answers|{1} answer|[2,*] answers', $post->answers->count()) }}
    </h2>

    <div class="mt-4 grid gap-4">
        @foreach ($post->answers as $answer)
            @php $isAuthor = auth()->id() === $post->user_id; @endphp

            <article class="card p-4 {{ $answer->is_best ? 'border-green bg-green-wash' : '' }}">

                <div class="flex items-start gap-3">

                    {{-- Vote column --}}
                    <div class="flex shrink-0 flex-col items-center gap-1">
                        <button type="button" wire:click="vote({{ $answer->id }}, 1)"
                                aria-label="{{ __('Helpful') }}"
                                class="tap w-10 rounded-control text-muted hover:bg-ink/5 hover:text-green">▲</button>

                        <span class="numeral text-body font-semibold">{{ $answer->upvotes }}</span>

                        <button type="button" wire:click="vote({{ $answer->id }}, -1)"
                                aria-label="{{ __('Not helpful') }}"
                                class="tap w-10 rounded-control text-muted hover:bg-ink/5 hover:text-danger">▼</button>
                    </div>

                    <div class="min-w-0 flex-1">

                        <div class="flex flex-wrap items-center gap-2">
                            @if ($answer->is_ai)
                                {{--
                                    Always labelled, always ranked below humans.

                                    We never blur the line to make the product look
                                    smarter. A student deciding what to study deserves to
                                    know whether a person or a machine told them.
                                --}}
                                <span class="badge-ai">✦ {{ __('AI') }}</span>
                                <span class="text-meta text-muted">{{ __('Ranked below every human answer') }}</span>
                            @else
                                <x-user-badge :user="$answer->user" />
                            @endif

                            @if ($answer->is_best)
                                <span class="badge-eligible">✓ {{ __('Accepted') }}</span>
                            @endif
                        </div>

                        <div class="prose mt-2 whitespace-pre-line text-body text-ink-soft">{{ $answer->body }}</div>

                        @if ($answer->is_ai)
                            <p class="mt-2 rounded-control bg-paper px-3 py-2 text-meta text-muted">
                                {{ __('This is a machine answer from our own material. A person will usually answer better — and their answer will appear above this one.') }}
                            </p>
                        @endif

                        <div class="mt-2 flex items-center gap-3 text-meta text-muted">
                            <span>{{ $answer->created_at->diffForHumans() }}</span>

                            {{-- Only the asker can accept, and never an AI answer. A
                                 machine answer carrying the accepted tick would tell every
                                 future reader it is as trustworthy as a verified
                                 candidate's. --}}
                            @if ($isAuthor && ! $answer->is_best && ! $answer->is_ai)
                                <button type="button" wire:click="accept({{ $answer->id }})"
                                        class="text-green hover:underline">
                                    {{ __('Mark as the answer') }}
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </article>
        @endforeach

        @if ($post->answers->isEmpty())
            <div class="card p-6 text-center">
                <p class="text-body font-medium">{{ __('No answers yet') }}</p>
                <p class="mt-1 text-body text-muted">
                    {{ __('People who follow this subject have been notified. An answer usually arrives within a few hours.') }}
                </p>
            </div>
        @endif
    </div>

    {{-- Answer form --}}
    @auth
        <form wire:submit="submit" class="card mt-6 p-4">
            <label for="answer-body" class="block text-body font-medium">{{ __('Write an answer') }}</label>

            <textarea id="answer-body" wire:model="body" rows="5"
                      placeholder="{{ __('Explain it the way you would to a friend preparing with you.') }}"
                      class="mt-2 w-full rounded-control border border-ink/20 px-3 py-2 text-body
                             focus:border-green focus:outline-none"></textarea>

            @error('body')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

            <button type="submit" class="btn-primary mt-3" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="submit">{{ __('Post answer') }}</span>
                <span wire:loading wire:target="submit">{{ __('Posting…') }}</span>
            </button>

            <p class="mt-3 text-meta text-muted">
                {{ __('Be kind. Everyone here is preparing under pressure.') }}
            </p>
        </form>
    @else
        <p class="card mt-6 p-4 text-center text-body text-muted">
            <a href="{{ route('login') }}" class="text-green hover:underline">{{ __('Sign in') }}</a>
            {{ __('to answer this doubt.') }}
        </p>
    @endauth

</div>
