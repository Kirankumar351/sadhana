<div>

@if ($submitted)
    <div class="card mt-5 border-green bg-green-wash p-6 text-center">
        <p class="text-body font-semibold">{{ __('Thank you — your notes are with our reviewers') }}</p>
        <p class="mt-2 text-body text-ink-soft">
            {{ __('Every upload is checked by a person before it goes live, usually within a day. You will be credited on the page.') }}
        </p>
        <a href="{{ route('material.index') }}" class="btn-secondary mt-4">{{ __('Back to material') }}</a>
    </div>
@else

<form wire:submit="submit" class="card mt-5 p-5">

    {{--
        The rule, stated before the form rather than buried under it.

        Every Telugu exam-prep Telegram channel distributes scanned coaching books. A user
        arriving here has probably seen that and assumes it is normal. Saying plainly what
        we will and will not take is more effective than a checkbox alone, and it is the
        difference between a contributor who understands and one who is merely clicking.
    --}}
    <div class="rounded-card border border-marigold bg-marigold-wash p-4">
        <p class="text-body font-semibold">{{ __('What we can publish') }}</p>
        <ul class="mt-2 grid gap-1 text-body text-ink-soft">
            <li>✓ {{ __('Notes you wrote yourself — handwritten or typed') }}</li>
            <li>✓ {{ __('Your own summaries and mind maps') }}</li>
            <li>✕ {{ __('Scanned pages from a coaching book or any published book') }}</li>
            <li>✕ {{ __('PDFs with someone else\'s watermark or logo') }}</li>
            <li>✕ {{ __('Anything you found forwarded on WhatsApp or Telegram') }}</li>
        </ul>
        <p class="mt-2 text-meta text-muted">
            {{ __('Uploading someone else\'s book is illegal and gets the account permanently banned on a second offence. We would rather have fewer notes than lose the library.') }}
        </p>
    </div>

    <div class="mt-5">
        <label for="m-title" class="block text-body font-medium">{{ __('Title') }}</label>
        <input type="text" id="m-title" wire:model="title"
               placeholder="{{ __('e.g. Polity — Fundamental Rights, my revision notes') }}"
               class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
        @error('title')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
    </div>

    <div class="mt-4">
        <label for="m-desc" class="block text-body font-medium">{{ __('What is in it') }}</label>
        <textarea id="m-desc" wire:model="description" rows="3"
                  class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body focus:border-green focus:outline-none"></textarea>
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-3">
        <div>
            <label for="m-exam" class="block text-body font-medium">{{ __('Exam') }}</label>
            <select id="m-exam" wire:model="exam_id"
                    class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
                <option value="">{{ __('Select') }}</option>
                @foreach ($exams as $exam)
                    <option value="{{ $exam->id }}">{{ $exam->name }}</option>
                @endforeach
            </select>
            @error('exam_id')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <label for="m-subject" class="block text-body font-medium">{{ __('Subject') }}</label>
            <input type="text" id="m-subject" wire:model="subject"
                   class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
        </div>

        <div>
            <label for="m-locale" class="block text-body font-medium">{{ __('Language') }}</label>
            <select id="m-locale" wire:model="locale"
                    class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
                <option value="te">తెలుగు</option>
                <option value="en">English</option>
            </select>
        </div>
    </div>

    <div class="mt-4">
        <label class="flex min-h-tap cursor-pointer items-center justify-center gap-2 rounded-control border border-dashed border-ink/25 px-4 py-6 text-body text-muted hover:border-green">
            <input type="file" wire:model="file" accept=".pdf,image/*" class="sr-only">
            <span wire:loading.remove wire:target="file">
                {{ $file ? '✓ '.__('File selected') : '📄 '.__('Choose a PDF or photo — up to 25 MB') }}
            </span>
            <span wire:loading wire:target="file">{{ __('Uploading…') }}</span>
        </label>
        @error('file')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
    </div>

    {{--
        THE WARRANTY.

        Specific wording rather than a link to terms nobody reads, and the acceptance is
        stored with a timestamp and IP. That record is what turns "we asked them not to"
        into something defensible if a publisher ever writes to us.
    --}}
    <label class="mt-5 flex cursor-pointer items-start gap-3 rounded-control bg-paper p-4">
        <input type="checkbox" wire:model="copyright_confirmed"
               class="mt-0.5 h-5 w-5 shrink-0 rounded border-ink/30 text-green focus:ring-green">
        <span class="text-body">
            {{ __('This is my own work. I wrote it myself and I have the right to share it. It is not scanned from any book or taken from anyone else.') }}
        </span>
    </label>
    @error('copyright_confirmed')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

    <button type="submit" class="btn-primary mt-5 w-full" wire:loading.attr="disabled">
        <span wire:loading.remove wire:target="submit">{{ __('Send for review') }}</span>
        <span wire:loading wire:target="submit">{{ __('Uploading…') }}</span>
    </button>

    <p class="mt-3 text-meta text-muted">
        {{ __('A person reads every upload before it is published. Usually within a day.') }}
    </p>
</form>

@endif

</div>
