# Sadhana — Master Specification

**One document, consolidating eleven.** Read this before writing code.

| Field | Detail |
|---|---|
| Version | 2.0 — consolidated |
| Supersedes | Vol 1, Vol 2, Vol 3, Vol 4, and the five HTML prototypes |
| Source documents | `docs/source/` — historical record, do not edit |
| Status | Pre-build. Sprint 0 not started. |

---

## 0. What changed in consolidation, and why

The eleven source documents are unusually complete and internally consistent. This
specification does not rewrite them — it resolves the four places where they conflict with
each other or with the current brief, and adds the two layers none of them covered.

| # | Change | Reason |
|---|---|---|
| C1 | **Commerce layer added** (plans, subscriptions, entitlements, credits, coupons, invoices, refunds, referrals) | No source document specified payments at all. Premium was named as a Phase 3 revenue line with no mechanism behind it. |
| C2 | **Gates default OPEN** | Decision D2 says the core is free forever and ad-funded; the brief asks for student revenue. Resolved by separating machinery from posture — see §6. |
| C3 | **Agent layer added** (four families, tool registry, runtime, memory, evals) | The AI Layer document specified eleven *features* behind one gateway. Agents are a different thing and needed their own model. |
| C4 | **Vectors moved to Qdrant** | The Integration Map specifies `embedding VECTOR(1536)` with a vector index on `ai_chunks`. **MySQL 8 has neither** — they arrived in MySQL 9. See §5.3. |
| C5 | **Monorepo layout** | A Python service was needed for embeddings and the agent runtime. Vol 3/4 file paths `app/…` are now `apps/web/app/…`; nothing else moves. |
| C6 | **Deferred modules included in schema** | Material library, community, circles, test series and mains were Phase 2/3 in Vol 3. Their tables ship now so migrations never have to be reordered later. Building them is still gated on retention. |

Everything else in the source documents stands as written.

---

## 1. The product in one paragraph

A Telugu-first web portal where an Indian government job aspirant finds every exam
notification, learns whether **they personally** qualify, studies for it, asks doubts, and
takes practice tests — in their own language, in one place, every day. Free at the core,
funded by advertising, with a commercial layer for the minority who want more.

### The core loop *is* the product

```
push 07:00 → daily quiz (10 questions) → streak +1 → personalised feed
   → read exam page / download material / ask doubt / take mock → tomorrow 07:00
```

**If a feature does not feed this loop, it does not ship.** This rule has survived four
documents and it survives consolidation.

### The wedge

Do not launch as a coaching platform — that is crowded, capital-heavy and faculty-dependent.
Launch as a **notification and information utility** people open every day, then layer
learning on top of the traffic.

```
Notifications  (why they find us)
      ↓
Daily quiz + streak  (why they come back tomorrow)
      ↓
Study material + doubts  (why they stay an hour)
      ↓
Test series + premium  (why some of them pay)
```

---

## 2. Why this exists

Four facts, from Vol 1 ch.2, which remain the basis of the whole plan:

1. India's test-prep market is ~USD 11.6 bn (2025), growing ~8.7% CAGR.
2. ~98% of Indian internet users consume Indic-language content; Telugu is among the most
   engaged cohorts. **88% of Indian-language users respond better to advertising in their
   own language** — that is a revenue argument, not just a content one.
3. The model is proven: Adda247 reached ~40M monthly users on vernacular-first content.
4. **Nobody owns Telugu.** Adda247's centre of gravity is Hindi. Testbook is English-first.
   The Telugu aspirant is served by Telegram forwards and ad-choked blogs.

**The fragmentation is the opportunity.** Not "a better Adda247" — the default daily
destination for the Telugu aspirant.

### What we are not solving

No live paid classes. No pirated material. No K-12. No offline centres. No selection
guarantees. This discipline matters more than ambition.

---

## 3. Users

| Persona | Share | Needs from us |
|---|---|---|
| **Ravi** — full-time aspirant, 21–27, Telugu-medium, phone-only | 60% | Never miss anything; understand it in Telugu; practise daily; feel progress |
| **Sailaja** — working preparer, 24–32, time-poor | 25% | Only notifications she is eligible for; a 10-minute quiz; offline PDFs |
| **Kiran** — final-year student, 20–22, exploring | 15% | Discovery: "what can I write with a B.Com?" |

