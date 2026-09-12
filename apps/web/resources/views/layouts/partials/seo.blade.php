{{--
    SEO head.

    hreflang points every locale at the SAME slug, which is why slugs are never translated.
    x-default goes to English as the fallback for a visitor whose language we do not serve.

    Google's rule is reciprocity: each alternate must point back at the others, or the
    whole cluster is ignored. Emitting the current locale in the list as well is correct
    and required.
--}}
@php
    $seo = $seo ?? [];
    $title = $seo['title'] ?? config('app.name');
    $description = $seo['description'] ?? __('Every government job notification for Telangana and Andhra Pradesh, in Telugu, checked against your own age, category and qualification.');
@endphp

<title>{{ $title }}{{ $title === config('app.name') ? '' : ' · Sadhana' }}</title>
<meta name="description" content="{{ $description }}">

@isset($seo['canonical'])
    <link rel="canonical" href="{{ $seo['canonical'] }}">
@endisset

@foreach ($seo['alternates'] ?? [] as $code => $url)
    <link rel="alternate" hreflang="{{ $code }}-IN" href="{{ $url }}">
@endforeach

@isset($seo['alternates']['en'])
    <link rel="alternate" hreflang="x-default" href="{{ $seo['alternates']['en'] }}">
@endisset

<meta property="og:site_name" content="Sadhana">
<meta property="og:title" content="{{ $title }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:type" content="{{ $seo['type'] ?? 'website' }}">
<meta property="og:locale" content="{{ app()->getLocale() }}_IN">
@isset($seo['canonical'])
    <meta property="og:url" content="{{ $seo['canonical'] }}">
@endisset

<meta name="twitter:card" content="summary_large_image">

{{-- Structured data. JSON_UNESCAPED_UNICODE matters: without it Telugu is emitted as
     \uXXXX escapes, which is valid JSON but unreadable when debugging a rich result. --}}
@isset($schema)
    <script type="application/ld+json">@json($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
@endisset

@isset($faqSchema)
    <script type="application/ld+json">@json($faqSchema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)</script>
@endisset
