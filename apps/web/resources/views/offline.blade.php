{{--
    The offline fallback.

    Deliberately STANDALONE rather than extending the app layout. It sits outside the
    locale group so the service worker caches one copy instead of one per language, and the
    layout's navigation calls route() for locale-prefixed routes that have no locale here.

    It is also the one page that must render with no network at all, so it carries its own
    styles inline rather than waiting on a stylesheet that may not arrive.
--}}
<!DOCTYPE html>
<html lang="te">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0F6B4F">
    <title>Offline · Sadhana</title>

    <style>
        :root { color-scheme: light; }
        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background: #F7F8F5;
            color: #12211C;
            font-family: 'Noto Sans Telugu', system-ui, -apple-system, sans-serif;
            line-height: 1.85;
            text-align: center;
        }
        .card { max-width: 420px; }
        h1 { font-size: 20px; font-weight: 700; margin: 16px 0 8px; }
        p { font-size: 14px; color: #6B7C74; margin: 0 0 8px; }
        .actions { display: grid; gap: 8px; margin-top: 24px; }
        a {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 0 16px;
            border-radius: 9px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
        }
        .primary { background: #0F6B4F; color: #fff; }
        .secondary { background: #fff; color: #12211C; border: 1px solid rgba(18,33,28,.15); }
        .note { margin-top: 24px; font-size: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <div aria-hidden="true" style="font-size:40px">📴</div>

        <h1>ఇంటర్నెట్ కనెక్షన్ లేదు</h1>
        <p>You are offline.</p>
        <p>మీరు ఇంతకు ముందు తెరిచిన పేజీలు ఇప్పటికీ అందుబాటులో ఉన్నాయి.</p>

        {{-- Links to what is actually cached, rather than a retry button that will fail
             again. On a train the connection is not coming back in five seconds. --}}
        <div class="actions">
            <a class="primary" href="/te/quiz">రోజు ప్రశ్న</a>
            <a class="secondary" href="/te/notifications">నోటిఫికేషన్‌లు</a>
        </div>

        {{-- Said plainly, because it is the one guarantee that matters most here. --}}
        <p class="note">
            చివరి తేదీలు, అర్హత వివరాలు ఎప్పుడూ కాష్ నుంచి చూపించవు — అవి ఎప్పుడూ తాజాగా ఉండాలి.
        </p>
    </div>
</body>
</html>
