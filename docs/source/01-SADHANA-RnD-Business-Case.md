# SADHANA — Product R&D and Business Case

**A Telugu-first daily-use portal for government and private job aspirants**

| Field | Detail |
|---|---|
| Document | Volume 1 of 2 — Research, Strategy, Business Case |
| Companion | Volume 2 — Technical Implementation Documentation |
| Version | 1.0 |
| Date | September 2026 |
| Owner | Lokesh |
| Status | Pre-build. Approved concept, pending Sprint 0. |

---

## How to read this document

This is the **thinking** document. It answers *why this product should exist*, *who it is for*, *what already exists in the market*, *where the gap is*, and *how it makes money*.

Volume 2 is the **building** document. It answers *how to code it*, screen by screen, table by table, sprint by sprint.

Read Volume 1 fully before Volume 2. If you skip to Volume 2 you will build features nobody asked for.

Every chapter starts with a one-line Telugu summary so that a non-technical reader — a co-founder, an investor, a teacher, a family member — can follow the argument without reading the detail.

---

# Chapter 0 — Executive Summary

> **సారాంశం:** తెలుగు విద్యార్థుల కోసం, ప్రతిరోజూ వాడే ఒక ఉద్యోగ + పరీక్ష పోర్టల్. ఉచితంగా మొదలై, ప్రకటనల ద్వారా ఆదాయం.

### The one-sentence pitch

Sadhana is a free, Telugu-first web and mobile portal where a government job aspirant can find every exam notification, check if they are eligible, study for it, ask doubts, and take practice tests — all in their own language, in one place, every day.

### Why this, why now

Four facts, taken together, make this the right product at the right time:

1. **The market is enormous and growing.** India's test preparation market was valued at roughly USD 11.6 billion in 2025 and is projected to grow at about 8.7% annually, reaching around USD 20.8 billion by 2032 (Ken Research). Some analysts put the FY30 figure as high as USD 26 billion.

2. **Indian-language internet is the mainstream, not the niche.** India's active internet base crossed 886 million in 2024 and is heading past 900 million. According to the IAMAI–KANTAR *Internet in India* report, about 98% of users consume content in Indic languages, with Telugu named among the most popular. Even in urban India, 57% prefer regional-language content. A Google–KPMG study found Indian-language internet users overtook English users as far back as 2016, and that 88% of them respond better to advertising in their own language.

3. **The proof of the model already exists.** Adda247 built a business on exactly this insight — vernacular-first content for government job exams — reaching about 40 million monthly users and 2 million paid users, with revenue of roughly ₹243 crore in FY24 and 88% year-on-year growth. It offers content in 12+ Indian languages.

4. **But nobody owns Telugu.** Adda247's strength is Hindi. Testbook's strength is analytics in English. The Telugu-speaking aspirant — roughly 8+ crore people across Andhra Pradesh and Telangana, with lakhs applying for every single TGPSC/APPSC notification — is served today by a fragmented mess of Telegram groups, YouTube channels, ad-choked blogs, and PDF forwards on WhatsApp.

**That fragmentation is the opportunity.** Not "build a better Adda247." Build the *default daily destination* for the Telugu aspirant, then extend the same architecture to Tamil, Kannada, Hindi.

### The wedge

Do not launch as a coaching platform. Coaching is a crowded, capital-heavy, faculty-dependent business.

Launch as a **notification + information utility** that people open every single day, then layer learning on top of the traffic.

```
Notifications (why they find us)
        ↓
Daily quiz + streak (why they come back tomorrow)
        ↓
Study material + doubts (why they stay for an hour)
        ↓
Test series + premium (why they pay)
```

### The three-year target

| Metric | Year 1 | Year 2 | Year 3 |
|---|---|---|---|
| Monthly active users | 150,000 | 900,000 | 3,500,000 |
| Daily active users | 15,000 | 120,000 | 500,000 |
| Languages live | 2 (te, en) | 4 (+hi, ta) | 7 |
| Annual revenue | ₹6–10 lakh | ₹90 lakh–1.2 cr | ₹5–7 cr |
| Team size | 2–3 | 8–10 | 25–30 |

These are planning targets, not promises. Chapter 13 shows the assumptions behind them and what breaks if the assumptions are wrong.

---

# Chapter 1 — Problem Statement

> **సారాంశం:** విద్యార్థికి సమాచారం ఉంది కానీ చెల్లాచెదురుగా ఉంది. అదే అసలు సమస్య.

## 1.1 The user's actual day

Meet Ravi. He is 23, has a B.Sc. from a degree college in Karimnagar, lives in a shared room in Hyderabad near Ashok Nagar, and is preparing for Group 2, SSC CGL, and any police notification that appears. His phone is a ₹12,000 Android. He reads Telugu comfortably and English slowly.

Here is his information-seeking day today:

| Time | What he does | The friction |
|---|---|---|
| 7:00 AM | Opens 4 WhatsApp groups | 200 unread messages, 90% forwards, most already expired |
| 7:30 AM | Checks 3 Telegram channels | Same notification posted 6 times, no way to know if it applies to him |
| 9:00 AM | Googles "group 2 notification" | Lands on an SEO blog with 8 ad popups and last year's information |
| 11:00 AM | Wants the syllabus PDF | Downloads a 40 MB scanned file, unreadable on his phone |
| 4:00 PM | Has a doubt in Polity | Asks in a WhatsApp group, gets 3 wrong answers and one argument |
| 8:00 PM | Wants to practice | Opens an English-only app, half-understands, closes it |
| 10:00 PM | Worries he missed something | Repeats the whole loop |

He is not short of information. He is drowning in **unstructured, unverified, untranslated, undated** information.

## 1.2 The six problems, named

**P1 — Discovery.** There is no single trustworthy place where every relevant notification appears within hours of release, in Telugu.

**P2 — Eligibility.** Notifications are written in bureaucratic language. "Am I eligible?" takes 20 minutes of PDF reading. Most aspirants either apply for things they cannot get, or miss things they could.

**P3 — Language.** The best-quality preparation content in India is in English or Hindi. A Telugu-medium graduate is structurally disadvantaged before the exam even starts.

**P4 — Material quality.** PDFs circulating on WhatsApp are pirated, outdated, watermarked five times over, and often for the wrong exam year.

**P5 — Doubt resolution.** No structured place to ask. Groups are noisy; answers are unverified; nothing is searchable, so the same doubt is asked a thousand times.

**P6 — Isolation.** Preparation is a two-to-five-year solo grind. Dropout is emotional as much as academic. There is no cohort, no streak, no visible progress.

## 1.3 What we are NOT solving

