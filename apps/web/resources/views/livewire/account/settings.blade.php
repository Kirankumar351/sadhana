<div class="mx-auto max-w-2xl">

    @if ($saved)
        <p class="mb-4 rounded-card bg-green-wash px-4 py-3 text-body text-green">
            {{ __('Saved.') }}
        </p>
    @endif

    <form wire:submit="save" class="grid gap-4">

        <section class="card p-5">
            <h2 class="font-display text-card-title">{{ __('About you') }}</h2>

            <div class="mt-4">
                <label for="s-name" class="block text-body font-medium">{{ __('Your name') }}</label>
                <input type="text" id="s-name" wire:model="name"
                       class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
                @error('name')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
            </div>

            <div class="mt-4">
                <span class="block text-body font-medium">{{ __('Language') }}</span>
                {{-- For this audience language is not a setting, it is the product — so it
                     appears here as well as in the header, not only buried in one place. --}}
                <div class="mt-2 flex gap-2">
                    @foreach (['te' => 'తెలుగు', 'en' => 'English'] as $code => $label)
                        <label class="chip cursor-pointer has-[:checked]:border-green has-[:checked]:bg-green-wash has-[:checked]:text-green">
                            <input type="radio" wire:model="locale" value="{{ $code }}" class="sr-only">
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="card p-5">
            <h2 class="font-display text-card-title">{{ __('Notifications') }}</h2>

            {{-- The cap, said out loud.
                 A specific promise someone can hold us to is worth more than any wording
                 about "relevant updates", and over-notification is the commonest reason
                 an app like this gets muted. --}}
            <p class="mt-1 rounded-control bg-green-wash px-3 py-2 text-body text-green">
                {{ __('We never send more than :count notifications a day, whatever you turn on.', ['count' => $dailyCap]) }}
            </p>

            <div class="mt-4 grid gap-4">
                @foreach ($this->types as $type => $meta)
                    <div class="border-b border-ink/10 pb-4 last:border-0 last:pb-0">
                        <p class="text-body font-medium">{{ $meta['label'] }}</p>
                        <p class="mt-0.5 text-meta text-muted">{{ $meta['help'] }}</p>

                        <div class="mt-2 flex flex-wrap gap-4">
                            <label class="flex min-h-tap cursor-pointer items-center gap-2 text-body">
                                <input type="checkbox" wire:model="preferences.{{ $type }}.push"
                                       class="h-5 w-5 rounded border-ink/30 text-green focus:ring-green">
                                {{ __('On this device') }}
                            </label>

                            {{-- WhatsApp is offered only where the message earns the cost
                                 and the intrusion. A streak nudge does not. --}}
                            @if ($meta['whatsapp'])
                                <label class="flex min-h-tap cursor-pointer items-center gap-2 text-body">
                                    <input type="checkbox" wire:model="preferences.{{ $type }}.whatsapp"
                                           class="h-5 w-5 rounded border-ink/30 text-green focus:ring-green">
                                    WhatsApp
                                </label>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-wrap gap-3">
            <button type="submit" class="btn-primary">{{ __('Save settings') }}</button>

            {{-- Offered plainly rather than hidden. The alternative someone reaches for is
                 muting us at the OS level, which also silences the deadline reminder for a
                 job they had already decided to apply for. --}}
            <button type="button" wire:click="muteAll" class="btn-secondary">
                {{ __('Turn everything off') }}
            </button>
        </div>
    </form>

    <p class="mt-6 text-meta text-muted">
        <a href="{{ route('privacy') }}" class="text-green hover:underline">{{ __('Your data and privacy') }}</a>
        — {{ __('download everything we hold, or delete your account.') }}
    </p>

</div>
