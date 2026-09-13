<form wire:submit="submit" class="card mt-5 p-5">

    <label for="doubt-title" class="block text-body font-medium">{{ __('Your question') }}</label>
    <input type="text" id="doubt-title" wire:model.live.debounce.400ms="title"
           placeholder="{{ __('e.g. SC candidates ki age relaxation e date nunchi lekkistaru?') }}"
           class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body
                  focus:border-green focus:outline-none">
    @error('title')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

    {{--
        SEARCH BEFORE YOU ASK.

        This is what keeps the archive clean enough to rank on Google, and it is also the
        fastest possible answer: if someone already asked it, the reply is already written.
        Shown as a suggestion rather than a block — the same words can mean two different
        questions, and refusing to let someone post is worse than one duplicate.
    --}}
    @if ($similar)
        <div class="mt-3 rounded-card border border-marigold bg-marigold-wash p-4">
            <p class="text-body font-semibold">{{ __('Already asked — your answer may be here') }}</p>
            <ul class="mt-2 grid gap-1">
                @foreach ($similar as $match)
                    <li>
                        <a href="{{ $match['url'] }}" class="text-body text-green hover:underline">{{ $match['title'] }}</a>
                        <span class="numeral text-meta text-muted">
                            · {{ $match['answers'] }} {{ trans_choice('{0} answers|{1} answer|[2,*] answers', $match['answers']) }}
                        </span>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <label for="doubt-body" class="mt-4 block text-body font-medium">{{ __('Explain it a bit more') }}</label>
    <textarea id="doubt-body" wire:model="body" rows="6"
              placeholder="{{ __('What have you already tried or read? That saves the person answering a lot of guessing.') }}"
              class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body
                     focus:border-green focus:outline-none"></textarea>
    @error('body')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

    <div class="mt-4 grid gap-4 sm:grid-cols-2">
        <div>
            <label for="doubt-exam" class="block text-body font-medium">{{ __('Which exam') }}</label>
            <select id="doubt-exam" wire:model="exam_id"
                    class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
                <option value="">{{ __('Not specific to one') }}</option>
                @foreach ($exams as $exam)
                    <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="doubt-subject" class="block text-body font-medium">{{ __('Subject') }}</label>
            <input type="text" id="doubt-subject" wire:model="subject" list="subjects"
                   class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
            <datalist id="subjects">
                @foreach (['Indian Polity', 'Indian Economy', 'Geography', 'History', 'Current Affairs', 'Telangana Movement', 'Reasoning'] as $s)
                    <option value="{{ $s }}"></option>
                @endforeach
            </datalist>
        </div>
    </div>

    {{--
        Photo and voice.

        Typing Telugu on a phone keyboard is genuinely painful, and a student with a doubt
        about one line of a textbook will photograph it. Asking them to transcribe it means
        they do not ask at all.
    --}}
    <div class="mt-4 grid gap-3 sm:grid-cols-2">
        <label class="flex min-h-tap cursor-pointer items-center gap-2 rounded-control border border-dashed border-ink/25 px-3 text-body text-muted hover:border-green">
            <input type="file" wire:model="image" accept="image/*" class="sr-only">
            📷 {{ $image ? __('Photo attached') : __('Add a photo') }}
        </label>

        <label class="flex min-h-tap cursor-pointer items-center gap-2 rounded-control border border-dashed border-ink/25 px-3 text-body text-muted hover:border-green">
            <input type="file" wire:model="audio" accept="audio/*" class="sr-only">
            🎙 {{ $audio ? __('Voice note attached') : __('Record a voice note') }}
        </label>
    </div>
    @error('image')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
    @error('audio')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

    <button type="submit" class="btn-primary mt-5 w-full" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="submit">{{ __('Post my doubt') }}</span>
        <span wire:loading wire:target="submit">{{ __('Posting…') }}</span>
    </button>

    {{-- Stated plainly, because this market has an active "guaranteed job" scam problem
         and phone numbers in public posts are how it operates. --}}
    <p class="mt-3 text-meta text-muted">
        {{ __('Never post your phone number. Anyone promising a guaranteed job for money is a scam.') }}
    </p>
</form>