Discipline about scope matters more than ambition. Sadhana will **not**:

- Run live classes with paid faculty (capital heavy, competes with entrenched coaching brands)
- Host pirated books or coaching material (legal suicide, covered in Chapter 11)
- Try to serve school students (K-12) in version 1
- Build offline centres
- Promise selection or job guarantees

---

# Chapter 2 — Market Research

> **సారాంశం:** మార్కెట్ పెద్దది, పెరుగుతోంది, మరియు ప్రాంతీయ భాషలదే భవిష్యత్తు.

## 2.1 Market size

| Source | Finding |
|---|---|
| Ken Research | India test preparation market ≈ USD 11.6 bn in 2025, growing ~8.70% CAGR to ≈ USD 20.8 bn by 2032. Coverage includes classroom coaching, live online, self-paced digital, hybrid, test series, and prep material. Roughly 19,000 players. |
| Technavio | Similar 2025 base (~USD 11.60 bn), 8.7% CAGR 2026–2030, post-secondary segment the largest slice |
| Indian Television (industry report) | Test prep heading toward as much as USD 26 bn by FY30; UG entrance prep alone growing from ~USD 3.8 bn in FY26 to USD 6.5–7 bn by FY30 |
| IMARC | Online test preparation segment specifically growing far faster than the offline blend |

The headline numbers differ because analysts define the boundary differently (some count only paid digital, some count all coaching). The direction is unanimous: **large base, high single-digit to high double-digit growth, digital growing fastest.**

Critically for us: India's advantage in this market is described as its *large recurring domestic exam funnel* spanning engineering, medicine, university admissions **and government recruitment**. Government recruitment is the segment with the least English dependency and the most regional-language demand — exactly our target.

## 2.2 The language opportunity

This is the single most important research finding in this document.

| Finding | Source |
|---|---|
| Active internet users in India reached 886 million in 2024, up 8% YoY, crossing 900 million in 2025 | IAMAI–KANTAR, *Internet in India Report 2024* |
| Rural India accounts for 488 million users — 55% of the total internet population | IAMAI–KANTAR |
| About 98% of internet users access content in Indic languages; Tamil, Telugu and Malayalam among the most popular | IAMAI–KANTAR |
| 57% of *urban* internet users prefer regional-language content | IAMAI–KANTAR |
| Indian-language internet users overtook English users in 2016 (234 mn vs 175 mn), projected to grow at 18% CAGR vs 3% for English | Google–KPMG, *Indian Languages: Defining India's Internet* |
| 88% of Indian-language internet users are more likely to respond to an advertisement in their own language | Google–KPMG |
| Telugu users specifically flagged as among the most *digitally engaged* language cohorts | Google–KPMG |

**Read that 88% figure again.** It is not just a content argument, it is a *revenue* argument. Advertisers pay more for audiences that convert. A Telugu-native ad slot in front of a Telugu-native aspirant converts better than the same slot on an English page — which means our CPM ceiling is higher than a generic English portal's, at the same traffic.

## 2.3 The Telugu aspirant pool — bottom-up sizing

Top-down market reports are useful for context but useless for planning. Here is a bottom-up estimate.

| Segment | Estimated annual active aspirants (AP + TS) |
|---|---|
| TGPSC / APPSC (Group 1, 2, 3, 4) | 12–18 lakh |
| Police (Constable, SI) recruitment cycles | 8–15 lakh (spikes hugely in notification years) |
| DSC / TET (teacher recruitment) | 5–8 lakh |
| SSC (CGL, CHSL, MTS, GD) from Telugu states | 6–10 lakh |
| Railways (RRB NTPC, Group D, ALP) | 8–12 lakh |
| Banking (IBPS, SBI) | 3–5 lakh |
| VRO/VRA, Panchayat Secretary, Gurukul, Singareni, APSPDCL/TSSPDCL and other state PSUs | 5–10 lakh |
| Defence, Post Office, Anganwadi, Court staff, misc. | 4–7 lakh |

Note the overlap — one person appears in five rows. After de-duplication:

- **TAM (Total Addressable Market):** ~2.2–2.8 crore Telugu-reading people aged 18–35 who will engage with a government job at some point
- **SAM (Serviceable Available Market):** ~40–55 lakh actively preparing in any given year with a smartphone and data
- **SOM (Serviceable Obtainable Market, 3 years):** 30–35 lakh MAU is theoretically reachable; we plan for 35 lakh MAU by Year 3 as the stretch case and 20 lakh as the base case

Even the *conservative* case is a large-scale consumer product.

## 2.4 Seasonality — the thing that will surprise you

Government exam traffic is not flat. It is spiky and event-driven:

```
Traffic
  │                    ██
  │                    ██          ██
  │        ██          ██          ██
  │  ▁▁▁▁▁▁██▁▁▁▁▁▁▁▁▁▁██▁▁▁▁▁▁▁▁▁▁██▁▁▁▁▁
     Normal  Notification  Admit card  Result
             released      released    day
```

A single major notification (say TGPSC Group 2) can produce **20–50x normal traffic for 72 hours**. Result day is worse — everyone refreshes simultaneously.

**Consequences that must be designed for from day one:**
- Infrastructure must survive a 50x spike (Volume 2, Chapter 14)
- Content team must be able to publish within 60 minutes of an official release
- Push notification infrastructure is the single highest-leverage feature we own
- Ad revenue is spiky; never build a cost base that assumes a flat month

---

# Chapter 3 — Competitor Analysis

> **సారాంశం:** పెద్ద కంపెనీలు హిందీ, ఇంగ్లీష్‌పై దృష్టి పెట్టాయి. తెలుగు ఖాళీగా ఉంది.

## 3.1 The competitive landscape, mapped

Competitors fall into five layers. Most founders only look at layer 1 and miss where the traffic actually is.

### Layer 1 — National edtech platforms

**Adda247**
- Founded 2012, Gurugram. Series C-II, ~USD 96M raised.
- ~40 million monthly users, ~2 million paid users
- Revenue ≈ ₹243.39 crore FY24, up 88% YoY
- 500+ competitive exams, 12+ vernacular languages, 15 states
- Model: free blog/video content as funnel → paid courses at ₹2,000–5,000, priced deliberately for smaller towns
- Gamification: in-app virtual coins to unlock courses, explicitly for retention
- **Strength:** vernacular-first thesis, brand trust, faculty, capital
- **Weakness for us:** Hindi-belt centre of gravity; Telugu is one of twelve, not the point. Interface is widely described as cluttered with too many exam categories. Analytics are basic compared to Testbook.

