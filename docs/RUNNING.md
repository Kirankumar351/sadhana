# Running Sadhana locally

Verified working on this machine on 14 September 2026: MySQL 8.0.45, PHP 8.2.12,
Node 24.13.1, npm 11.8.0.

---

## One-time setup

Already done on this machine. Repeat only on a fresh clone.

```bash
cd D:/Sadhana/apps/web

composer install
npm install

cp .env.example .env          # then edit the DB block below
php artisan key:generate
php artisan storage:link
```

`.env` database block — MySQL, not SQLite:

```ini
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sadhana
DB_USERNAME=root
DB_PASSWORD=12345
```

Create the schema once (Workbench, or this one-liner):

```bash
php -r "\$p=new PDO('mysql:host=127.0.0.1;port=3306','root','12345'); \$p->exec('CREATE DATABASE IF NOT EXISTS sadhana CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); echo \"ok\n\";"
```

---

## Start the app

Port 8000 is already taken on this machine by another project, so use **8001**.

Two terminals, both from `D:/Sadhana/apps/web`:

```bash
# terminal 1 — the app
php artisan serve --host=127.0.0.1 --port=8001
```

```bash
# terminal 2 — assets, with hot reload
npm run dev
```

Then open **http://127.0.0.1:8001** — it redirects to `/te`.

A third terminal is needed for anything queued (AI answers, push, flashcard
building). Without it those jobs sit in the `jobs` table and nothing appears to
happen:

```bash
php artisan queue:work --queue=push,high,default,low
```

And a fourth if you want the 7 AM quiz publish, scrapers and digests to fire:

```bash
php artisan schedule:work
```

---

## Pulling real notifications

Sources live in **Admin → System → Scrape Sources**. Each board has its own
parser, because each publishes differently: TGPSC and APPSC as PDFs behind a
listing, SSC through a JSON API, IBPS as links to its registration portal, RRB
as notice cards, TGPRB inside a JavaScript bundle.

**Automatic:** keep `schedule:work` and `queue:work` (above) running. Active
sources are checked on their own frequency — TGPSC and APPSC every 15 minutes.

**Right now, from a terminal:**

```bash
php artisan scrape:run --sync --force               # every active source
php artisan scrape:run --sync --force --source=TGPSC  # one source
```

**Right now, from the admin panel:** the **Pull now** button on each row.

Everything pulled lands in **Admin → Review Queue** as a draft. **Nothing is
published automatically** — a person checks the dates and links against the
official PDF and publishes. Notifications whose application window has already
closed are counted as "skipped already closed" and not queued.

Without an Anthropic key, dates, vacancies and age limits are read directly from
the labelled lines in each notification ("Last Date … 22/08/2026"). With a key,
the model fills in the rest; values it cannot ground in the page are dropped.

PDFs are read with Poppler's `pdftotext`, which ships with Git for Windows. Set
its full path in `.env` (on Linux, install `poppler-utils` and leave the default):

```ini
PDFTOTEXT_PATH="C:/Program Files/Git/mingw64/bin/pdftotext.exe"
```

Use forward slashes. Backslashes inside double quotes are escape sequences to
the `.env` parser, and one bad line stops the whole application from booting.

Without it ingestion still works, using a slower PHP parser.

APPSC's server sends an incomplete certificate chain. The missing GlobalSign
intermediate is supplied from `resources/certs` — verification stays on. Never
"fix" a certificate error by turning verification off.

---

## AI features

Every AI feature needs `ANTHROPIC_API_KEY` in `.env`. Without it they answer
"temporarily unavailable" and hand over to the community — nothing crashes.

```ini
ANTHROPIC_API_KEY=sk-ant-...
AI_MODEL_LARGE=claude-sonnet-5
AI_MODEL_SMALL=claude-haiku-4-5
```

Run `php artisan config:clear` after setting it.

`GEMINI_API_KEY` works too. Whichever key is set is the provider used; with both,
`AI_PROVIDER=auto` picks Claude and `AI_PROVIDER=gemini` picks Gemini.

### Ask Sadhana, Explain and the doubt solver need the vector store

They search Sadhana's own material before answering, and that search runs on Qdrant.
Without it they reply "not available right now". The Windows build lives in
`infra/qdrant` (not committed); download it once:

