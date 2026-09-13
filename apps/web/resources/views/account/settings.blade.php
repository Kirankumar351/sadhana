@extends('layouts.app')

@section('content')
<h1 class="mx-auto max-w-2xl font-display text-screen-title">{{ __('Settings') }}</h1>

<div class="mt-5">
    @livewire('account.settings')
</div>
@endsection
