@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-xl">

    <h1 class="font-display text-screen-title">{{ __('Your details') }}</h1>
    <p class="mt-1 text-body text-muted">
        {{ __('Used only to check which jobs you qualify for. Takes 30 seconds.') }}
    </p>

    <form method="POST" action="{{ route('profile.update') }}" class="card mt-5 grid gap-5 p-5">
        @csrf

        <div>
            <label for="date_of_birth" class="block text-body font-medium">{{ __('Date of birth') }}</label>
            <input type="date" id="date_of_birth" name="date_of_birth"
                   value="{{ old('date_of_birth', $profile->date_of_birth?->format('Y-m-d')) }}"
                   class="numeral mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body
                          focus:border-green focus:outline-none">
            <p class="mt-1 text-meta text-muted">
                {{ __('Age limits are calculated from the date the notification states, not from today.') }}
            </p>
            @error('date_of_birth')<p class="mt-1 text-meta text-danger">{{ $message }}</p>@enderror
        </div>

        <div>
            <span class="block text-body font-medium">{{ __('Category') }}</span>
            <p class="text-meta text-muted">{{ __('Decides your age relaxation.') }}</p>
            <div class="scroll-x mt-2">
                @foreach (['general' => __('General'), 'obc' => 'OBC', 'sc' => 'SC', 'st' => 'ST', 'ews' => 'EWS'] as $value => $label)
                    <label class="chip cursor-pointer has-[:checked]:border-green has-[:checked]:bg-green-wash has-[:checked]:text-green">
                        <input type="radio" name="category" value="{{ $value }}" class="sr-only"
                               @checked(old('category', $profile->category) === $value)>
                        {{ $label }}
                    </label>
                @endforeach
            </div>
        </div>

        <div>
            <label for="highest_qualification" class="block text-body font-medium">{{ __('Highest qualification') }}</label>
            <select id="highest_qualification" name="highest_qualification"
                    class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body
                           focus:border-green focus:outline-none">
                <option value="">{{ __('Select') }}</option>
                @foreach ([
                    '10th' => __('10th / SSC'),
                    '12th' => __('Intermediate / 12th'),
                    'iti' => 'ITI',
                    'diploma' => __('Diploma'),
                    'degree' => __('Degree — any stream'),
                    'btech' => 'B.Tech / B.E.',
                    'pg' => __('Post Graduation'),
                    'mbbs' => 'MBBS',
                    'phd' => 'PhD',
                ] as $value => $label)
                    <option value="{{ $value }}" @selected(old('highest_qualification', $profile->highest_qualification) === $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="state" class="block text-body font-medium">{{ __('State') }}</label>
                <select id="state" name="state"
                        class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
                    <option value="TS" @selected(old('state', $profile->state) === 'TS')>{{ __('Telangana') }}</option>
                    <option value="AP" @selected(old('state', $profile->state) === 'AP')>{{ __('Andhra Pradesh') }}</option>
                </select>
            </div>
            <div>
                <label for="district" class="block text-body font-medium">{{ __('District') }}</label>
                <input type="text" id="district" name="district" value="{{ old('district', $profile->district) }}"
                       class="mt-1 min-h-tap w-full rounded-control border border-ink/20 px-3 text-body focus:border-green focus:outline-none">
            </div>
        </div>

        <div class="grid gap-2">
            <label class="flex min-h-tap cursor-pointer items-center gap-3 text-body">
                <input type="checkbox" name="is_pwd" value="1" @checked(old('is_pwd', $profile->is_pwd))
                       class="h-5 w-5 rounded border-ink/30 text-green focus:ring-green">
                {{ __('Person with disability') }}
            </label>
            <label class="flex min-h-tap cursor-pointer items-center gap-3 text-body">
                <input type="checkbox" name="is_ex_serviceman" value="1" @checked(old('is_ex_serviceman', $profile->is_ex_serviceman))
                       class="h-5 w-5 rounded border-ink/30 text-green focus:ring-green">
                {{ __('Ex-serviceman') }}
            </label>
        </div>

        {{-- DPDP Act: the purpose is stated at the point of collection, in plain language,
             and we then honour it. No Aadhaar, no caste certificate, no full address. --}}
        <p class="rounded-control bg-paper px-3 py-2 text-meta text-muted">
            {{ __('We use these details only to check your eligibility. We never share them. You can download or delete your data at any time.') }}
        </p>

        <button type="submit" class="btn-primary w-full">{{ __('Save') }}</button>
    </form>

</div>
@endsection