```bash
cd D:/Sadhana/infra/qdrant
curl -L -o qdrant.zip https://github.com/qdrant/qdrant/releases/download/v1.19.1/qdrant-x86_64-pc-windows-msvc.zip
unzip qdrant.zip && rm qdrant.zip
```

Then, each time, in its own terminal:

```bash
cd D:/Sadhana/infra/qdrant
QDRANT__TELEMETRY_DISABLED=true QDRANT__STORAGE__STORAGE_PATH=./storage ./qdrant.exe
```

Embed the corpus once Qdrant and a key are both available (the queue worker must be
running, or run the worker line below once):

```bash
php artisan corpus:reindex --stale
php artisan queue:work --queue=low --stop-when-empty
php artisan corpus:reindex            # shows how many chunks are still awaiting embedding
```

New and edited content is embedded automatically by the queue worker. Doubt answers are
queued too, so keep `queue:work` running for the doubt solver to reply.

The Python AI service (`apps/ai`) listens on **8100** so it never collides with
the web app on 8001:

```bash
cd D:/Sadhana/apps/ai
uvicorn sadhana_ai.main:app --host 127.0.0.1 --port 8100
```

---

## Sign in

| | |
|---|---|
| Admin panel | http://127.0.0.1:8001/admin |
| Email | `admin@sadhana.test` |
| Password | `password` |

The student side uses phone OTP, no password. In `local` the code is written to
`storage/logs/laravel.log` rather than sent — grep for it after requesting one.

---

## Database

```bash
php artisan migrate                 # apply new migrations
php artisan migrate:status          # what has and has not run
php artisan migrate:fresh --seed    # WIPES sadhana, rebuilds, reseeds
php artisan db:seed                 # reseed without dropping
php artisan db:show                 # connection, table count, size
```

`migrate:fresh` drops every table in the `sadhana` schema. It does not touch your
other databases, but there is no undo.

After seeding you have: 5 exams, 5 notifications, 10 approved questions, 1 daily
quiz, 77 glossary terms, 9 scrape sources (all inactive), 10 agents (all
inactive), 3 plans, 1 admin user.

Scrapers and agents are seeded **inactive on purpose**. A scraper with nobody
watching the review queue fills it with unverified notifications; an agent that
has not been evaluated should not be acting. Enable them in the admin panel.

---

## Build and checks

```bash
npm run build                       # production assets + the bundle budget check
./vendor/bin/pest                   # 366 tests, runs on in-memory SQLite
./vendor/bin/pest --filter=Flashcard
./vendor/bin/pint                   # format
./vendor/bin/pint --test            # check formatting without writing
./vendor/bin/phpstan analyse        # static analysis
```

Tests deliberately run on SQLite for speed. The MySQL-only parts of the schema —
the FULLTEXT index on `posts`, `ad_events` partitioning, the 191-char prefix
index on `push_tokens` — are skipped there, which is why they went unexercised
until the first real `migrate` on MySQL. All three are confirmed present now.

---

## When something looks stale

```bash
php artisan optimize:clear          # config, routes, views, events, cache
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

Run `optimize:clear` after editing `.env` or `lang/te.json`. Translations and
config are cached, and a missing `route:clear` after adding a route is the usual
cause of "my new page 404s".

---

## Useful during development

```bash
php artisan route:list                        # every route
php artisan route:list --path=notes           # just these
php artisan queue:failed                      # jobs that died
php artisan queue:retry all
tail -f storage/logs/laravel.log
```

---

## Not yet configured

These are absent rather than broken. The app runs without them; the features
that need them degrade rather than crash.

| Missing | Effect |
|---|---|
| `ANTHROPIC_API_KEY` | Every AI feature returns "temporarily unavailable" and hands over to the community. Nothing errors. |
| Qdrant | Retrieval finds nothing, so the assistant refuses to answer rather than guessing. Correct behaviour, no content. |
| Redis | `.env` uses the database driver for cache, queue and session. Fine locally; Redis is needed for leaderboards at scale. |
| Meilisearch | Search falls back to SQL `LIKE`. Works, slower, no typo tolerance. |
| Telugu font subset | Telugu renders in a system fallback font. Exact build command is in `public/fonts/README.md`. |
| PWA icons | Install prompt shows no icon. Commands in `public/icons/README.md`. |
