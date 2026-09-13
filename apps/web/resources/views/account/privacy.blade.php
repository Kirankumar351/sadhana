@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-2xl">

    <h1 class="font-display text-screen-title">{{ __('Your data') }}</h1>
    <p class="mt-1 max-w-reading text-body text-muted">
        {{ __('What we hold, why we hold it, and how to take it back.') }}
    </p>

    {{-- Purpose limitation, stated in plain language rather than legalese. The DPDP Act
         requires the purpose to be stated; saying it in words people actually use is what
         makes the consent meaningful rather than merely obtained. --}}
    <section class="card mt-6 p-5">
        <h2 class="font-display text-card-title">{{ __('What we collect, and why') }}</h2>
        <dl class="mt-3 grid gap-3 text-body">
            <div>
                <dt class="font-medium">{{ __('Date of birth, category, qualification') }}</dt>
                <dd class="text-muted">{{ __('Only to work out which jobs you qualify for. Nothing else.') }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('Phone number') }}</dt>
                <dd class="text-muted">{{ __('To sign you in, and to send alerts you asked for.') }}</dd>
            </div>
            <div>
                <dt class="font-medium">{{ __('District') }}</dt>
                <dd class="text-muted">{{ __('To show jobs near you. We never ask for a full address.') }}</dd>
            </div>
        </dl>

        <p class="mt-4 rounded-control bg-green-wash px-3 py-2 text-body text-green">
            {{ __('We never ask for Aadhaar, caste certificates, or documents. We do not need them and we do not want them.') }}
        </p>
    </section>

    <section class="card mt-4 p-5">
        <h2 class="font-display text-card-title">{{ __('Download everything we hold') }}</h2>
        <p class="mt-1 text-body text-muted">
            {{ __('A single file with your profile, quiz history, doubts, answers, study plans, flashcards and AI conversations.') }}
        </p>
        <a href="{{ route('privacy.export') }}" class="btn-secondary mt-3">{{ __('Download my data') }}</a>
    </section>

    {{-- Deletion sits last and is visually distinct. It is irreversible and should feel
         that way, without being hidden — hiding it would be its own dark pattern. --}}
    <section class="card mt-4 border-danger/30 p-5">
        <h2 class="font-display text-card-title text-danger">{{ __('Delete my account') }}</h2>

        <p class="mt-1 text-body text-muted">
            {{ __('This removes your profile, quiz history, streak, flashcards, study plans and AI conversations. It cannot be undone.') }}
        </p>

        {{-- Said plainly rather than buried, because a user who discovers it afterwards
             would reasonably feel misled. --}}
        <p class="mt-3 text-body text-muted">
            {{ __('Two things stay: doubts and answers you posted remain, with your name removed, because other people rely on them. Payment records are kept because tax law requires it.') }}
        </p>

        <form method="POST" action="{{ route('privacy.destroy') }}" class="mt-4">
            @csrf
            @method('DELETE')

            <label for="confirm_phone" class="block text-body font-medium">
                {{ __('Type your phone number to confirm') }}
            </label>
            <input type="tel" id="confirm_phone" name="confirm_phone" inputmode="numeric"
                   class="numeral mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-danger focus:outline-none">
            @error('confirm_phone')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror

            <button type="submit"
                    class="tap mt-3 w-full rounded-control border border-danger px-4 py-2.5 font-semibold text-danger hover:bg-danger hover:text-white">
                {{ __('Delete my account permanently') }}
            </button>
        </form>
    </section>

    @if ($requests->isNotEmpty())
        <section class="mt-4">
            <h2 class="text-meta font-semibold text-muted">{{ __('Your recent requests') }}</h2>
            <ul class="mt-2 grid gap-1 text-meta text-muted">
                @foreach ($requests as $request)
                    <li>{{ __($request->type) }} · {{ $request->created_at->format('d M Y') }} · {{ __($request->status) }}</li>
                @endforeach
            </ul>
        </section>
    @endif

</div>
@endsection
