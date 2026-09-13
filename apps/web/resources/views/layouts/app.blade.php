<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ config('locales.supported.'.app()->getLocale().'.dir', 'ltr') }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#0F6B4F">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- Self-hosted, subset Telugu font. Preloaded because it is on every page and the
         alternative is a visible reflow on a 3G connection. --}}
    <link rel="preload" href="/fonts/noto-sans-telugu-subset.woff2" as="font" type="font/woff2" crossorigin>

    @include('layouts.partials.seo')

    <link rel="manifest" href="/manifest.json">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>

<body class="min-h-screen bg-paper pb-20 md:pb-0">

    {{-- ------------------------------------------------------------------ header --}}
    <header class="sticky top-0 z-40 border-b border-ink/10 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-5xl items-center gap-3 px-4 py-3">

            <a href="{{ route('home') }}" class="flex items-center gap-2 font-display text-lg font-extrabold text-green">
                <span class="grid h-8 w-8 place-items-center rounded-control bg-green text-white">స</span>
                <span>Sadhana</span>
            </a>

            <nav class="ml-4 hidden items-center gap-1 md:flex">
                @php
                    $nav = [
                        ['route' => 'notifications.index', 'label' => __('Notifications')],
                        ['route' => 'exams.index',         'label' => __('Exams')],
                        ['route' => 'quiz.today',          'label' => __('Daily quiz')],
                        ['route' => 'flashcards',          'label' => __('Flashcards'), 'auth' => true],
                        ['route' => 'material.index',     'label' => __('Material')],
                        ['route' => 'community.index',    'label' => __('Doubts')],
                        ['route' => 'ask',                'label' => __('Ask')],
                        ['route' => 'billing.plans',      'label' => __('Premium')],
                    ];
                @endphp
                @foreach ($nav as $item)
                    @continue(($item['auth'] ?? false) && ! auth()->check())
                    <a href="{{ route($item['route']) }}"
                       class="tap rounded-control px-3 text-body font-medium transition
                              {{ request()->routeIs($item['route']) ? 'bg-green-wash text-green' : 'text-ink-soft hover:bg-ink/5' }}">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </nav>

            <div class="ml-auto flex items-center gap-2">

                <a href="{{ route('search') }}" aria-label="{{ __('Search') }}"
                   class="tap rounded-control px-2 text-lg text-ink-soft hover:bg-ink/5">🔍</a>

                {{-- Ask sits beside search because it is the same intent -- "I have a
                     question" -- expressed two ways. A person who cannot find something
                     in search should fall into it naturally. --}}
                <a href="{{ route('ask') }}" aria-label="{{ __('Ask Sadhana') }}"
                   class="tap rounded-control px-2 text-lg text-green hover:bg-green-wash">✦</a>


                {{-- Language switch. For this audience it is not a setting, it is the
                     product, so it stays visible on every screen rather than being
                     buried in account settings. --}}
                <div class="flex rounded-pill border border-ink/15 p-0.5 text-meta">
                    @foreach (\App\Support\Locale::active() as $code)
                        <a href="{{ route(request()->route()?->getName() ?? 'home', array_merge(request()->route()?->parameters() ?? [], ['locale' => $code])) }}"
                           class="rounded-pill px-2.5 py-1 font-semibold transition
                                  {{ app()->getLocale() === $code ? 'bg-green text-white' : 'text-muted hover:text-ink' }}">
                            {{ $code === 'te' ? 'తె' : strtoupper($code) }}
                        </a>
                    @endforeach
                </div>

                @auth
                    @if ($streak = auth()->user()->streak)
                        <span class="hidden items-center gap-1 rounded-pill bg-marigold-wash px-2.5 py-1 text-meta font-semibold text-ink sm:inline-flex">
                            🔥 <span class="numeral">{{ $streak->current_streak }}</span>
                        </span>
                    @endif
                    <a href="{{ route('dashboard') }}" class="tap rounded-control px-3 text-body font-medium text-ink-soft hover:bg-ink/5">
                        {{ __('Dashboard') }}
                    </a>
                @else
                    <a href="{{ route('login') }}" class="btn-primary text-body">{{ __('Sign in') }}</a>
                @endauth
            </div>
        </div>
    </header>

    {{-- ------------------------------------------------------------------ content --}}
    <main class="mx-auto max-w-5xl px-4 py-5">
        @if (session('status'))
            <div class="mb-4 rounded-card bg-green-wash px-4 py-3 text-body text-green">
                {{ session('status') }}
            </div>
        @endif

        {{ $slot ?? '' }}
        @yield('content')
    </main>

    {{-- ------------------------------------------------------------------ footer --}}
    <footer class="mt-10 border-t border-ink/10 bg-white">
        <div class="mx-auto max-w-5xl px-4 py-6 text-meta text-muted">
            <p class="max-w-reading">
                {{ __('Sadhana is an information service. Always confirm details against the official notification before applying.') }}
            </p>
            <p class="mt-2">© <span class="numeral">{{ date('Y') }}</span> Sadhana</p>
        </div>
    </footer>

    {{-- ------------------------------------------------- bottom nav (mobile only) --}}
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-ink/10 bg-white md:hidden"
         style="padding-bottom: env(safe-area-inset-bottom)">
        <div class="grid grid-cols-5">
            @php
                $tabs = [
                    ['route' => 'notifications.index', 'label' => __('Feed'),  'icon' => '📋'],
                    ['route' => 'exams.index',         'label' => __('Exams'), 'icon' => '🎓'],
                    ['route' => 'quiz.today',          'label' => __('Quiz'),  'icon' => '✏️'],
                    ['route' => 'ask',                 'label' => __('Ask'),   'icon' => '✦'],
                    ['route' => auth()->check() ? 'dashboard' : 'home', 'label' => __('You'), 'icon' => '👤'],
                ];
            @endphp
            @foreach ($tabs as $tab)
                <a href="{{ route($tab['route']) }}"
                   class="flex min-h-tap flex-col items-center justify-center gap-0.5 py-2 text-meta
                          {{ request()->routeIs($tab['route']) ? 'text-green' : 'text-muted' }}">
                    <span aria-hidden="true">{{ $tab['icon'] }}</span>
                    <span>{{ $tab['label'] }}</span>
                </a>
            @endforeach
        </div>
    </nav>

    @livewireScripts
</body>
</html>
