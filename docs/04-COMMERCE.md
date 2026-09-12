# Commerce

**Read this before touching money.**

This layer appears in none of the eleven source documents. It was added deliberately, and
the tension it creates with Decision D2 is resolved here.

---

## 1. The tension, and the resolution

Vol 1 Decision D2:

> The core stays free forever. Notifications, syllabus, daily quiz, community and basic
> material are never paywalled. This is a strategic decision, not charity: our moat is
> traffic and habit, and a paywall kills both.

The brief for this repository asks for real revenue from students.

**Both hold, by separating machinery from posture.**

| | |
|---|---|
| **The machinery** | Complete. Plans, subscriptions, orders, payments, entitlements, credits, coupons, invoices, refunds, referrals. Money can be taken today. |
| **The posture** | `commerce.gates_open = true`. Nothing is actually withheld. |

While gates are open, a purchase still creates a subscription and still grants entitlements —
**every row is written**. So on the day a gate closes, paying users already hold what they
bought, and nothing needs backfilling.

Closing a gate then becomes a product decision someone makes on a date, for a reason, with a
metric in mind. Never an accident of how a feature was written.

### Why not simply paywall from day one

The business case rests on a specific causal chain:

```
best Telugu page on a query  →  Google ranking  →  free traffic  →  habit
   →  daily active users  →  advertiser value  →  revenue
```

A paywall breaks it at the second step. Gated pages rank worse, gated pages get fewer
backlinks, and a user who hits a wall on their first visit does not come back tomorrow at
7 AM. Vol 1's own analysis: Adda247 converts about **5%** of 40 million users. The other 95%
monetise through advertising or not at all — and the whole thesis of this product is that
**we monetise that 95%.**

Student subscriptions are a real and worthwhile stream. They are not the first one.

---

## 2. Never gated — at any revenue target

| Capability | Why |
|---|---|
| Notification feed | The reason people find us |
| Eligibility check | The core differentiator. Nobody else answers "should *I* apply?" |
| Exam hub pages | 60% of acquisition. The SEO engine. |
| Daily quiz | The habit loop. The retention engine. |
| Official material | Government PDFs. Gating public documents would be indefensible. |

`FeatureGate::assertGatable()` **throws** if code asks it to gate one of these. It is a
programming error caught in CI, not a runtime condition to handle (D13).

---

## 3. Entitlements, not plans

**Feature code asks for a key. It never asks for a plan** (D14).

```php
// Right
if ($gate->allows($user, 'test_series_full')) { ... }

// Wrong — five different things can grant this capability
if ($user->subscription?->plan->slug === 'premium') { ... }
```

Five sources can grant an entitlement: a subscription, a one-off purchase, a coupon, a
referral reward, a manual staff grant. Feature code should know about none of them.

Entitlements are **one row per grant**, never upserted. A user can hold the same key from
two sources at once — an annual subscription and a campus coupon — and when the coupon lapses
the subscription must still cover them. Collapsing them loses that, and the user loses access
to something they paid for.

### The catalogue

| Key | Grants |
|---|---|
| `ads_free` | No display advertising |
| `test_series_full` | Every mock in every series, not just the free sample |
| `test_analytics_deep` | Percentile, rank, topic strength, comparison to top 10% |
| `ai_answer_eval` | Descriptive answer evaluation for mains |
| `ai_mock_interview` | Mock interview practice |
| `ai_higher_caps` | Raised daily and monthly AI limits |
| `material_bulk_download` | Bulk PDF download |
| `doubt_priority` | Doubts surfaced to verified answerers first |
| `offline_full` | Full offline library sync |

---

## 4. Plans

Prices are **integer paise** (D15). ₹199.00 is `19900`.

| Plan | Price | Period | AI credits |
|---|---|---|---|
| Free | ₹0 | — | 0 |
| **Premium** | **₹199** | yearly | 500 |
| Group 1 Mains | ₹999 | yearly | 3,000 |

