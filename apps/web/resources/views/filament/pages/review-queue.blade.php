<x-filament-panels::page>

    @if ($current === null)
        <div class="rounded-xl border border-gray-200 bg-white p-8 text-center dark:border-white/10 dark:bg-gray-900">
            <p class="text-lg font-medium">{{ __('The queue is empty.') }}</p>
            <p class="mt-1 text-sm text-gray-500">
                {{ __('Every scraped notification has been reviewed. The scrapers run hourly.') }}
            </p>
        </div>
    @else

        {{-- The SLA is a business number, not a courtesy: being first to publish is most of
             why we rank for a new notification, and position lost is not recovered later. --}}
        @if ($pastSla > 0)
            <div class="rounded-xl border border-danger-300 bg-danger-50 p-4 text-danger-800
                        dark:border-danger-500/30 dark:bg-danger-500/10 dark:text-danger-300">
                <p class="font-semibold">
                    {{ trans_choice('{1} :count item is past SLA.|[2,*] :count items are past SLA.', $pastSla, ['count' => $pastSla]) }}
                </p>
                <p class="mt-1 text-sm">
                    {{ __('Being first to publish is most of why we rank on Google for a new notification. Every hour late costs position.') }}
                </p>
            </div>
        @endif

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">
                @if ($position)
                    {{ __(':position of :total', ['position' => $position, 'total' => $queue->count()]) }} ·
                @endif
                {{ __('scraped :age', ['age' => $current->created_at->diffForHumans()]) }}
            </p>

            <div class="flex flex-wrap gap-2">
                <x-filament::button wire:click="skip" color="gray" size="sm">
                    {{ __('Skip for now') }}
                </x-filament::button>

                <x-filament::button wire:click="reject" color="danger" size="sm"
                                    wire:confirm="{{ __('Reject this notification?') }}">
                    {{ __('Reject') }}
                </x-filament::button>

                <x-filament::button wire:click="publish" color="success" size="sm">
                    {{ __('Approve and publish') }}
                </x-filament::button>
            </div>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">

            {{-- ------------------------------------------------ extracted fields --}}
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <h2 class="text-base font-semibold">{{ __('Extracted fields') }}</h2>

                {{-- The extractor never guesses a date. What it left blank is exactly what a
                     person has to read off the source — so it is called out, not hidden. --}}
                @if ($missing !== [])
                    <div class="mt-3 rounded-lg border border-warning-300 bg-warning-50 p-3 text-sm
                                dark:border-warning-500/30 dark:bg-warning-500/10">
                        <p class="font-semibold">{{ __('Left blank by the extractor — it never guesses') }}</p>
                        <ul class="mt-1 list-inside list-disc">
                            @foreach ($missing as $field)
                                <li>{{ $field }}</li>
                            @endforeach
                        </ul>
                        <p class="mt-2">
                            {{ __('Fill these in the editor before publishing. A wrong reference date does not look like an error — it just quietly tells the wrong people they are ineligible.') }}
                        </p>
                    </div>
                @endif

                <dl class="mt-3 divide-y divide-gray-100 text-sm dark:divide-white/5">
                    @foreach ([
                        __('Title') => $current->title,
                        __('Organisation') => $current->organisation,
                        __('Vacancies') => $current->total_vacancies ? number_format($current->total_vacancies) : null,
                        __('Qualification') => $current->min_qualification,
                        __('Age') => $current->min_age && $current->max_age ? $current->min_age.'–'.$current->max_age : null,
                        __('Age reference date') => $current->age_reference_date?->format('d/m/Y'),
                        __('Applications open') => $current->apply_start_date?->format('d/m/Y'),
                        __('Last date') => $current->apply_end_date?->format('d/m/Y'),
                        __('Exam date') => $current->exam_date?->format('d/m/Y'),
                    ] as $label => $value)
                        <div class="flex justify-between gap-4 py-2">
                            <dt class="text-gray-500">{{ $label }}</dt>
                            <dd class="text-end font-medium {{ blank($value) ? 'text-danger-600' : '' }}">
                                {{ blank($value) ? __('Not extracted') : $value }}
                            </dd>
                        </div>
                    @endforeach
                </dl>

                <x-filament::button
                    tag="a"
                    href="{{ \App\Filament\Resources\ExamNotificationResource::getUrl('edit', ['record' => $current]) }}"
                    color="gray" size="sm" class="mt-3">
                    {{ __('Open the full editor') }}
                </x-filament::button>

                <div class="mt-5 border-t border-gray-100 pt-4 dark:border-white/5">
                    <h3 class="text-sm font-semibold">{{ __('Confirm before publishing') }}</h3>
                    <p class="mt-1 text-xs text-gray-500">
                        {{ __('These four are the ones that mis-serve people silently when they are wrong.') }}
                    </p>

                    <div class="mt-2 grid gap-2 text-sm">
                        @foreach ([
                            'dates' => __('All dates match the official PDF'),
                            'eligibility' => __('Eligibility and age relaxation match'),
                            'fees' => __('Fee amounts match'),
                            'link' => __('The official link opens correctly'),
                        ] as $key => $label)
                            <label class="flex items-center gap-2">
                                <input type="checkbox" wire:model.live="confirmed.{{ $key }}"
                                       class="rounded border-gray-300 text-primary-600">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </section>

            {{-- ------------------------------------------------------- the source --}}
            <section class="rounded-xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="text-base font-semibold">{{ __('Source') }}</h2>

                    @if ($current->official_pdf_url || $current->source_url)
                        {{-- Beside the form, never in another tab: a reviewer who loses their
                             place starts trusting the extraction instead of checking it. --}}
                        <a href="{{ $current->official_pdf_url ?? $current->source_url }}"
                           target="_blank" rel="noopener"
                           class="text-sm font-medium text-primary-600 hover:underline">
                            {{ __('Open the original') }} ↗
                        </a>
                    @endif
                </div>

                @if ($current->source)
                    <p class="mt-1 text-xs text-gray-500">{{ $current->source->name }}</p>
                @endif

                {{-- The links a student will click. Checked here, before publishing, because a
                     dead apply link on a live notification is the complaint that arrives
                     first and costs the most trust. --}}
                @if ($current->apply_url || $current->registration_url)
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs">
                        @if ($current->registration_url)
                            <a href="{{ $current->registration_url }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">
                                {{ __('Registration link') }} ↗
                            </a>
                        @endif
                        @if ($current->apply_url && $current->apply_url !== $current->registration_url)
                            <a href="{{ $current->apply_url }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">
                                {{ __('Apply link') }} ↗
                            </a>
                        @endif
                    </div>
                @endif

                @if ($current->official_pdf_url && str_ends_with(strtolower($current->official_pdf_url), '.pdf'))
                    <iframe src="{{ $current->official_pdf_url }}"
                            class="mt-3 h-[36rem] w-full rounded-lg border border-gray-200 dark:border-white/10"
                            title="{{ __('Source document') }}"></iframe>
                @else
                    <div class="mt-3 max-h-[36rem] overflow-y-auto rounded-lg border border-gray-200 p-3 text-sm leading-relaxed dark:border-white/10">
                        {{ $current->description ?? __('No extracted text stored for this item. Open the original.') }}
                    </div>
                @endif
            </section>
        </div>

        {{-- ------------------------------------------------------ queue movement --}}
        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="text-sm text-gray-500">
                @if ($nextId)
                    {{ __('Next in queue: :title', ['title' => \Illuminate\Support\Str::limit($queue->firstWhere('id', $nextId)?->title ?? '', 60)]) }}
                @else
                    {{ __('Last in the queue.') }}
                @endif
            </p>

            <div class="flex gap-2">
                <x-filament::button wire:click="open({{ $previousId ?? 0 }})" color="gray" size="sm"
                                    :disabled="! $previousId">
                    {{ __('Previous') }}
                </x-filament::button>

                <x-filament::button wire:click="open({{ $nextId ?? 0 }})" color="gray" size="sm"
                                    :disabled="! $nextId">
                    {{ __('Next item') }}
                </x-filament::button>
            </div>
        </div>
    @endif

</x-filament-panels::page>