**Testbook**
- **Strength:** best-in-class mock tests, performance analytics, rank prediction, clean interface, affordable plans, free tier
- **Weakness for us:** regional-language support consistently described as more limited than Adda247's; English-first UX; content depth varies by exam

**Unacademy / PhysicsWallah / Vedantu**
- Focused on JEE/NEET/UPSC and K-12. State government exams are a side business.
- Notably, distribution-first online platforms accounted for only 0–2% of top-100 JEE Advanced and NEET UG ranks in 2026, while national institutes took 88–93%. **Lesson: online-only platforms have not yet cracked outcomes at the top end.** Our positioning should be access and information, not "we produce toppers."

### Layer 2 — Telugu-specific incumbents

| Player | What they do | Weakness |
|---|---|---|
| Sakshi Education | News-brand education portal, Telugu content, notifications, previous papers | Newspaper-era UX, no personalisation, no accounts, no community, weak mobile |
| Eenadu Pratibha | Same category, strong brand recall | Same weaknesses; content behind a print-first mindset |
| Regional job blogs (jobupdatestelugu-type sites) | Fast notification posting, good Telugu SEO | Ad-choked, no verification, no structure, no retention loop, one-person operations |

**This layer is our real competition and our real opportunity.** They have the audience trust but 2010-era product. We can beat them on product without beating anyone on content budget.

### Layer 3 — Telegram and WhatsApp

Where the actual daily behaviour lives. Free, instant, but unstructured, unsearchable, and heavily pirated.

**Do not fight this layer. Integrate with it.** (See Chapter 9 — WhatsApp channels are our distribution, not our enemy.)

### Layer 4 — YouTube

Telugu current affairs and syllabus channels have huge reach. Again — a distribution partner, not a competitor. Every channel needs a link in the description.

### Layer 5 — Government's own sites

tgpsc.gov.in, psc.ap.gov.in, ssc.gov.in, ncs.gov.in. Authoritative but unusable — no mobile design, no alerts, no Telugu, PDFs only.

**These are our upstream data source, and our credibility anchor.** Always link back to the official PDF. Never claim to replace them.

## 3.2 Feature gap matrix

| Capability | Adda247 | Testbook | Sakshi/Eenadu | Telegram | **Sadhana** |
|---|---|---|---|---|---|
| Telugu-first UI | Partial | No | Yes | Yes | **Yes** |
| Telugu-first study content | Partial | Weak | Partial | Mixed | **Yes** |
| Notification within hours | Yes | Yes | Yes | Yes | **Yes** |
| Personalised eligibility check | No | No | No | No | **Yes — core differentiator** |
| Daily habit loop (streak) | Coins | Partial | No | No | **Yes — core differentiator** |
| Structured, searchable Q&A | No | No | No | No | **Yes — core differentiator** |
| Free full test series | Partial | Partial | No | No | Free daily + paid full |
| Legal, clean material library | Yes | Yes | Partial | No | **Yes** |
| WhatsApp-native delivery | Partial | Partial | No | Yes | **Yes** |
| Works on ₹8,000 phone / 3G | Heavy app | Medium | Yes | Yes | **Yes — PWA, <100 KB first paint** |
| Free at the core | Freemium | Freemium | Free | Free | **Free** |

## 3.3 The four gaps we exploit

**Gap 1 — Telugu depth, not Telugu translation.** The incumbents *translate into* Telugu. We *originate in* Telugu, with Telugu-language current affairs, Telugu explanations, Telugu community. Different product, not a localised one.

**Gap 2 — Personalised eligibility.** No competitor answers "Should *I* apply to this?" We will, using the user's stored qualification, date of birth, category and district. This is technically simple and strategically enormous — it converts a notice board into an advisor.

**Gap 3 — Daily habit.** Every competitor is a destination you visit when you need something. We will be a destination you visit because it is 7 AM. Habit beats features.

**Gap 4 — Free-first, ad-funded.** Every serious competitor gates its good content. Our best content is free forever; advertisers pay. This is the Google/ShareChat model applied to exam prep, and it is defensible against players whose revenue model depends on conversion to paid.

---

# Chapter 4 — Reference Systems: where each idea comes from

> **సారాంశం:** ప్రతి ఫీచర్ ఇప్పటికే ఎక్కడో పని చేస్తోంది. మనం కొత్తగా ఏదీ కనిపెట్టడం లేదు — కలుపుతున్నాం.

You asked where to take reference from. Here is the honest answer: **almost nothing in Sadhana is novel.** Every mechanic below already works at scale somewhere. The innovation is the *combination*, aimed at a *specific underserved language*.

| Feature in Sadhana | Reference product | What exactly to study | What to change for our context |
|---|---|---|---|
| Daily streak + reminder | Duolingo | Streak freeze, loss aversion messaging, 3-day/7-day/30-day milestones, the way the notification is worded | Send at 7 AM IST; Telugu copy; never guilt-trip — our users are already under exam stress |
| Daily 10-question quiz | Adda247 daily quizzes | Question format, difficulty mix, how they tie quiz to the day's current affairs | Telugu + English toggle on *every* question |
| Structured Q&A, upvotes, accepted answer | Stack Overflow | Reputation model, duplicate detection, tag taxonomy, moderator privileges by reputation | Voice notes and photos — typing Telugu on a phone is painful. Far gentler moderation tone. |
| Exam-wise hub pages | Testbook exam pages | Page structure: syllabus → pattern → previous papers → cutoff → dates → material. This structure is why they rank on Google | Write the Telugu version as the primary page, not a translation |
| Eligibility matching | Naukri / LinkedIn job match | Profile fields → filter logic → "you match 4 of 5 criteria" UI | Government criteria are stricter and formulaic (age relaxation by category, qualification codes) — actually easier to automate than private jobs |
| Vernacular-first content strategy | Adda247, ShareChat, Dailyhunt | How ShareChat grew Bharat audiences without English; Dailyhunt's language-first feed | Narrower: one exam vertical, deeper quality |
| Feed + notification model | Inshorts | Short card format, "read in 60 words", swipe UX | Cards are notifications and current affairs, not general news |
| Test analytics and rank prediction | Testbook | Percentile calculation, weak-area breakdown, comparison to toppers | Show in Telugu; simpler visuals for low-literacy-in-charts users |
| Study groups | WhatsApp / Discord | Group size limits, admin roles, pinned messages, media sharing | Auto-created per exam, capped at 200, moderated |
| Ad-funded free content | Google Search, ShareChat | Native ad placement that does not destroy the reading experience | Never interstitial on a syllabus page; ads on download and feed only |
| PWA performance on cheap phones | Flipkart Lite (classic case study), Twitter Lite | Service worker caching strategy, sub-100 KB shell, offline fallback | Aggressive: assume 3G and a 2 GB RAM phone as the *default* |
| Success stories | Any coaching brand | Format: photo, name, rank, "what I did differently" | Verified only. One fake story destroys the brand. |
| Content moderation at scale | Reddit, Quora | Report → queue → action ladder. Reputation-gated privileges | Start fully manual. Automate at 50k+ posts. |

