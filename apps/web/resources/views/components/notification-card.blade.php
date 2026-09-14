@props(['n'])

{{--
    One notification in the feed.

    Deliberately shows vacancies and the last date on the card itself. Those are the two
    facts that decide whether someone taps through, and making them tap to find out costs
    a page load on a 3G connection.
--}}
<article class="card p-4">
    <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-card-title">
                <a href="{{ route('notifications.show', ['slug' => $n->slug]) }}"
                   class="hover:text-green hover:underline">{{ $n->title }}</a>
            </h3>
            <p class="mt-1 text-body text-muted">{{ $n->organisation }}</p>
        </div>

        <div class="shrink-0">
            <x-eligibility-badge :eligibility="$n->eligibility ?? ['status' => 'unknown']" />
        </div>
    </div>

    <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-1 text-body">
        @if ($n->total_vacancies)
            <div>
                <dt class="inline text-muted">{{ __('Vacancies') }}:</dt>
                <dd class="numeral inline font-semibold">{{ number_format($n->total_vacancies) }}</dd>
            </div>
        @endif

        @if ($n->min_qualification)
            <div>
                <dt class="inline text-muted">{{ __('Qualification') }}:</dt>
                <dd class="inline font-medium">{{ __($n->min_qualification) }}</dd>
            </div>
        @endif

        @if ($n->apply_end_date)
            <div>
                <dt class="inline text-muted">{{ __('Last date') }}:</dt>
                <dd class="numeral inline font-semibold {{ $n->isUrgent() ? 'text-danger' : '' }}">
                    {{ $n->apply_end_date->format('d M Y') }}
                </dd>
            </div>
        @endif
    </dl>

    <div class="mt-3 flex flex-wrap items-center gap-2">
        <a href="{{ route('notifications.show', ['slug' => $n->slug]) }}" class="btn-primary text-body">
            {{ __('Details') }}
        </a>

        {{-- Red only in the last three days, so it keeps its meaning. --}}
        @if ($n->isUrgent())
            <span class="badge-urgent">
                {{ trans_choice('{0} Last day|{1} :count day left|[2,*] :count days left', $n->daysLeft(), ['count' => $n->daysLeft()]) }}
            </span>
        @elseif ($n->isFresh())
            <span class="badge-eligible">{{ __('Posted today') }}</span>
        @endif
    </div>
</article>
