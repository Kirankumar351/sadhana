# Documentation

## Read in this order

| # | Document | Purpose |
|---|---|---|
| 00 | [Master specification](00-MASTER-SPEC.md) | The whole product, once. **Start here.** |
| 01 | [Decision log](01-DECISION-LOG.md) | 22 decisions and why they hold |
| 02 | [Data ownership](02-DATA-OWNERSHIP.md) | Who owns which fact. **Read before any migration.** |
| 03 | [AI and agents](03-AI-AND-AGENTS.md) | 11 features, 4 agent families, the guardrails |
| 04 | [Commerce](04-COMMERCE.md) | Money. Read before touching it. |
| 05 | [Build order](05-BUILD-ORDER.md) | What to do on Monday |
| 06 | [Runbook](06-RUNBOOK.md) | Notification day, incidents, reconciliation |
| 07 | [Build status](07-BUILD-STATUS.md) | What exists, what is verified, what is not |

Plus [`../CLAUDE.md`](../CLAUDE.md) — the context primer, loaded automatically in every
AI-assisted coding session in this repository.

---

## Source documents

`source/` holds the ten original design documents. **They are a historical record. Do not
edit them.** Where they conflict with the consolidated specification, the specification wins
and §0 of the master spec explains why.

| File | What it is |
|---|---|
| `01-SADHANA-RnD-Business-Case.md` | Vol 1 — market research, personas, business model, decisions D1–D10 |
| `02-SADHANA-Implementation-Documentation.md` | Vol 2 — stack, full SQL schema, eligibility engine, 20 chapters |
| `SADHANA-Vol-1-RnD-and-Business-Case.pdf` | Vol 1, rendered |
| `SADHANA-Vol-2-Implementation-Documentation.pdf` | Vol 2, rendered |
| `SADHANA-Vol-3-Build-Playbook.pdf` | 62 tasks across 5 sprints, organised by week |
| `SADHANA-Vol-4-Module-Build-Guide.pdf` | The same 62 tasks, organised by module, with file trees |
| `SADHANA-Web-Portal.html` | 29 student screens, working prototype |
| `SADHANA-Admin-Portal.html` | 30 admin screens and modals |
| `SADHANA-UI-Specification.html` | Design system — colours, type scale, 38 screens, 14 modals |
| `SADHANA-AI-Layer.html` | 11 AI features, guardrails, cost model, gateway architecture |
| `SADHANA-Integration-Map.html` | Data-ownership audit — 12 gaps, 5 new tables, corrected build order |

### What changed on the way in

- A byte-identical duplicate of the UI specification was removed.
- Two PDFs had a `(1)` suffix from a browser download and were renamed.
- Nothing else was altered. The content is exactly as written.

---

## The design system, at a glance

Extracted from the UI specification so it is available without opening the HTML.

**Colour** — nine values, no gradients. Every state maps to exactly one.

| Token | Hex | Use |
|---|---|---|
| Ink | `#12211C` | Body text, headers |
| Ink soft | `#33463E` | Secondary text, labels |
| Muted | `#6B7C74` | Meta, captions, placeholders |
| Green | `#0F6B4F` | Primary action, eligible, active tab |
| Green wash | `#E3F0EA` | Eligible badge, correct answer |
| Marigold | `#E8A33D` | Streaks, partial match, quiz CTA |
| Marigold wash | `#FCF1DE` | Partial badge, your leaderboard row |
| Red | `#C4362B` | **Deadline under 3 days, and errors only** |
| Paper | `#F7F8F5` | App background |

Deep officialdom green carries authority without the coldness of civic blue. Marigold is the
colour of exam-morning temple visits and of a deadline that matters — warm urgency rather
than alarm. **Red is reserved strictly for the last three days before a deadline**, so it
never loses its meaning.

**Type** — Gabarito for headings, Noto Sans for body (it shares metrics with Noto Sans
Telugu, so a bilingual line never jumps in weight or height).

**The rule that governs every layout:** Telugu strings run 15–30% longer than their English
equivalent and the glyphs are taller. Line-height 1.85 on Telugu, 1.6 on Latin. Numerals
stay Latin everywhere.

**Spacing** 4 · 8 · 12 · 16 · 24 · 32 · 48 — **Radius** sheets 20, cards 11, controls 9,
pills 99 — **Minimum tap target 48px**, for one-handed use on a moving bus.
