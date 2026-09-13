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