### Technical references

| Concern | Reference to study |
|---|---|
| Laravel multi-language SEO | `spatie/laravel-translatable` docs; Laravel localization docs |
| Search relevance for Indic text | Meilisearch tokenizer documentation, per-locale index patterns |
| Google indexing of multi-language sites | Google Search Central: "Managing multi-regional and multilingual sites" — the hreflang and URL-structure guidance is the authority here |
| Structured data for job postings | schema.org `JobPosting` and `Course` — this is how we get rich results in Google Jobs |
| Web performance budgets | web.dev Core Web Vitals thresholds (LCP, INP, CLS) |
| Data protection | India's DPDP Act 2023 — consent, purpose limitation, minors, breach notification |

### How to actually do the reference study (a practical instruction)

Do not read about these products. **Use them.** Specifically:

1. Install Adda247, Testbook, and Sakshi Education apps on a real budget Android phone.
2. Complete the full signup flow on each. Screenshot every screen.
3. Set the language to Telugu wherever offered. Note every place it silently falls back to English. **Those failures are your feature list.**
4. Follow one notification (say, a police recruitment) across all three plus two Telegram channels. Time how long each takes to publish, and how accurate they are.
5. Write down every moment you felt confused, annoyed, or lost. That list becomes your UX spec.

Budget one full week for this before writing any code. It will save two months.

---

# Chapter 5 — Users

> **సారాంశం:** ముగ్గురు రకాల విద్యార్థులు. ముగ్గురికీ ఒకే యాప్, వేర్వేరు అనుభవం.

## 5.1 Primary personas

**Persona A — "Ravi", the full-time aspirant (60% of users)**
- 21–27, degree completed, preparing full time, lives in a hostel or shared room in Hyderabad/Vijayawada/Warangal
- Telugu medium schooling, English reading is slow
- 4–6 hours daily on preparation, phone is the primary device
- Fears: missing a notification, wasting a year, family pressure
- **Needs from us:** never miss anything, understand it in Telugu, practise daily, feel progress

**Persona B — "Sailaja", the working preparer (25%)**
- 24–32, has a private job or is a teacher, preparing in evenings and weekends
- Time-poor, will not read long content
- **Needs from us:** filtered notifications only for what she is eligible for, 10-minute daily quiz, downloadable PDF for offline

**Persona C — "Kiran", the final-year student (15%)**
- 20–22, still in degree college, exploring
- Does not know what exams exist or which he qualifies for
- **Needs from us:** discovery, career guidance, "what can I write with a B.Com?"

## 5.2 Secondary users (they matter for revenue)

- **Coaching institutes and hostels** — our direct advertisers
- **Content contributors** — toppers, teachers, senior aspirants who answer doubts and upload notes
- **Private employers** — the paid job-posting side

## 5.3 Job stories

Written as jobs-to-be-done, which is more useful than feature lists:

- When a new notification is released, I want to know within an hour and know immediately whether I qualify, so I do not waste time reading a 60-page PDF.
- When I open my phone in the morning, I want one small task that makes me feel I have started studying, so I do not doomscroll.
- When I get stuck on a concept, I want a correct answer in Telugu from someone who has actually cleared the exam, so I stop being stuck.
- When I have 20 minutes on a bus, I want to revise something useful offline, so my commute is not wasted.
- When I finish a mock test, I want to know exactly which topic is killing my score, so I know what to study tomorrow.

---

# Chapter 6 — Product Concept

> **సారాంశం:** ఒక ప్రధాన అలవాటు, ఏడు మాడ్యూల్స్, రెండు భాషలతో మొదలు.

## 6.1 The core loop (this is the product)

```
   Push at 7:00 AM  ──────────────────────────┐
          │                                    │
          ▼                                    │
   ROJU PRASHNA — 10 questions, Telugu/English │
          │                                    │
          ▼                                    │
   Streak +1 · Leaderboard · Weak-area tag     │
          │                                    │
          ▼                                    │
   Personalised feed: "3 new notifications,    │
   you are eligible for 2"                     │
          │                                    │
     ┌────┴────┬─────────────┬──────────────┐  │
     ▼         ▼             ▼              ▼  │
  Read      Download      Ask a          Take a│
  exam      material      doubt          mock  │
  page                                         │
     └─────────────────────────────────────────┘
              Tomorrow, 7:00 AM
```

**If a feature does not feed this loop, it does not go in v1.** Write that on the wall.

## 6.2 The seven modules

### Module 1 — Notification Feed *(MVP, highest priority)*

The reason people find us.

- Every govt + private opportunity relevant to AP/TS aspirants
- Sources: TGPSC, APPSC, SSC, RRB, IBPS, NCS, state PSUs, district collectorates, plus private jobs
- Each notification carries: title, department, vacancy count, qualification, age limits with category relaxation, fee, important dates, official PDF link, apply link
- **Eligibility engine:** compares notification criteria to user profile → shows a green "You are eligible" / amber "You match 3 of 4" / grey "Not eligible — age limit" badge
- Filters: exam type, qualification, district, deadline, salary band
- "Save" and "Remind me before last date"
- **Every notification must link to the official source PDF.** Non-negotiable for trust.

### Module 2 — Exam Hub *(MVP — this is the SEO engine)*

One permanent page per exam, in each language.

Structure per page (this order matters for search ranking):
1. What the exam is, in two sentences
2. Latest notification status
3. Eligibility
4. Exam pattern (table)
5. Full syllabus (expandable, downloadable)
6. Previous year papers with answer keys
7. Previous cutoffs by category
8. Recommended free material from our library
9. Related exams
10. FAQ block (marked up with schema.org FAQPage)

**Why this matters:** a page titled "TGPSC Group 2 సిలబస్ 2026" that is genuinely the best Telugu page on that topic will rank on Google and bring free traffic for years. Ninety percent of our Year-1 acquisition comes from this module. Treat it as a product, not as content.

### Module 3 — Daily Quiz and Streaks *(MVP — this is retention)*

- 10 questions daily, published at 7:00 AM
- Mix: 5 current affairs, 3 static GK/subject, 2 previous-year questions
- Every question bilingual with a toggle
- Explanation shown after each answer, in the user's language
- Streak counter, weekly leaderboard (state-wide and district-wide)
- Streak freeze once a month, because exams and travel happen
- Result shareable as an image to WhatsApp status — free viral loop

