# Build order

Vol 3 organised 62 tasks by week. Vol 4 organised the same 62 by module. This organises them
by **dependency**, which is what actually governs, and folds in the corrected AI sequencing
from the Integration Map.

**Module order is not free order.** M0 must be complete before anything else compiles.

```
                    ┌──────────────────┐
                    │  M0 FOUNDATION   │
                    └────────┬─────────┘
                             │
              ┌──────────────┼──────────────┐
              ▼              ▼              ▼
        ┌──────────┐  ┌────────────┐  ┌──────────┐
        │ M1 EXAM  │  │ M2 NOTIFS  │  │ M4 QUIZ  │
        │   HUB    │─►│ ELIGIBILITY│  │ STREAKS  │
        └────┬─────┘  └─────┬──────┘  └────┬─────┘
             │              │              │
             │              ▼              ▼
             │        ┌──────────┐   ┌──────────┐
             │        │ M3 INGEST│   │ M5 PUSH  │
             │        └──────────┘   └──────────┘
             │
             ▼
        ┌─────────────────────────────┐
        │ CORPUS (ai_chunks) ─► AI    │  ◄── needs M1 populated first
        └─────────────────────────────┘
```

---

## Sprint 0 — Foundation (weeks 1–2)

**Do the competitor teardown first.** Install Adda247, Testbook and Sakshi on a real budget
Android. Complete every signup flow. Set the language to Telugu everywhere it is offered and
**note every place it silently falls back to English — those failures are your feature list.**
Budget one week. It saves two months.

| Task | Note |
|---|---|
| Repo, Laravel 12, Docker, CI skeleton | `make up && make fresh` |
| **All migrations** | Already written — 15 files in `apps/web/database/migrations` |
| Models, relationships, factories, seeders | |
| **`glossary` seeded with ~142 terms** | **Do this before any AI work.** Half a day, ten features depend on it. |
| i18n: config, routing, `SetLocale`, language files | |
| Telugu typography — self-hosted subset Noto Sans Telugu | ~60 KB subset, not the 300 KB full font |
| Filament install, first resources | |
| Design system, tested **with Telugu text** | 9 colours, 48px minimum tap target |
| Auth: phone OTP, profile with eligibility fields | |

**Exit gate:** an admin can create a bilingual exam and it renders correctly in Telugu on a
real phone.

---

## Sprint 1 — Exam Hub (weeks 3–4)

The SEO engine. **Treat it as a product, not as content** — 60% of Year 1 acquisition comes
from these pages.

Eleven sections, in a fixed order that does not vary page to page. Consistency is what
teaches Google what the template means.

| Task |
|---|
| Exam listing and detail, all 11 sections |
| SEO: meta, canonical, hreflang, per-locale sitemaps |
| Structured data: `Course`, `FAQPage` |
| Filament resources for syllabus, pattern, cutoffs |
| Cloudflare caching + purge-on-save observer |
| **Content sprint: populate 10 exams fully, both languages** |

**Exit gate:** 10 exam pages live and indexable in both languages.

---

## Sprint 2 — Notifications and ingestion (weeks 5–7)

The core value proposition.

| Task |
|---|
| Notification model, admin CRUD, publish workflow |
| **`EligibilityService` with 95% test coverage** |
| Feed component with filters and pagination |
| Detail page with `JobPosting` structured data |
| Save and remind |
| Meilisearch, per-locale indexes |
| First two scrapers (TGPSC, APPSC) + review queue |
| **AI: notification extraction** — never guesses a date, always lands in review |
| **AI: translation drafting** — now has a glossary to use |
| **Content sprint: 12 months of historical notifications** |

**Always keep a manual entry path.** If a scraper breaks on notification day, a human must be
able to publish in five minutes through Filament.

**Exit gate:** a user sets a profile and sees a personalised, eligibility-tagged feed.

---

## Sprint 3 — Quiz, streaks, push (weeks 8–10)

The retention engine. **If this does not work, everything else is wasted.**

