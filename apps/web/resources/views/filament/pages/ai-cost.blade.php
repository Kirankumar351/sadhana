<x-filament-panels::page>

    @php
        $rupees = fn (int $paise): string => '₹'.number_format($paise / 100, 2);
        $overBudget = $perUserPaise > $budgetPaise;
    @endphp

    {{-- The headline number, with the threshold beside it. A cost with no comparison is
         just a number nobody acts on. --}}
    <div class="grid gap-4 sm:grid-cols-4">
        <x-filament::section>
            <x-slot name="heading">This month</x-slot>
            <p class="text-3xl font-bold">{{ $rupees($totalPaise) }}</p>
            <p class="text-sm text-gray-500">{{ number_format($activeUsers) }} active users</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Per active user</x-slot>
            <p @class([
                'text-3xl font-bold',
                'text-danger-600' => $overBudget,
                'text-success-600' => ! $overBudget,
            ])>{{ $rupees($perUserPaise) }}</p>
            <p class="text-sm text-gray-500">Budget {{ $rupees($budgetPaise) }}</p>
        </x-filament::section>

        {{-- Caching by CONTENT rather than by user is the decision that makes this layer
             affordable. When cost drifts up, this is the first number to check. --}}
        <x-filament::section>
            <x-slot name="heading">Cache hit rate</x-slot>
            <p class="text-3xl font-bold">{{ $cacheHitRate }}%</p>
            <p class="text-sm text-gray-500">One generation serves many</p>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">Refusals</x-slot>
            <p class="text-3xl font-bold">{{ number_format($refusals->sum('total')) }}</p>
            <p class="text-sm text-gray-500">This is the product working</p>
        </x-filament::section>
    </div>

    @if ($overBudget)
        <x-filament::section>
            <x-slot name="heading">Over budget</x-slot>

            <p class="text-sm">
                AI is costing {{ $rupees($perUserPaise) }} per active user against a
                {{ $rupees($budgetPaise) }} budget. The free product only works while this
                stays small — Vol 1's economics depend on serving the 95% who never pay at
                almost no cost.
            </p>

            <p class="mt-2 text-sm font-medium">What to check, in order:</p>
            <ol class="mt-1 list-inside list-decimal text-sm text-gray-600">
                <li>Cache hit rate. A sudden drop usually means a prompt version changed and invalidated it.</li>
                <li>One user with abnormal volume — that is what the per-user caps exist for.</li>
                <li>Answer evaluation and question generation are the expensive features. Evaluation belongs behind premium.</li>
            </ol>

            <p class="mt-2 text-sm text-gray-600">
                Ask Sadhana stays free regardless. It is the reason people come back, and
                gating the habit to save money would cost far more than it saves.
            </p>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">By feature</x-slot>

        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-xs uppercase text-gray-500">
                    <tr class="border-b">
                        <th class="py-2 text-start">Feature</th>
                        <th class="py-2 text-end">Requests</th>
                        <th class="py-2 text-end">Cache</th>
                        <th class="py-2 text-end">Refused</th>
                        <th class="py-2 text-end">Cost</th>
                        <th class="py-2 text-end">Per request</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($byFeature as $row)
                        @php
                            $perRequest = $row->requests > 0 ? $row->cost_paise / $row->requests : 0;
                        @endphp
                        <tr class="border-b border-gray-100">
                            <td class="py-2 font-medium">{{ $row->feature }}</td>
                            <td class="py-2 text-end">{{ number_format($row->requests) }}</td>
                            <td class="py-2 text-end">
                                {{ $row->requests > 0 ? round($row->cache_hits / $row->requests * 100).'%' : '—' }}
                            </td>
                            <td class="py-2 text-end">{{ number_format($row->refusals) }}</td>
                            <td class="py-2 text-end">{{ $rupees((int) $row->cost_paise) }}</td>
                            {{-- Per-request cost is what identifies the feature to move
                                 behind premium. Anything near a rupee a call cannot be
                                 free at volume. --}}
                            <td @class(['py-2 text-end', 'text-danger-600 font-semibold' => $perRequest > 50])>
                                ₹{{ number_format($perRequest / 100, 3) }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-500">
                                No AI requests yet this month.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    @if ($refusals->isNotEmpty())
        <x-filament::section>
            <x-slot name="heading">Why the assistant declined</x-slot>

            {{-- Framed as health, not failure. A visible refusal costs far less trust than
                 a fluent wrong answer, and these counts are the evidence the guardrails
                 are earning their place. --}}
            <p class="text-sm text-gray-600">
                Every one of these got something better than a guess — a deterministic
                eligibility check, real cutoff history, or an honest "ask the community".
            </p>

            <ul class="mt-3 grid gap-1 text-sm">
                @foreach ($refusals as $refusal)
                    <li class="flex justify-between border-b border-gray-100 py-1">
                        <span>{{ str_replace('_', ' ', $refusal->refused_reason) }}</span>
                        <span class="font-semibold">{{ number_format($refusal->total) }}</span>
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

</x-filament-panels::page>
