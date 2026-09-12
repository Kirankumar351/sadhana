# Decision log

Decisions recorded so they are not re-litigated every month. Each has a rationale and, where
one exists, the temptation that will eventually argue against it.

**To reverse a decision:** add a new row superseding it, with a date and a reason. Do not
edit or delete an existing row — the history of what was believed and when is the whole
value of this file.

---

## Inherited from Vol 1 — still binding

| # | Decision | Rationale | The temptation to reverse it |
|---|---|---|---|
| **D1** | Telugu-first, not Telugu-also | The differentiation from Adda247 and Testbook. They translate *into* Telugu; we originate *in* it. | "English content is cheaper to produce and reaches more people" |
| **D2** | Free core, ad-funded | Traffic and habit are the moat. A paywall kills both. 95% of Adda247's users monetise through something other than subscription — we monetise that 95%. | "We could charge for this." See **D12** for how this is now resolved. |
| **D3** | PWA before native app | Reach on cheap phones, no install friction, one codebase, instant updates | "Everyone wants an app" |
| **D4** | Laravel stack | Founder's deepest expertise. Shipping speed beats theoretical fit. | "Next.js is more modern" — and would require SSR complexity for a product whose entire acquisition model is server-rendered HTML |
| **D5** | Notifications as the wedge, not coaching | Capital-light, defensible, and where the daily need actually is | "Coaching is where the money is" |
| **D6** | Zero tolerance on pirated material | Existential legal risk. One publisher notice ends the business. | "Everyone does it, it would 10x our traffic." Traffic you cannot keep is not traffic. |
| **D7** | Two languages at launch, not eight | Thin pages destroy the SEO engine the whole model depends on | "More languages, more users" |
| **D8** | Community opens only after 5,000 DAU | An empty forum signals a dead product and permanently damages first impressions | "Let's launch with it, it looks better" |
| **D9** | Human review on every date and eligibility criterion | One wrong date costs a user their year and costs us their trust forever | "The AI extraction is good enough now" |
| **D10** | Bootstrap through Phase 1 | Raising before D7 retention is proven means raising on a story. Raising after means raising on evidence, at a far better valuation. | "We could move faster with funding" |

---

## Added in consolidation — 2026-09-12

| # | Decision | Rationale |
|---|---|---|
| **D11** | **Stop all feature work if D7 retention is under 20% at Week 14** | If the habit loop does not work, no feature built on top of it will. This is the highest-risk assumption in the entire business plan (Vol 1 ch.13.2), and the only honest response to failing it is to stop and fix the loop. The temptation — "just one more feature will fix retention" — is exactly the failure mode this exists to prevent. |
| **D12** | **Commerce machinery ships now; gates default OPEN** | Resolves the conflict between D2 and the brief's requirement for student revenue. The payment layer is complete and live — purchases work, subscriptions activate, entitlements are recorded — but `commerce.gates_open = true` means nothing is actually withheld. Closing a gate becomes a dated, reasoned product decision rather than a side effect of writing a feature. Because entitlement rows are written all along, no backfill is ever needed. |
| **D13** | **Five capabilities are never gated, at any revenue target** | Notification feed, eligibility check, exam hub pages, daily quiz, official material. These are the SEO engine and the habit loop — the compounding assets from Vol 1 ch.7. Gating one to raise ARPU trades a multi-year moat for a quarter of revenue. `FeatureGate` throws rather than returning false, so this is caught in CI as a programming error. |
| **D14** | **Feature code asks for an entitlement key, never for a plan** | `$gate->allows($user, 'test_series_full')`, never `$user->plan === 'premium'`. Five different things can grant a capability — a subscription, a purchase, a coupon, a referral, a manual staff grant — and feature code should know about none of them. |
| **D15** | **Money is integer paise, everywhere** | Never a float, never a decimal rupee column someone will eventually round. ₹199.00 is `19900`. Floating-point money bugs are found by accountants, months later, in aggregate. |
| **D16** | **Vectors live in Qdrant; `ai_chunks` stays in MySQL** | The Integration Map specifies a MySQL `VECTOR(1536)` column with a vector index. MySQL 8 has neither — they arrived in MySQL 9. Rather than force a database upgrade the rest of the stack does not need, MySQL stays the owner of record and Qdrant holds the vectors keyed by `ai_chunks.id`. Preserves the rule that matters: the corpus is derived, never authored. |
| **D17** | **Agents draft; there is no autonomy level that publishes** | The highest autonomy in the system is `act`, covering internal state only — reindex a chunk, retry a scraper, tag a lead. Anything a student will read goes to `ai_drafts` and waits for a person. There is deliberately no `publish_notification`, `approve_question` or `set_eligibility` tool for an agent to reach for. Enforced by `ToolRegistry` at bind time, in CI, not trusted at runtime. |
| **D18** | **Every agent ships disabled** | Turning one on is a deliberate operational decision with an eval run behind it. A catalogue of agents that all start running the moment they are merged is how an AI bill arrives unannounced. |
| **D19** | **Prompt editing is Owner-only; hard blocks are not in prompts** | A prompt is not content — it silently changes the behaviour of every answer the product gives. The eligibility route, the retrieval gate and the output filters live in application code, where no prompt edit and no user instruction can reach them. |
| **D20** | **Deferred modules ship their schema now, their code later** | Material library, community, circles and test series are still gated behind D11. But their tables are in the initial migration set, so the schema never has to be reordered and a foreign key never has to be retrofitted onto a table with millions of rows. |
| **D21** | **Monorepo: `apps/web` + `apps/ai`** | A Python service is genuinely better for embeddings, retrieval and agent loops. Vol 3 and Vol 4 give file paths as `app/…`; they are now `apps/web/app/…` and nothing else about those documents changes. |
| **D22** | **AI cost per active user stays under ₹0.40/month** | Not a target — a constraint. The free product does not work above it. Held by four mechanisms: cache by content not by user, small model for routing and filtering, hard per-user caps, and short retrieval-based prompts. When AI exceeds 30% of revenue, the expensive features move behind premium. |

---

## The five you will most want to reverse

From Vol 3 Part E, written down so future-you does not relitigate them at 1 AM.

| Decision | The temptation | Why you hold |
|---|---|---|
| Community opens only after 5,000 DAU | "Let's launch with it, it looks better" | An empty forum signals a dead product and permanently damages first impressions |
| No pirated PDFs, ever | "Everyone does it, it would 10x our traffic" | One publisher notice ends the business. Traffic you cannot keep is not traffic. |
| Two languages at launch, not eight | "More languages, more users" | Thin pages destroy the SEO engine the entire model depends on |
| Human review on every date and eligibility | "The AI extraction is good enough now" | One wrong date costs a user a year and costs you their trust forever |
| Stop feature work if D7 is under 20% at Week 14 | "Just one more feature will fix retention" | If the habit loop does not work, nothing built on top of it will |

---

## Superseded

*None yet. When a decision is reversed, move it here with the date, the reason, and the
number of the decision that replaced it.*