### Module 4 — Material Library *(Phase 2)*

**The legal line, stated plainly:** we host only (a) government-published documents, (b) content we create, and (c) content users upload that they personally authored and warrant as their own. We do not host scanned coaching books. See Chapter 11.

- Category tree by exam → subject → topic
- Formats: PDF, and — importantly — mobile-readable HTML versions of the same content, because a 40 MB scan is useless on a ₹10,000 phone
- Contributor system: students upload their own handwritten notes → moderated → published with credit → contributor badge and leaderboard
- Download counter, save-for-offline via service worker

### Module 5 — Doubt Community *(Phase 2)*

- Post a doubt with text, image, or voice note
- Tag by exam and subject
- Answers with upvotes; asker marks a best answer
- Reputation points; privileges unlock at thresholds (edit others' tags at 200, moderate at 1000)
- **Search-before-you-ask:** as the user types, show similar existing questions. This is how we stop the same doubt being asked 1,000 times and how the archive becomes SEO gold.
- Verified badge for cleared candidates who prove selection

### Module 6 — Study Circles *(Phase 2)*

- Auto-created group per exam per language; user joins on selecting an exam
- Capped at 200 members, then a new circle spawns
- Daily auto-post: today's quiz, new notifications for that exam
- Peer accountability: "12 of your circle finished today's quiz"
- Moderated by reputation-holders

### Module 7 — Test Series *(Phase 3, first revenue)*

- Free: daily quiz, one free full-length mock per exam
- Paid: full mock series matching the real pattern and timing
- Post-test analytics: score, percentile, rank, time per question, topic-wise strength, comparison against top 10%
- "Your weak area is Indian Polity — here are 3 free resources" → routes back into the library

## 6.3 Multi-language architecture (concept level; implementation in Volume 2)

**Rollout, committed:**

| Phase | Languages | Trigger to add next |
|---|---|---|
| 1 | Telugu, English | Launch |
| 2 | Hindi, Tamil | 500k MAU and content pipeline proven |
| 3 | Kannada, Marathi, Bengali | 2M MAU |

**Do not launch eight languages at once.** Half-empty language versions produce thin pages, which Google penalises, which destroys the SEO engine that the whole business depends on.

**Three separate layers, three separate mechanisms:**

| Layer | Content type | Mechanism | Human review |
|---|---|---|---|
| L1 | UI strings (buttons, labels, errors) | JSON language files | Once, at translation time |
| L2 | Editorial content (notifications, syllabus, questions) | Translatable JSON DB columns | **Mandatory for eligibility, dates, fees** |
| L3 | User-generated (doubts, answers) | Store original + lazy AI translation, cached | None — clearly labelled as machine translated |

**The one rule that protects us:** AI may draft translations of titles and descriptions. AI may **never** auto-publish eligibility criteria, dates, or fees. A wrong date means a student misses an exam and we lose them forever.

**URL structure:** `/te/...` and `/en/...` as subdirectories, with hreflang tags. Never a query parameter, never a subdomain. Slugs stay identical across languages so links and backlinks do not fragment.

---

# Chapter 7 — Differentiation and Moat

> **సారాంశం:** కాపీ చేయలేని మూడు విషయాలు — డేటా, కమ్యూనిటీ, SEO.

A feature can be copied in a sprint. Ask instead: *what compounds?*

| Asset | Why it compounds | Time for a competitor to replicate |
|---|---|---|
| **Structured Telugu exam database** — every notification, syllabus, cutoff, paper, since launch, normalised and tagged | Data gets more valuable with age. Cutoff trends over 5 years cannot be back-filled. | 2–3 years |
| **SEO position** on Telugu exam queries | Ranking is won by age + backlinks + engagement. A page that has ranked for three years is very hard to displace. | 2–4 years |
| **Community archive** of answered doubts | Each answered doubt is a permanent asset that ranks, helps future users, and cost us nothing | 2–3 years, and only with our traffic |
| **Habit** — being the 7 AM app | Habit is the hardest thing in consumer product to displace | Indefinite, if we do not break it |
| **Verified contributor network** | Selected candidates who answer doubts and vouch for the brand | 1–2 years |

**What is NOT a moat:** the tech stack, the UI, the quiz format, the feature list. Assume all of it is copied within six months of us becoming visible. Everything above is what actually holds.

---

# Chapter 8 — Business Model

> **సారాంశం:** కంటెంట్ ఉచితం. ప్రకటనలే ప్రధాన ఆదాయం. తర్వాత టెస్ట్ సిరీస్.

## 8.1 The principle

**The core stays free forever.** Notifications, syllabus, daily quiz, community, and basic material are never paywalled. This is a strategic decision, not charity: our moat is traffic and habit, and a paywall kills both. Adda247 converts about 5% of its 40 million users to paid — meaning 95% of the audience monetises through something other than subscriptions, or not at all. We monetise that 95%.

## 8.2 Revenue streams, in the order they switch on

### Stream 1 — Programmatic display ads (from ~5,000 DAU)

- Google AdSense / Ad Manager
- Placements: feed (native, every 5th card), between exam-page sections, pre-download on PDFs
- **Never** on a syllabus page mid-content. Never a full-screen interstitial on first visit.
- Realistic Indian education-vertical RPM: ₹25–70 per 1,000 pageviews, higher in notification season

### Stream 2 — Direct advertising (from ~20,000 DAU) — *this is the real business*

Sold directly to:
- Coaching institutes (Ashok Nagar, Dilsukhnagar, Vijayawada, Guntur clusters)
- Study hostels and libraries
- Book publishers
- Local service businesses targeting the 20–30 age group

Why direct beats programmatic here: we can offer **district-level and exam-level targeting**. A Karimnagar coaching centre only wants Karimnagar Group-2 aspirants. Nobody else can sell them that. Direct deals typically fetch 5–10x programmatic RPM.

Pricing model to start with:
| Product | Price |
|---|---|
| Feed banner, district-targeted, 30 days | ₹5,000–15,000 |
| Exam-page sponsorship, 30 days | ₹10,000–25,000 |
| Sponsored daily quiz ("Today's quiz by X Academy") | ₹15,000/week |
| Newsletter / WhatsApp broadcast mention | ₹8,000 |

### Stream 3 — Premium subscription (from ~100,000 MAU)

₹199/year, deliberately cheap. Includes: ad-free, full mock test series, bulk PDF download, priority doubt answers, advanced analytics.

At 2% conversion on 1M MAU = 20,000 subscribers = ₹40 lakh/year.

### Stream 4 — Job postings (Year 2)

₹500–2,000 per private job posting, free for verified government notifications (which we want anyway for SEO).

### Stream 5 — Data and insight products (Year 3, optional)

Anonymised, aggregated trend reports: which districts prepare for what, cutoff prediction models. Sold to publishers and institutes. **Only aggregate, never individual, and only with clear consent.** See Chapter 11.

## 8.3 Unit economics

| Metric | Assumption | Note |
|---|---|---|
| Pageviews per MAU per month | 12 | Conservative; habit users do far more |
| Ad RPM (blended, Year 2) | ₹45 | Programmatic + direct blend |
| Revenue per MAU per month | ₹0.54 | 12 × 45 ÷ 1000 |
| Revenue per MAU per year | ₹6.48 | |
| Cost to serve one MAU per year | ₹1.20–2.00 | Infra + content, at scale |
| **Contribution margin per MAU** | **~₹4.50–5.30** | |
| CAC (organic-led) | ₹2–8 | SEO and WhatsApp are near-free; paid only for spikes |
| Payback period | Under 2 months | |

The economics only work at scale, and they work *well* at scale. This is a volume business, not a margin business. Plan accordingly: obsess over traffic and retention, not over ARPU, for the first two years.

## 8.4 Cost structure

| Stage | Monthly infra | Monthly content/ops | Team |
|---|---|---|---|
| 0–10k MAU | ₹2,000 | ₹0 (founder) | 1 |
| 10k–100k MAU | ₹8,000 | ₹40,000 (2 part-time content) | 2–3 |
| 100k–1M MAU | ₹45,000 | ₹2,50,000 (team of 5) | 8–10 |
| 1M–5M MAU | ₹2,50,000 | ₹10,00,000 | 25–30 |

---

# Chapter 9 — Go-to-Market

> **సారాంశం:** గూగుల్ సెర్చ్, వాట్సాప్, యూట్యూబ్ — ఈ మూడే మన మార్కెటింగ్.

## 9.1 Channel priority (in strict order)

### Channel 1 — SEO (60% of acquisition)

The single most important growth activity.

**The mechanic:** Telugu-language exam queries have high volume and low-quality competition. Our exam-hub pages and community archive can own them.

Target query patterns:
- `[exam] notification 2026 telugu`
- `[exam] syllabus in telugu pdf`
- `[exam] previous papers telugu`
- `[exam] cutoff marks`
- `[district] government jobs`
- `10th qualification government jobs telugu`

**Rules:**
- One page per exam per language, permanently updated, never a new page each year
- schema.org `JobPosting` on notifications → eligibility for Google Jobs listings
- schema.org `FAQPage` on exam hubs → rich results
- Sitemaps split by language, submitted separately
- Publish within 60 minutes of official release; being first matters enormously for ranking on breaking notifications

### Channel 2 — WhatsApp (25%)

The highest open-rate channel in AP/TS by a wide margin, and effectively free.

- One WhatsApp channel per major exam
- Daily quiz link at 7 AM, notification alerts as they break
- Shareable quiz-result images designed for WhatsApp status
- Long term: a WhatsApp bot that answers "what's new for Group 2?" — this reuses infrastructure already built for Fynicom

### Channel 3 — YouTube and Instagram Reels (10%)

- 30–60 second Telugu explainer for every major notification, published within 30 minutes
- Format: vertical, big Telugu text, voiceover, link in description and pinned comment
- Weekly current-affairs roundup

### Channel 4 — Campus and coaching ambassadors (5%)

- One student per degree college, given free premium and a referral code
- Their job: post in their college WhatsApp groups
- Leaderboard and small monthly rewards

### Not doing in Year 1
Paid ads (except small bursts on notification days), TV, print, influencer sponsorships. All are expensive and none compound.

## 9.2 Launch sequence

**Pre-launch (4 weeks before)**
- Seed 30 exam hub pages so the site is not empty on day one
- Build the notification database with the last 12 months of history
- Create WhatsApp channels, start posting *before* the product exists — build the list first
- Recruit 20 beta users from real coaching centres, in person

**Launch week**
- Launch on a day a major notification is expected — ride the traffic spike
- Ask every beta user to share the quiz result to status
- Post in every relevant Telegram group and Facebook group (as a genuine helpful link, not spam)

**Post-launch, first 90 days**
- Ship one improvement daily based on feedback
- Personally reply to every single support message
- Weekly retention review: if D7 retention is under 20%, stop building features and fix the loop

## 9.3 The critical sequencing rule

**Do not open the community until you have daily traffic.**

An empty forum is worse than no forum — it signals a dead product. Open Module 5 only when there are 5,000+ DAU, and seed it with 50 real questions answered by real people before opening it publicly.

---

# Chapter 10 — Metrics

> **సారాంశం:** ఒకే ఒక ముఖ్యమైన సంఖ్య — రోజూ ఎంతమంది వస్తున్నారు.

## 10.1 North Star Metric

**Weekly Active Aspirants who completed at least one meaningful action** (quiz attempt, material download, doubt post, or notification save).

Not signups. Not pageviews. Not app installs. Those can all be bought and all lie.

## 10.2 Metric tree

| Level | Metric | Year 1 target |
|---|---|---|
| **Acquisition** | Organic sessions/month | 400,000 |
| | Signup rate from organic | 6% |
| **Activation** | % who set exam preferences within first session | 50% |
| | % who attempt first quiz | 40% |
| **Retention** | D1 / D7 / D30 | 40% / 25% / 15% |
| | 7-day streak holders | 8% of MAU |
| **Engagement** | Quiz completion rate | 70% of starts |
| | Avg sessions/week per active user | 4.5 |
| **Revenue** | Ad RPM | ₹35 |
| | Direct ad clients | 10 |
| **Quality** | Notification publish latency | Under 60 min, 90th percentile |
| | Doubt first-answer time | Under 4 hours, median |
| | Translation review backlog | Under 24 hours |

## 10.3 Review cadence

- **Daily:** DAU, quiz completions, notification latency, errors
- **Weekly:** cohort retention curves, top search queries, community health
- **Monthly:** revenue, SEO ranking movements, competitor changes
- **Quarterly:** language expansion decision, roadmap re-plan

---

# Chapter 11 — Risks, Legal and Compliance

> **సారాంశం:** పైరసీ, తప్పు సమాచారం, డేటా చట్టం — ఈ మూడింటిని తేలికగా తీసుకుంటే ప్రాజెక్ట్ ముగిసిపోతుంది.

Read this chapter twice. Most student portals in India die here, not from lack of traffic.

## 11.1 Copyright — the biggest single risk

Every Telugu exam-prep Telegram channel distributes scanned coaching books. It is normal, it is widespread, and it is **illegal**. A single takedown notice or criminal complaint from a publisher can end the business.

**Policy, absolute:**

| Allowed | Not allowed |
|---|---|
| Government-published PDFs (notifications, syllabi, official papers) with attribution and source link | Scanned coaching institute books |
| Content we write ourselves | Uploaded content from any published book |
| Previous year question papers (facts, generally reproducible; still attribute) | PDFs with another brand's watermark |
| User notes the user personally authored, with an explicit warranty at upload | "Free" PDFs found elsewhere on the internet |

**Enforcement mechanics:**
- Upload flow requires an explicit checkbox: "This is my own work and I have the right to share it"
- Every upload goes through moderation before publication — no exceptions, even at scale
- Automated watermark/brand-logo detection on uploaded images and PDFs at Phase 3
- Published DMCA/takedown process with a real contact address and a 48-hour SLA
- Instant permanent ban for a user who uploads copyrighted material twice

**Say no to this even when it costs you users.** Piracy would give a short traffic spike and a permanent existential liability.

## 11.2 Accuracy — the reputational risk

If we publish a wrong last date and a student misses an exam, we have destroyed a year of someone's life and our own credibility.

**Controls:**
- Every notification requires two-source verification: the official PDF plus the official website listing
- Dates, fees, and eligibility are **never** AI-auto-published; a human reviews and approves
- Every notification page shows "Last verified: [date]" and links to the official source
- Prominent disclaimer: always confirm with the official notification before applying
- A visible, one-tap "Report an error" on every notification, routed to a queue with a 2-hour SLA

## 11.3 Data protection — DPDP Act 2023

We will hold names, dates of birth, qualifications, phone numbers, and district for lakhs of people. India's Digital Personal Data Protection Act applies.

**Requirements to build in from day one, not retrofit:**
- Clear, plain-language consent at signup, in the user's language, itemised by purpose
- Purpose limitation — profile data is used for eligibility matching, and we say so
- Right to access, correct, and delete; build a working "Download my data" and "Delete my account" from v1
- **Under-18 users:** verifiable parental consent is required, and behavioural advertising to children is restricted. Practical mitigation: set the minimum age at 18 for account creation, since our audience is graduates anyway, and make that explicit in terms.
- Breach notification process documented before launch
- Data minimisation — do not collect what we do not use. No Aadhaar. No caste certificate uploads. No address beyond district.
- Encryption at rest and in transit; access logging on all admin reads of user data

## 11.4 Scraping and terms of use

We ingest from government websites. Mitigations:
- Respect `robots.txt` and reasonable rate limits; identify our crawler honestly in the user agent
- Store facts (dates, vacancy counts, eligibility), not verbatim copies of long documents
- Always deep-link back to the official page rather than mirroring it
- Manual fallback: the pipeline must survive a source blocking us, so all ingestion has a human-entry path

## 11.5 Full risk register

| # | Risk | Likelihood | Impact | Mitigation |
|---|---|---|---|---|
| R1 | Copyright claim over hosted material | Medium | **Fatal** | Section 11.1 policy, enforced without exception |
| R2 | Publishing a wrong date/eligibility | High | High | Two-source verification, human review, visible correction path |
| R3 | Traffic spike takes the site down on notification day | High | High | Autoscaling, CDN caching, static fallback page, load-tested to 50x |
| R4 | Adda247 or Testbook launches a serious Telugu product | Medium | High | Move fast on SEO and community — both take years to displace |
| R5 | Community turns toxic or becomes an exam-leak channel | Medium | High | Moderation from day one, reputation gates, clear rules, quick bans |
| R6 | Ad revenue too thin at low traffic | High | Medium | Direct sales earlier than planned; keep cost base tiny until 100k MAU |
| R7 | DPDP non-compliance notice | Low | High | Build compliance in v1; do not defer |
| R8 | Founder bandwidth — this is a large scope for a small team | **High** | High | Ruthless MVP scope (Chapter 12); do not build Phase 2 before Phase 1 retention is proven |
| R9 | Google algorithm change destroys SEO traffic | Medium | High | Diversify into WhatsApp and app installs from Year 1; never be 90% SEO-dependent |
| R10 | Translation quality damages credibility | Medium | Medium | Human review layer, glossary, native-speaker editor on payroll from Month 4 |

---

# Chapter 12 — Roadmap

> **సారాంశం:** నాలుగు దశలు. ప్రతి దశకు ఒక షరతు ఉంది — అది తీరకపోతే ముందుకు వెళ్లకూడదు.

Each phase has an **exit criterion**. Do not start the next phase until it is met. This single discipline is what separates a shipped product from an abandoned one.

## Phase 0 — Foundation (Weeks 1–2)

- Competitor teardown week (Chapter 4 instructions)
- Domain, hosting, repo, CI/CD
- Design system, Telugu typography, component library
- Database schema finalised
- 30 target exams identified and prioritised

**Exit criterion:** schema approved, one styled page rendering correctly in Telugu on a real budget phone.

## Phase 1 — MVP (Weeks 3–14)

Scope, and nothing more:
- Auth (phone OTP) and user profile with eligibility fields
- Multi-language routing and UI (te/en)
- Exam Hub — 30 exams, fully populated in both languages
- Notification feed with eligibility matching
- Daily quiz with streaks and leaderboard
- Push notifications (web push + WhatsApp)
- Admin panel for content team
- SEO: sitemaps, hreflang, schema.org, analytics

**Exit criterion:** 5,000 MAU, D7 retention above 20%, notification publish latency under 60 minutes. **If D7 retention is below 20%, do not proceed — fix the loop.**

## Phase 2 — Community and Content (Months 4–8)

- Material library with contributor uploads and moderation
- Doubt community with reputation
- Study circles
- Offline mode via service worker
- AdSense live
- Hindi and Tamil groundwork

**Exit criterion:** 100,000 MAU, 500+ community posts/week, first ₹1 lakh in ad revenue.

## Phase 3 — Monetisation and Scale (Months 9–15)

- Full test series with analytics
- Premium subscription
- Direct ad sales platform with district targeting
- Native mobile app (Flutter)
- Hindi and Tamil live
- Job posting marketplace

**Exit criterion:** 500,000 MAU, ₹5 lakh monthly revenue, positive contribution margin.

## Phase 4 — Expansion (Months 16–36)

- Kannada, Marathi, Bengali
- AI study assistant answering syllabus questions in Telugu
- Personalised study plans
- Live doubt sessions with verified toppers
- Cutoff prediction models from our own historical data

**Exit criterion:** 3 million MAU, ₹50 lakh monthly revenue.

---

# Chapter 13 — Financial Projection

> **సారాంశం:** మొదటి సంవత్సరం నష్టం. రెండో సంవత్సరం సమానం. మూడో సంవత్సరం లాభం.

## 13.1 Three-year base case

| | Year 1 | Year 2 | Year 3 |
|---|---|---|---|
| MAU (end of year) | 150,000 | 900,000 | 3,500,000 |
| DAU | 15,000 | 120,000 | 500,000 |
| Monthly pageviews | 1.8M | 12M | 48M |
| **Revenue** | | | |
| Programmatic ads | ₹5.4 L | ₹43 L | ₹1.7 cr |
| Direct ads | ₹2 L | ₹45 L | ₹2.6 cr |
| Premium subscriptions | — | ₹12 L | ₹90 L |
| Job postings | — | ₹4 L | ₹25 L |
| **Total revenue** | **₹7.4 L** | **₹1.04 cr** | **₹5.45 cr** |
| **Costs** | | | |
| Infrastructure | ₹0.6 L | ₹5 L | ₹30 L |
| Content and translation | ₹3 L | ₹30 L | ₹1.2 cr |
| Salaries | ₹6 L | ₹48 L | ₹2.4 cr |
| Marketing | ₹1 L | ₹8 L | ₹40 L |
| Legal, compliance, misc. | ₹1 L | ₹5 L | ₹20 L |
| **Total costs** | **₹11.6 L** | **₹96 L** | **₹4.5 cr** |
| **Net** | **−₹4.2 L** | **+₹8 L** | **+₹95 L** |

## 13.2 What has to be true

These projections rest on four assumptions. Test each one early and cheaply:

1. **Organic traffic grows to 400k sessions/month in Year 1.** Test by Month 4: are 10 exam pages ranking in the top 10 for their Telugu queries? If not, the SEO thesis is wrong and everything downstream fails.
2. **D7 retention exceeds 20%.** Test by Week 14. If the daily quiz does not produce habit, the entire model collapses. This is the highest-risk assumption.
3. **Blended ad RPM reaches ₹35–45.** Test by Month 8. If Indian education RPMs come in at ₹15, pull premium and direct sales forward by six months.
4. **Content production keeps up.** Test continuously. If notification latency drifts above 4 hours, we lose to Telegram and never recover.

## 13.3 Funding

Year 1 needs roughly ₹12 lakh. Options in preference order:
1. **Bootstrap** — feasible if built part-time; slower but retains full control and forces discipline
2. Small angel round (₹25–50 lakh) after Phase 1 exit criteria are met — the metrics, not the deck, raise the money
3. Institutional seed after Phase 3

**Recommendation: bootstrap through Phase 1.** Raising before D7 retention is proven means raising on a story. Raising after means raising on evidence, at a far better valuation.

---

# Chapter 14 — Team

> **సారాంశం:** మొదట ఇద్దరు చాలు. కంటెంట్ మనిషి డెవలపర్ కంటే ముఖ్యం.

## 14.1 Hiring sequence

| Order | Role | When | Why this order |
|---|---|---|---|
| 1 | Founder / full-stack (you) | Day 1 | Builds Phase 1 |
| 2 | **Telugu content editor** | Month 1 | The most underrated hire. Content velocity, not code velocity, is the constraint. Must be a former aspirant. |
| 3 | Content associate | Month 4 | Notification monitoring, daily quiz production |
| 4 | Frontend / mobile developer | Month 6 | Flutter app and UI depth |
| 5 | Community manager | Month 8 | Moderation, contributor relations |
| 6 | Ad sales | Month 12 | Direct sales is a relationship business |
| 7 | Backend developer #2 | Month 12 | Scale and reliability |

## 14.2 The non-obvious point

The instinct of a technical founder is to hire developers. **The constraint here is content, not code.** A Telugu content editor who was an aspirant themselves — who knows which exams matter, which sources are official, and what phrasing students actually search for — is worth more in Year 1 than a second engineer.

---

# Appendix A — Decision log

Decisions made, so they are not re-litigated every month:

| # | Decision | Rationale |
|---|---|---|
| D1 | Telugu-first, not Telugu-also | Differentiation from Adda247/Testbook |
| D2 | Free core, ad-funded | Traffic and habit are the moat; a paywall kills both |
| D3 | PWA before native app | Reach on cheap phones; no install friction; one codebase |
| D4 | Laravel stack | Founder's existing expertise; fastest route to shipped |
| D5 | Notifications as the wedge, not coaching | Capital-light, defensible, and where the daily need is |
| D6 | Zero tolerance on pirated material | Existential legal risk |
| D7 | Two languages at launch, not eight | Thin pages destroy SEO |
| D8 | Community opens only after 5,000 DAU | Empty forums signal a dead product |
| D9 | Human review on all dates and eligibility | One wrong date costs a user's year and our credibility |
| D10 | Bootstrap through Phase 1 | Raise on evidence, not on story |

# Appendix B — Glossary

| Term | Meaning |
|---|---|
| DAU / MAU | Daily / Monthly Active Users |
| D1, D7, D30 | Percentage of users returning 1, 7, 30 days after signup |
| RPM | Revenue per thousand pageviews |
| CAC | Customer acquisition cost |
| PWA | Progressive Web App — a website that installs and works offline like an app |
| hreflang | HTML tag telling Google which language version of a page to show |
| TAM / SAM / SOM | Total / Serviceable Available / Serviceable Obtainable Market |
| DPDP | India's Digital Personal Data Protection Act, 2023 |

# Appendix C — Sources

- IMARC Group — India Test Preparation Market and India Online Test Preparation Market reports
- Ken Research — India Test Preparation Market Size, Share & Forecast 2026–2032
- Technavio — India Test Preparation Market Analysis 2026–2030
- IAMAI & KANTAR — *Internet in India Report 2024*
- KPMG in India & Google — *Indian Languages: Defining India's Internet* (April 2017)
- Inc42 — reporting on Adda247's vernacular and pricing strategy
- CB Insights — Adda247 company profile (funding and stage)
- Indian Television — India's test prep market outlook to FY30
- Google Search Central — multi-regional and multilingual site guidance
- Ministry of Electronics and IT — Digital Personal Data Protection Act, 2023

*Market figures vary between analysts because of differing scope definitions. Where sources disagree, the more conservative figure has been used in planning.*

---

**End of Volume 1. Continue to Volume 2 — Technical Implementation Documentation.**
