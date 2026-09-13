<div class="mt-4">

    @if ($sent)
        {{-- Thanks, and the SLA stated. Telling someone their report will actually be read
             within two hours is what makes the next person bother to report. --}}
        <p class="rounded-control bg-green-wash px-3 py-2 text-body text-green">
            {{ __('Thank you — a person will check this within two hours. If it is wrong we will correct it and tell everyone who saved this job.') }}
        </p>

    @elseif ($open)
        <form wire:submit="submit" class="card p-4">
            <p class="text-body font-medium">{{ __('What is wrong?') }}</p>

            <div class="mt-3 grid gap-2">
                @foreach ($this->fields as $value => $label)
                    <label class="flex min-h-tap cursor-pointer items-center gap-3 rounded-control px-3 text-body hover:bg-paper">
                        <input type="radio" wire:model="field" value="{{ $value }}"
                               class="h-4 w-4 border-ink/30 text-green focus:ring-green">
                        {{ $label }}
                    </label>
                @endforeach
            </div>

            @error('field')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

            <label for="report-note" class="mt-3 block text-body font-medium">
                {{ __('What should it say?') }}
                <span class="font-normal text-muted">— {{ __('optional') }}</span>
            </label>
            <textarea id="report-note" wire:model="note" rows="2"
                      placeholder="{{ __('e.g. the PDF says 15 September, not 12 September') }}"
                      class="mt-1 w-full rounded-control border border-ink/20 px-3 py-2 text-body
                             focus:border-green focus:outline-none"></textarea>

            <div class="mt-3 flex gap-2">
                <button type="submit" class="btn-primary text-body">{{ __('Send report') }}</button>
                <button type="button" wire:click="$set('open', false)" class="btn-secondary text-body">
                    {{ __('Cancel') }}
                </button>
            </div>
        </form>

    @else
        <p class="text-meta text-muted">
            {{ __('Something wrong on this page?') }}
            <button type="button" wire:click="$set('open', true)" class="text-green hover:underline">
                {{ __('Report an error') }}
            </button>
        </p>
    @endif

</div>