Secondary users who matter for revenue: **coaching institutes and hostels** (our direct
advertisers), **contributors** (toppers and teachers who answer doubts), **private employers**.

---

## 4. Modules

| # | Module | Phase | Why it exists |
|---|---|---|---|
| M0 | Foundation — infra, i18n, auth, design system | Sprint 0 | Everything sits on it |
| M1 | Exam Hub | Sprint 1 | SEO engine — 60% of acquisition |
| M2 | Notification Feed + Eligibility | Sprint 2 | The core value proposition |
| M3 | Ingestion / scrapers | Sprint 2 | Content velocity |
| M4 | Daily Quiz + Streaks | Sprint 3 | Retention engine |
| M5 | Push + WhatsApp | Sprint 3 | Distribution |
| M6 | PWA + Performance | Sprint 4 | Reach on cheap phones |
| M7 | Compliance + Launch | Sprint 4 | Legal survival |
| M8 | Material library | Phase 2 | Depth, and corpus for AI |
| M9 | Doubt community | Phase 2 | Compounding archive, SEO |
| M10 | Study circles | Phase 2 | Peer accountability |
| M11 | Test series | Phase 3 | First real student revenue |
| M12 | Commerce | Built now, gated open | See §6 |
| M13 | AI layer | Staged, see §5 | Differentiation |
| M14 | Agents | Staged, see §5 | Content velocity and scale |

**M8–M11 do not start until D7 retention exceeds 20%.** That gate is Decision D11 and it is
the single most important scheduling rule in the project.

### The four differentiators

1. **Telugu depth, not Telugu translation.** Competitors translate *into* Telugu; we
   originate *in* it.
2. **Personalised eligibility.** No competitor answers "should *I* apply to this?" This is
   technically simple and strategically enormous: it turns a notice board into an advisor.
3. **Daily habit.** Competitors are destinations you visit when you need something. We are
   a destination you visit because it is 7 AM.
4. **Free-first, ad-funded.** Competitors gate their good content. Ours is free and
   advertisers pay.

### The moat — what actually compounds

A feature is copied in a sprint. These are not:

| Asset | Time to replicate |
|---|---|
| Structured Telugu exam database — every notification, syllabus, cutoff since launch | 2–3 years |
| SEO position on Telugu exam queries | 2–4 years |
| Community archive of answered doubts | 2–3 years, and only with our traffic |
| Habit — being the 7 AM app | Indefinite, if we do not break it |

**Not a moat:** the stack, the UI, the quiz format, the feature list. Assume all of it is
copied within six months of us becoming visible.

---

## 5. Architecture

### 5.1 Stack

| Layer | Choice | Why |
|---|---|---|
| Backend | Laravel 12, PHP 8.3 | Founder's deepest expertise. Shipping speed beats theoretical fit. |
| Frontend | Livewire 3 + Alpine + Tailwind | **Server-rendered HTML. SEO is the business model** — an SPA would add SSR complexity for no gain. |
| Admin | Filament 3 | The content team needs CRUD on day one |
| Database | MySQL 8 | Owner of record for every fact |
| Cache / queue / streaks | Redis | Sorted sets are exactly right for leaderboards |
| Keyword search | Meilisearch | One index **per locale** |
| Vector search | **Qdrant** | See §5.3 |
| Files | Cloudflare R2 | Zero egress. PDFs at our volume would bankrupt us on S3. |
| CDN / WAF | Cloudflare | Free tier handles our spike profile |
| Client | PWA first | No install friction on limited-storage phones |
| Push | FCM + WhatsApp Cloud API | WhatsApp has the highest open rate in this market |
| Payments | Razorpay, behind an interface | UPI-first; card penetration here is low |
| AI | Claude, via `AiGateway` only | |

**Explicitly rejected:** Next.js/React SPA (SEO), a separate translations table (joins on a
95%-read workload), Elasticsearch (operational weight), native-app-first (install friction),
microservices and Kubernetes (a team of two does not need distributed systems).

### 5.2 Repository layout