| Task |
|---|
| Question bank, bilingual admin entry |
| Daily quiz generation with pool-depletion alerting |
| Quiz UI with per-question language toggle |
| `StreakService` including freeze logic |
| Redis leaderboards — state and district |
| Shareable result image (1080×1920, WhatsApp status) |
| FCM web push |
| WhatsApp Cloud API + template approval |
| **AI: current affairs pipeline** — moved earlier; it is the binding constraint on the daily quiz |
| **AI: question generator** — 500 questions by hand is weeks; generated and verified it is days |
| **Content sprint: 500 questions, 60 days scheduled** |

Publish at 06:45, push at 07:00. **Never send a notification for content that has not
finished writing.**

**Exit gate:** the 7 AM habit loop works end to end.

---

## Sprint 4 — Polish, compliance, launch (weeks 11–14)

| Task |
|---|
| PWA: manifest, service worker, install prompt, offline fallback |
| Performance pass against the §5.4 budgets |
| Translation review panel in Filament |
| Privacy policy, terms, consent flow, data export, account deletion |
| Analytics and event tracking |
| **Load test at 50x** |
| Sentry, Pulse, uptime alerts **routed to a phone** |
| Beta with 20 real users, in person |
| Fix everything they trip over |

**Launch on a day a major notification is expected** — ride the traffic spike.

---

## ⛔ Week 14 gate — the one that matters

**If D7 retention is below 20%: stop all feature work and fix the loop.** Do not start
Phase 2. No feature built on a broken habit loop will save it (D11).

---

## Month 4+ — the corpus, then AI

**Do not build AI before the corpus exists.** An assistant with nothing to retrieve answers
anyway — fluently and emptily — and the people who try it in that state do not come back.

| When | Ship |
|---|---|
| Month 4 | `ai_chunks` + observers + Qdrant + backfill |
| Month 4 | Ask Sadhana · Explain simpler (92% cacheable, ships alongside for free) |
| Month 5 | Material generator · first ops agents (`scraper_medic`, `news_curator`) |

---

## Phase 2 — community and content (months 4–8)

Gated on the Week 14 retention gate passing.

Material library with contributor uploads and moderation · doubt community with reputation ·
study circles · offline mode · AdSense live · AI doubt solver · flashcards · tutor agent ·
study plan · Hindi and Tamil groundwork.

**Do not open the community until 5,000+ DAU** (D8). Seed it with 50 real questions answered
by real people first.

**Exit gate:** 100,000 MAU, 500+ community posts/week, first ₹1 lakh in ad revenue.

---

## Phase 3 — monetisation and scale (months 9–15)

Full test series with analytics · premium subscription · direct ad sales platform with
district targeting · native Flutter app · Hindi and Tamil live · job posting marketplace ·
answer evaluation and mock interview behind premium · business agents.

**Exit gate:** 500,000 MAU, ₹5 lakh monthly revenue, positive contribution margin.

---

## The weekly ritual

Every Friday, 30 minutes:

1. What shipped this week?
2. What slipped, and why **specifically**?
3. What are the numbers — DAU, D7, notification latency, error rate, AI cost per user?
4. Am I on track for the sprint exit gate?
5. If not — **what gets cut**, not what gets crunched.
6. What is the single most important thing next week?

**The rule that saves the project:** when a sprint is at risk, cut scope. Never extend the
sprint, never work the weekend. A sprint that slips two weeks becomes a project that slips
two months.

---

## Definition of done

- [ ] Merged to `main`, deployed to staging
- [ ] Tests passing at the coverage target for that area
- [ ] Works correctly in **Telugu and English** — verified, not assumed
- [ ] Tested on a real budget Android on a throttled connection
- [ ] Meets the performance budgets
- [ ] Cache keys include locale
- [ ] SEO tags present where the page is public
- [ ] Accessible: keyboard navigable, adequate contrast, labelled controls
- [ ] Manageable through Filament where relevant
- [ ] Errors reported to Sentry, not swallowed
