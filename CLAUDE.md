# SADHANA — project context for AI-assisted development

> Paste-free version of Vol 3 §A.1. This file is read automatically by Claude Code in this
> repository. Keep it accurate — it is the single highest-leverage file in the repo.

## What this is

A Telugu-first daily-use portal for Indian government and private job aspirants.
Free at the core, ad-funded, with a commercial layer (subscriptions, test series,
AI credits, ad sales) built in but **gated open by default**.

The product is: *find every notification → know if you personally qualify → study for it →
practise daily → ask doubts → take mocks*, all in Telugu, on a ₹8,000 Android, on 3G.

**The core loop is the product.** If a feature does not feed this loop, it does not ship:

```
push 07:00 → daily quiz (10 Q) → streak +1 → personalised feed
   → read exam page / download material / ask doubt / take mock → tomorrow 07:00
```

## Repository layout

```
apps/web/     Laravel 12 monolith — the entire student + admin product
apps/ai/      Python FastAPI service — agent runtime, embeddings, retrieval
docs/         Consolidated specification (read docs/00-MASTER-SPEC.md first)
docs/source/  The 10 original design documents. Historical record — do not edit.
infra/        nginx, supervisor, docker, qdrant configuration
```

Vol 3 and Vol 4 give file paths as `app/...`. In this repo they are `apps/web/app/...`.
Everything else in those documents maps unchanged.

## Stack

| Layer | Choice |
|---|---|
| Backend | Laravel 12, PHP 8.3, strict types |
| Frontend | Livewire 3 + Alpine.js + Tailwind. **Server-rendered HTML — no SPA, no client-side routing.** SEO *is* the business model. |
| Admin | Filament 3 |
| Database | MySQL 8 — owner of record for every fact |
| Cache / queue / streaks | Redis (sorted sets for leaderboards) |
| Keyword search | Meilisearch, one index **per locale** |
| Vector search | Qdrant. `ai_chunks` rows live in MySQL; vectors live in Qdrant keyed by chunk id |
| Files | Cloudflare R2 (zero egress — PDF downloads would bankrupt us on S3) |
| CDN / WAF | Cloudflare |
| Client | PWA first. Flutter app only in Phase 3 |
| Push | FCM (web push) + WhatsApp Cloud API |
| Payments | Razorpay (UPI-first), with an `PaymentGateway` interface so it is swappable |
| AI | Claude via `AiGateway` only. Agents run in `apps/ai` |
| Tests | Pest (PHP), pytest (Python) |

## Hard rules — these are not preferences

1. **Multi-language (te/en) is core, not an afterthought.** Translatable content uses
   `spatie/laravel-translatable` JSON columns. **Slugs are NEVER translated** — one slug
   per entity across all locales, or backlinks fragment and the SEO engine dies.
2. **Every cache key must include `app()->getLocale()`.** Use `Locale::cacheKey()`. Never
   call `Cache::remember()` with a raw string key. This is linted in CI.
3. **Target device is a ₹8,000 Android on 3G.** First-load JS under 100 KB gzipped, LCP
   under 2.5s. Enforced in CI — the build fails over budget.
4. **Telugu text runs 15–30% longer than English and its glyphs are taller.** Test every
   component with Telugu, not English. Line-height 1.85 for Telugu, 1.6 for Latin.
   Numerals always stay in Latin script — `2026`, `₹28,940`, `16,614`.
5. **Modular monolith.** Modules talk through service classes and events. If `Community`
   needs exam data it calls `ExamService`, never `Exam::find()`.
6. **No pirated content anywhere in the system.** Ever. See rule 9.
7. **AI drafts, a human decides.** No AI output about an eligibility criterion, a date, a
   fee or an answer key reaches a student without a human approving it. Enforced in
   application code, not in the prompt.
8. **Eligibility outcomes are computed by `EligibilityService`, never by a model.** The
   intent router routes eligibility and date questions away from the LLM entirely.
9. **The corpus is derived, never authored.** `ai_chunks` is rebuilt from owner tables by
   observers. Nobody edits a chunk. Deleting an owner row deletes its chunks — otherwise a
   copyright takedown is only half done and the assistant keeps quoting removed material.
10. **Money gates default to OPEN.** Every paid feature is a config flag. Shipping a gate
    closed is a product decision made deliberately, never a side effect of writing code.

## Code style

- `declare(strict_types=1);` at the top of every PHP file.
- Business logic in service classes under `app/Services`, never in controllers or Livewire
  components. Controllers orchestrate; services decide.
- Form Requests for validation. Policies for authorisation. Observers for cache/corpus
  invalidation.
- Pest for tests. Feature tests hit routes; unit tests cover services.
- Laravel Pint for formatting, PHPStan level 6 minimum. Both run in CI.
- Ruff + mypy for Python.

## Test coverage targets — non-negotiable for the first three

| Area | Target | Why |
|---|---|---|
| `EligibilityService` | 95% | A wrong answer misleads someone about their career |
| Billing / entitlements | 95% | Money |
| `StreakService` | 90% | Midnight, timezone and freeze edge cases |
| AI output guards | 90% | Silent failure means a hallucinated date reaches a student |
| Translation fallback | 90% | Silent failure shows English to Telugu users |
| Scraper parsers | 80% | Government sites change layout without warning |
| UI components | Smoke | Diminishing returns |

## Definition of done

- [ ] Merged to `main`, deployed to staging
- [ ] Tests written and passing at the target above
- [ ] Verified working in **Telugu and English** — verified, not assumed
- [ ] Tested on a real budget Android on a throttled connection
- [ ] Cache keys include locale
- [ ] SEO tags present if the page is public
- [ ] Manageable through Filament where relevant
- [ ] Errors reported to Sentry, not swallowed

## Before you write code

Read `docs/00-MASTER-SPEC.md`. If you are touching AI or agents, also read
`docs/03-AI-AND-AGENTS.md`. If you are touching money, read `docs/04-COMMERCE.md`.
Before writing any migration, check `docs/02-DATA-OWNERSHIP.md` — it says which table owns
each fact and what a schema change would break.