```
apps/web/     Laravel 12 monolith — the entire student and admin product
apps/ai/      Python FastAPI — embeddings, retrieval, agent runtime
docs/         This specification
docs/source/  The ten original design documents (historical)
infra/        nginx, supervisor, docker, qdrant
```

Modular monolith. **Modules talk through service classes and events, never by reaching into
each other's models.** If `Community` needs exam data it calls `ExamService`, never
`Exam::find()`.

### 5.3 The vector-store correction

The Integration Map specifies:

```sql
ai_chunks  ... embedding VECTOR(1536), VECTOR INDEX (embedding)
```

**MySQL 8 has no `VECTOR` type and no vector index.** Those arrived in MySQL 9. Rather than
force a database upgrade the rest of the stack does not need:

- `ai_chunks` **stays in MySQL** as the owner of record for chunk text and provenance
- **vectors live in Qdrant**, keyed by `ai_chunks.id` as the point id
- `embedding_model` and `embedded_at` on the chunk row make drift detectable, so changing
  the embedding model triggers a findable re-embed rather than silently degrading retrieval

This preserves the rule that actually matters: **the corpus is derived, never authored.**

### 5.4 Non-functional requirements — hard constraints

| Requirement | Target |
|---|---|
| LCP on 3G, ₹8,000 Android | under 2.5s |
| First-load JS | under 100 KB gzipped — **enforced in CI** |
| Cached page response | under 200ms p95 |
| Dynamic page response | under 500ms p95 |
| Uptime | 99.5% normal, 99.9% on notification days |
| Spike capacity | **50x baseline** without degradation |

That last one is not theoretical. A single major notification produces **20–50x normal
traffic for 72 hours**, and result day is worse. See `docs/06-RUNBOOK.md`.

### 5.5 Multi-language — three layers, three mechanisms

| Layer | Content | Mechanism | Human review |
|---|---|---|---|
| L1 | UI strings | JSON language files | Once, at translation time |
| L2 | Editorial (notifications, syllabus, questions) | Translatable JSON columns | **Mandatory for eligibility, dates, fees** |
| L3 | User-generated (doubts, answers) | Original stored + lazy AI translation, cached | None — labelled as machine translated |

**The rule that protects us:** AI may draft translations of titles and descriptions. AI may
**never** auto-publish eligibility criteria, dates or fees.

Non-negotiables: slugs are never translated; `/te/…` and `/en/…` as subdirectories with
hreflang; **every cache key includes the locale**; the glossary is injected into every AI
prompt.

---

## 6. Money

Full detail in `docs/04-COMMERCE.md`. The essentials:

### The tension, and how it is resolved

Decision D2 says the core stays free forever because traffic and habit are the moat.
The brief asks for revenue from students. Both hold, by separating two things:

- **The machinery is complete.** Payments, subscriptions, entitlements, credits, coupons,
  invoices, refunds, referrals. Money can be taken today.
- **The posture is open.** `commerce.gates_open = true`. Nothing is actually withheld.

While gates are open, a purchase still creates a subscription and still grants entitlements —
every row is written. So on the day a gate closes, paying users already hold what they bought
and nothing needs backfilling.

Closing a gate becomes a product decision made on a date, for a reason, with a metric in
mind. Never an accident of how a feature was written.

### Never gated, at any revenue target

**Notification feed · eligibility check · exam hub pages · daily quiz · official material.**

These five are the SEO engine and the habit loop. `FeatureGate` **throws** if code asks it to
gate one — a programming error caught in CI, not a runtime condition.

### Revenue streams, in the order they switch on

| Stream | From | Note |
|---|---|---|
| Programmatic ads | ~5,000 DAU | ₹25–70 RPM in this vertical |
| **Direct ads** | ~20,000 DAU | **The real business.** District + exam targeting is the thing nobody else can sell. 5–10x programmatic. |
| Premium subscription | ~100,000 MAU | ₹199/yr, deliberately cheap |
| Test series / Group 1 tier | Phase 3 | Where AI evaluation and mock interview belong |
| Job postings | Year 2 | ₹500–2,000 per private posting |

### Unit economics

Revenue per MAU/year ≈ ₹6.48; cost to serve ≈ ₹1.20–2.00; contribution ≈ ₹4.50–5.30.
**This is a volume business, not a margin business.** Obsess over traffic and retention, not
ARPU, for the first two years.