₹199 is deliberately cheap. This audience is price-sensitive and the model is **volume, not
margin** — Vol 1 ch.8.3 puts contribution at ~₹4.50–5.30 per MAU per year, and the economics
only work at scale.

The Group 1 tier exists because answer evaluation costs ~₹0.93 per use and mock interview
more. Those features cannot be free at any volume, and the aspirants who need them are the
cohort most able to pay.

---

## 5. Payments

**Razorpay, behind a `PaymentGateway` interface.** UPI-first, because card penetration in
this segment is low and UPI has no per-transaction floor that would make a ₹199 product
uneconomic.

### Rules

- **We never see a card number.** Checkout is on the provider's surface; the callback
  returns identifiers and a signature. This keeps the application outside PCI scope entirely.
- **Verify before any state change.** An unverified callback is an attacker claiming to have
  paid, and a subscription granted on one is a free product with extra steps.
- **Verify webhooks against the raw body**, not a re-encoded array — re-serialising JSON
  changes key order and the signature will not match.
- **Webhooks are stored before they are acted on**, deduplicated by the gateway's event id.
  Gateways retry, duplicate and arrive out of order. Processing a payment webhook twice grants
  two subscriptions for one payment.
- **Ask the provider what happened.** Never trust a client-reported status.
- **Auto-renew is opt-in, never pre-ticked.** A surprise renewal on a ₹199 product buys one
  refund request and loses one user permanently.

### Order vs payment

An **order** is intent to buy. A **payment** is money moving. They are separate rows because
one order can have a failed attempt then a successful one, and reconciliation needs both
histories.

---

## 6. Referrals

Vol 1 ch.9.1, channel 4: one student per degree college, given free premium and a code.

- **Reward is entitlement or credits, never cash.** Cash invites fraud, and this market has
  an active cottage industry in exactly that.
- **"Qualified" means the referred person actually used the product** — 7 days and 3 quiz
  attempts — not merely that they signed up. Rewarding a signup rewards fake accounts.

---

## 7. GST

Education services attract varying treatment and the correct rate is a question for an
accountant, not for a config file. So:

- the rate is stored **per invoice**, not assumed in code
- `place_of_supply` drives CGST/SGST versus IGST
- invoice numbers are sequential per financial year and **gapless**
- `gstin` is captured when a coaching institute buys advertising

`GST_ENABLED` defaults to `false`. Turn it on with an accountant, not with a deploy.

---

## 8. Advertising — the first and larger revenue line

Worth restating here, because it is easy to over-focus on subscriptions: **direct advertising
is the real business** (Vol 1 ch.8.2).

Direct beats programmatic 5–10x for one structural reason: we can offer **district-level and
exam-level targeting**. A Karimnagar coaching centre wants Karimnagar Group-2 aspirants, and
nobody else can sell them exactly that.

| Product | Price |
|---|---|
| Feed banner, district-targeted, 30 days | ₹5,000–15,000 |
| Exam-page sponsorship, 30 days | ₹10,000–25,000 |
| Sponsored daily quiz | ₹15,000/week |
| WhatsApp broadcast mention | ₹8,000 |

**Placement discipline, which is not negotiable for revenue:** never a full-screen
interstitial on first visit; never an ad mid-content on a syllabus page. Ads live in the feed
(every 5th card), between exam-page sections, and before a download. Breaking this trades the
habit loop for a small CPM gain, and the habit loop is the moat.

---

## 9. Before you close a gate

1. Which metric is this meant to move, and what is its current value?
2. Is the capability in `never_gated`? Then stop.
3. Have the entitlement rows been written correctly while gates were open? Check a real user.
4. What happens to someone who used it yesterday for free? Grandfather them, or tell them
   plainly in advance. People forgive a price; they do not forgive a bait.
5. Record it in `docs/01-DECISION-LOG.md` with the date and the reason.
