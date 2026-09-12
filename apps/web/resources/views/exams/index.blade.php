@extends('layouts.app')

@section('content')
<h1 class="font-display text-screen-title">{{ __('All government exams') }}</h1>
<p class="mt-1 max-w-reading text-body text-muted">
    {{ __('Syllabus, exam pattern, previous papers and cutoffs for every major exam.') }}
</p>

@forelse ($categories as $category)
    @continue($category->exams->isEmpty())
    <section class="mt-8">
        <h2 class="font-display text-card-title text-ink-soft">{{ $category->name }}</h2>
        <div class="mt-3 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($category->exams as $exam)
                <a href="{{ route('exams.show', ['slug' => $exam->slug]) }}"
                   class="card flex items-center justify-between p-4 transition hover:border-green hover:bg-green-wash/40">
                    <span>
                        <span class="block text-card-title">{{ $exam->name }}</span>
                        <span class="block text-meta text-muted">{{ $exam->conducting_body }}</span>
                    </span>
                    <span class="text-muted" aria-hidden="true">›</span>
                </a>
            @endforeach
        </div>
    </section>
@empty
    <p class="card mt-6 p-8 text-center text-body text-muted">{{ __('Exams are being added.') }}</p>
@endforelse
@endsection
