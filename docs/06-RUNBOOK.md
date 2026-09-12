# Runbook

Operational procedures. Print the notification-day section and pin it up.

---

## 1. Notification day

Government exam traffic is not flat — it is spiky and event-driven. A single major
notification (say TGPSC Group 2) produces **20–50x normal traffic for 72 hours**. Result day
is worse, because everyone refreshes simultaneously.

```
Traffic
  │                    ██
  │                    ██          ██
  │        ██          ██          ██
  │  ▁▁▁▁▁▁██▁▁▁▁▁▁▁▁▁▁██▁▁▁▁▁▁▁▁▁▁██▁▁▁▁▁
     Normal  Notification  Admit card  Result
             released      released    day
```

### T−24 hours

- [ ] Scale app servers to 3x
- [ ] Raise Cloudflare cache TTLs
- [ ] Pre-create the exam hub page and a draft notification record
- [ ] Draft **and approve** the push message in advance
- [ ] Content team on standby
- [ ] Verify the relevant scrapers ran successfully in the last hour

### T−0 — notification released

- [ ] Publish within **15 minutes** (the p90 target is 60; on a known day, beat it)
- [ ] Push in **staggered waves of 50,000**, never all at once
- [ ] Watch: p95 response time, queue depth, error rate, DB connections

### During the spike — degradation ladder

Apply in this order. **Degraded is always better than absent.**

| Trigger | Action |
|---|---|
| p95 response > 2s | Enable static fallback mode — a cached HTML page with the essential facts, no personalisation |
| DB connections saturating | Shed the personalised feed; serve the anonymous cached feed to everyone |
| Queue depth > 10,000 | Pause `low` queue (translation, image generation). Never pause `push`. |
| AI cost spiking | Disable `ask` and `notes`; Explain stays (it is 92% cached and nearly free) |

**Never let the site go fully down.** A student who cannot reach us on notification day goes
to Telegram and may not come back.

### T+48 hours

- [ ] Scale back down
- [ ] Post-mortem: what broke, what was slow, what to fix before next time

---

## 2. Alert thresholds

**To a phone, not an inbox.** We find out before the student does.

| Alert | Threshold |
|---|---|
| Error rate | above 1% for 5 minutes |
| Response p95 | above 2s for 5 minutes |
| Queue depth | above 10,000 |
| Scraper failing | 3 consecutive runs |
| Moderation queue | above 100 items |
| Notification latency | published more than 4 hours after official release |
| AI daily spend | above 1/25th of the monthly ceiling |
| Agent run failures | 3 consecutive for one agent |
| Payment webhook backlog | any unprocessed webhook older than 15 minutes |

---

## 3. A scraper broke

Government sites change layout without warning — this is normal, not an emergency, **unless
it is notification season for that source**.

1. Check `scrape_sources.consecutive_failures` and the run log
2. Is the site up? Is it a layout change, or are we rate-limited?
3. **Publish manually through Filament in the meantime.** Never let the scraper being broken
   delay a notification — being first matters enormously for ranking.
4. Fix the parser, add a test fixture from the new layout
5. If we were rate-limited: slow the frequency, check our user agent identifies us honestly

---

## 4. A wrong date was published

This is the failure mode with the highest human cost. Treat it with the urgency that implies.

1. **Correct the record immediately.** Do not wait for confirmation of the correct value —
   set the field to null rather than leave a wrong value visible.
2. Purge the CDN for that URL, every locale
3. Confirm `ReindexChunks` ran, so the assistant stops quoting it
4. Push a correction to everyone who saved that notification
5. Update "last verified"
6. Post-mortem: which verification step was skipped, and why was it skippable?

---

## 5. A copyright complaint

**The response clock is 48 hours and the policy is to comply first, discuss second.**

1. Set the material to `taken_down` — this fires the observer
2. **Verify the chunks are gone from both `ai_chunks` and Qdrant.** A takedown that leaves
   the corpus intact is incomplete in exactly the way that matters legally.
3. Check whether the same uploader has other material — a second offence is a permanent ban
4. Reply to the complainant confirming removal
5. Record it. Patterns in complaints tell you where moderation is failing.

---

## 6. AI cost anomaly

1. `ai_requests` grouped by feature for the period — which one moved?
2. Check the cache hit rate for that feature. A sudden drop usually means a prompt version
   changed and invalidated the cache.
3. Check for a single user with abnormal volume — that is what the caps exist for; if one got
   through, the cap is misconfigured.
4. If it is genuine growth: the expensive features (answer evaluation, mock interview) move
   behind premium. `Ask Sadhana` stays free — it is the reason people come back.

---

## 7. Payment reconciliation

Daily, and after any gateway incident:

1. Orders with `status = pending` older than 30 minutes → query the gateway for the real status
2. Payments `captured` with no corresponding entitlement grant → the webhook was missed; replay it
3. Entitlements granted with no payment → investigate immediately
4. `payment_webhooks` with `status = failed` → replay, they are idempotent by event id

**Never resolve a discrepancy in the user's disfavour without checking the gateway first.**
A student who paid ₹199 and lost access will not pay again.

---

## 8. Restore drill — quarterly

**An untested backup is not a backup.**

1. Restore the latest MySQL dump to a scratch database
2. Verify row counts on `users`, `notifications`, `questions`, `payments`
3. Verify a point-in-time recovery from binlogs
4. Rebuild the Qdrant collection from `ai_chunks` (it is derived — this must work)
5. Record how long the whole thing took. That number is your real RTO.
