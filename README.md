<div align="center">

# సాధన · Sadhana

**A Telugu-first portal for Indian government and private job aspirants.**

Find every notification · know whether *you* qualify · study · practise · ask

</div>

---

## What this is

A single repository containing the whole platform: the student portal, the admin portal, the
AI layer, the agent runtime, and the commercial layer.

Free at the core, funded by advertising, with a commercial layer for the minority who want
more. Built for a ₹8,000 Android on a patchy 3G connection, held one-handed on a bus, by
someone who reads Telugu faster than English and is making a decision about their career.

## The core loop

```
push 07:00 → daily quiz (10 questions) → streak +1 → personalised feed
   → read exam page / download material / ask doubt / take mock → tomorrow 07:00
```

**If a feature does not feed this loop, it does not ship.**

---

## Layout

```
apps/web/     Laravel 12 monolith — student + admin product
apps/ai/      Python FastAPI — embeddings, retrieval, agent runtime
docs/         The specification. Start with 00-MASTER-SPEC.md
docs/source/  The ten original design documents (historical, do not edit)
infra/        nginx, supervisor, docker, qdrant
```

## Getting started

```bash
make up        # mysql, redis, meilisearch, qdrant, minio, mailpit
make install   # composer + npm + python deps
make fresh     # migrate and seed
make dev       # serve, queue worker, vite
```

Then open <http://localhost:8000/te>.

`make help` lists everything.

---

## Documentation

| Document | Read it when |
|---|---|
| [`docs/00-MASTER-SPEC.md`](docs/00-MASTER-SPEC.md) | **Before writing any code** |
| [`docs/01-DECISION-LOG.md`](docs/01-DECISION-LOG.md) | Before re-litigating anything at 1 AM |
| [`docs/02-DATA-OWNERSHIP.md`](docs/02-DATA-OWNERSHIP.md) | **Before writing any migration** |
| [`docs/03-AI-AND-AGENTS.md`](docs/03-AI-AND-AGENTS.md) | Before touching AI or agents |
| [`docs/04-COMMERCE.md`](docs/04-COMMERCE.md) | Before touching money |
| [`docs/05-BUILD-ORDER.md`](docs/05-BUILD-ORDER.md) | Every Monday morning |
| [`docs/06-RUNBOOK.md`](docs/06-RUNBOOK.md) | On notification day, and during an incident |
| [`CLAUDE.md`](CLAUDE.md) | Every AI-assisted coding session |

---

## The rules that do not bend

1. **Telugu is the product, not a setting.** Slugs are never translated. Every cache key
   includes the locale. Telugu strings run 15–30% longer than English — test layouts against
   Telugu, never against English.
2. **No pirated content, anywhere, ever.** Government PDFs, content we wrote, and user notes
   the uploader personally authored. One publisher notice ends this company.
3. **AI drafts; a person decides.** No AI output about a date, a fee, an eligibility
   criterion or an answer key reaches a student without human approval. Enforced in
   application code, not in prompt text.
4. **Eligibility is deterministic.** `EligibilityService` decides, never a model.
5. **The corpus is derived, never authored.** Delete an owner row and its chunks go with it —
   otherwise a copyright takedown is only half done.
6. **Money is integer paise.** ₹199.00 is `19900`.
7. **Paid gates default to OPEN.** Closing one is a dated, reasoned product decision.
8. **Under 100 KB of JavaScript on first load.** Enforced in CI; the build fails over budget.

---

## Quality gates

```bash
cd apps/web
composer check     # pint + phpstan + pest
npm run budget     # first-load JS budget
```

Coverage floors: `EligibilityService` 95% · billing and entitlements 95% ·
`StreakService` 90% · AI output guards 90% · translation fallback 90%.

CI additionally fails on a raw `Cache::remember()` string key, and on any direct model
provider call outside `App\Services\AI`.

---

## Status

**Pre-build.** Schema, configuration, core services and specification are in place.
Sprint 0 has not started — see [`docs/05-BUILD-ORDER.md`](docs/05-BUILD-ORDER.md).

## Licence

Proprietary. All rights reserved.
