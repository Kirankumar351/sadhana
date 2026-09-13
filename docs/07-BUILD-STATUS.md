# Build status

**As of 2026-09-13.** What exists, what is verified, and what is not.

Written to be read by someone deciding what to do next — not as a progress report. The
"not built" section is the useful one.

---

## Verified working

**236 tests, 357 assertions, all passing.** 28 public routes and 30 admin screens render
against a running server in both locales.

| Area | State |
|---|---|
| Schema | 15 migrations, 85 tables, 150 indexes |
| Models | 74 |
| Student portal | 28 routes, bilingual |
| Admin portal | 30 screens across 8 navigation groups |
| AI layer | Gateway, guards, corpus, Ask Sadhana |
| Agents | Runtime, tool registry, memory, 10 catalogued — all disabled |
| Commerce | Razorpay, entitlements, invoices, coupons |
| Push | FCM + WhatsApp, budget, waves |
| PWA | Service worker, offline, manifest |
| DPDP | Export and erasure both work |

### The rules that are enforced in code, not documentation

Each of these has a test that fails if someone removes it.

| Rule | Where |
|---|---|
| Eligibility is deterministic; the model is never asked | `IntentRouter` |
| A date in no retrieved passage is stripped **and** drops confidence below the floor | `OutputGuard` |
| An agent cannot be given a tool that writes an owner table | `ToolRegistry` |
| Five capabilities can never be gated — the gate **throws** | `FeatureGate` |
| AI answers rank below every human and can never be accepted | `ReputationService` |
| No uploaded file is reachable before human approval | `UploadNotes` |
| Never publish a short quiz — alert instead | `PublishDailyQuiz` |
| Five pushes a day, only a deadline passes a full budget | `PushBudget` |
| Payment fulfilment is idempotent | `CheckoutService` |
| Nothing outside `Services\AI` may call a model provider | architecture test |

---

## Bugs found by building it

Recorded because each one was invisible on paper and would have been expensive in production.

| # | Bug | Consequence if shipped |
|---|---|---|
| 1 | Duplicate index names across tables | Schema would not build on PostgreSQL |
| 2 | `AUTOINCREMENT` on a composite primary key | `ad_events` unusable outside MySQL |
| 3 | MySQL-only SQL running unconditionally | Entire test suite impossible |
| 4 | FK guessed `exam_category_id`, column is `category_id` | Exam directory 500s |
| 5 | `route('login')` resolved before locale defaults | Every guarded route threw |
| 6 | Reindex threw with no API key | Content editors could not save |
| 7 | Qdrant delete failure aborted a purge | **Material stayed retrievable after takedown** |
| 8 | Filament resolves closures by parameter **name** | Table could not resolve its model |
| 9 | `StreakMilestoneReached` dispatched, never existed | Fatal on every 7-day streak |
| 10 | Vote change moved the score by one, not two | Wrong scores, wrong reputation |
| 11 | **`posts.user_id` was NOT NULL** | **Erasure policy unimplementable** |

Number 11 is the one worth remembering: the DPDP erasure policy was written, agreed and
documented — and the schema made it impossible. Only writing the test found it.

---

## Not built

### Deliberately, per the roadmap

**Study circles.** Tables and models exist, no UI. Phase 2, and lower value than the
community it depends on.

**Flashcards and study plans.** Schema, models and the AI features exist; no screens.
Both need 30+ days of real quiz history to produce anything but noise.

**Mock interview and answer evaluation.** Phase 3, behind premium. ~₹0.93 and ~₹1.10 per
use — they need the Group 1 cohort to exist first.

### Because it needs a human decision

**Telugu fonts.** `public/fonts/README.md` has the exact `pyftsubset` commands. Until
added, Telugu falls back to a system font — acceptable in development, **not at launch**,
because the fallback on a budget Android often has poor Telugu hinting.

**PWA icons.** `public/icons/README.md` specifies sizes and the maskable safe zone.

**Scrape sources are seeded INACTIVE.** Pointing a crawler at nine government sites should
be a decision taken when someone is ready to watch the review queue.

**Every agent ships disabled.** Turning one on should follow an eval run.

**`COMMERCE_GATES_OPEN=true`.** Nothing is withheld. Closing a gate is a dated, reasoned
product decision — see `04-COMMERCE.md` §9.

---

## The largest untested surface

**Everything has been verified on SQLite. Nothing has ever run against MySQL.**

Three pieces of schema exist only on the MySQL path and have therefore **never executed**:

1. The `FULLTEXT` index on `posts` — the search-before-you-ask fallback
2. `ad_events` `PARTITION BY RANGE` — the table that grows fastest
3. The 191-character prefix index on `push_tokens.token`

MySQL 8.0.45 is installed and running locally. This needs one credential to close.

```bash
cd apps/web
# set DB_CONNECTION=mysql and the credentials in .env
php artisan migrate:fresh --seed
php artisan test
```

Until that runs, treat the MySQL-specific schema as unverified.

---

## Before launch

From Vol 2 ch.19, filtered to what is actually outstanding:

**Content** — 30 exam hubs in both languages · 12 months of historical notifications ·
500 questions (the quiz needs 10 verified Telugu questions *every* morning; the pool
currently holds 10 in total, which is one day) · 50 official material PDFs

**Technical** — MySQL verification · fonts and icons · load test at 50x · Sentry and
uptime alerts routed to a phone · LCP under 2.5s on a real budget Android on 3G

**Legal** — privacy policy and terms published in both languages · copyright takedown
process with a real contact address

**Growth** — WhatsApp channels seeded before launch · Search Console verified per locale ·
20 beta users onboarded in person

---

## The gate that still stands

**Decision D11: if D7 retention is below 20% at Week 14, stop all feature work and fix the
loop.**

Phase 2 code now exists ahead of that gate, at the owner's explicit direction. That does
not move the gate. Shipping these modules to users before retention is proven would still
be building on a foundation that has not held weight — the code being ready changes what is
possible, not what is wise.