---

## 7. Metrics

**North Star: weekly active aspirants who completed at least one meaningful action** — a
quiz attempt, a material download, a doubt post, or a saved notification.

Not signups. Not pageviews. Not installs. Those can be bought and they lie.

| Level | Metric | Year 1 target |
|---|---|---|
| Acquisition | Organic sessions/month | 400,000 |
| Activation | Set exam preferences in first session | 50% |
| **Retention** | **D1 / D7 / D30** | **40% / 25% / 15%** |
| Engagement | Quiz completion rate | 70% of starts |
| Quality | Notification publish latency | under 60 min, p90 |
| Quality | Doubt first-answer time | under 4 hours, median |
| AI | Cost per active user per month | under ₹0.40 |
| AI | Cost as % of revenue | under 30% |

**The Week 14 gate: if D7 retention is below 20%, stop all feature work and fix the loop.**
No feature built on a broken habit loop will save it.

---

## 8. Risk

The three that end the business, from Vol 1 ch.11:

### R1 — Copyright (likelihood medium, impact **fatal**)

Every Telugu exam-prep Telegram channel distributes scanned coaching books. It is normal,
widespread, and **illegal**. One publisher notice ends this company.

**Policy, absolute.** We host only: government-published PDFs (with attribution and source
link), content we wrote, and user notes the uploader personally authored and warrants.
Never a scanned coaching book, never a PDF with another brand's watermark.

Enforcement: mandatory warranty checkbox logged with timestamp and IP; **every upload
human-reviewed before publication, with no exception at any volume**; published takedown
process with a real address and 48-hour SLA; permanent ban on a second offence.

**Say no to this even when it costs users.** Piracy buys a traffic spike and a permanent
existential liability.

### R2 — Accuracy (likelihood high, impact high)

Publishing a wrong last date destroys a year of someone's life and our credibility with it.

Controls: two-source verification on every notification; dates, fees and eligibility
**never** AI-auto-published; "last verified" shown on every page with a link to the official
source; one-tap "report an error" with a 2-hour SLA.

### R3 — Traffic spike (likelihood high, impact high)

Autoscaling, CDN caching, static fallback mode, load-tested to 50x. **Degraded is always
better than absent.**

### DPDP Act 2023 — built in v1, never retrofitted

Itemised consent in the user's language with the version recorded · purpose limitation ·
working data export **including AI conversations, study plans and flashcards** · working
account deletion · data minimisation (**no Aadhaar, no caste certificates, no address
beyond district**) · minimum age 18 · documented breach process · audit log on every admin
read of user data.

---

## 9. Go to market

| Channel | Share | Mechanic |
|---|---|---|
| **SEO** | 60% | Exam-hub pages and the community archive own Telugu exam queries. One page per exam per language, permanently updated, **never a new page each year.** Publish within 60 minutes of an official release. |
| **WhatsApp** | 25% | Highest open rate in AP/TS by a wide margin, and effectively free. One channel per major exam. |
| YouTube / Reels | 10% | 30–60s Telugu explainer for every major notification, within 30 minutes |
| Campus ambassadors | 5% | One student per degree college, free premium and a referral code |

**Not doing in Year 1:** paid ads (except small bursts on notification days), TV, print,
influencer sponsorships. Expensive, and none of them compound.

**The sequencing rule: do not open the community until 5,000+ DAU.** An empty forum is worse
than no forum — it signals a dead product. Seed it with 50 real questions answered by real
people first.

---

## 10. Where to go next

| Document | Read it when |
|---|---|
| `01-DECISION-LOG.md` | Before re-litigating anything at 1 AM |
| `02-DATA-OWNERSHIP.md` | **Before writing any migration** |
| `03-AI-AND-AGENTS.md` | Before touching AI or agents |
| `04-COMMERCE.md` | Before touching money |
| `05-BUILD-ORDER.md` | Every Monday morning |
| `06-RUNBOOK.md` | On notification day, and during an incident |
| `../CLAUDE.md` | Every AI-assisted coding session |

---

*Vol 1 explained why. Vol 2 explained how. Vol 3 said what to do on Monday. Vol 4 said how
each module fits together. This says all four, once, with the contradictions removed.*
