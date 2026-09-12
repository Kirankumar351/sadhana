# Data ownership

**Read this before writing any migration.**

Every fact in the product has exactly one owner table, one set of people allowed to write
it, and a defined set of readers. Get this wrong and the same number ends up in three places,
drifting apart, and nobody can say which one is right.

**The rule that makes this a commercial product rather than a demo:**

> AI never becomes an owner. It proposes into a draft table, and a human write promotes it.
> If an AI feature needs a fact nobody owns, that is a schema gap, not a prompt problem.

---

## 1. Owner map

| Fact | Owner table | Written by | AI may |
|---|---|---|---|
| **Eligibility criteria** — age, qualification, relaxation, domicile | `notifications` | Content lead only, after PDF verification | Propose into review queue only |
| **Eligibility outcome** — is this person eligible | *none — computed* | `EligibilityService`, deterministic | **Never.** Routed away from the model entirely |
| **Dates and fees** | `notifications` | Content lead only | Extract into draft only |
| Syllabus structure | `exams.syllabus` | Content team | Read only |
| Cutoff history | `exam_cutoffs` | Content team, source URL required | Read only. **Never predicts** |
| **Questions and answer keys** | `questions` | Content team, after key verification | Draft into `ai_drafts` |
| Current affairs items | `news_items` | Pipeline draft → human approve | Draft only |
| Study material | `materials` | Upload + moderation, or generated + edited | Draft only |
| User profile | `profiles` | The user | Read only |
| Quiz performance | `quiz_attempts` | System, on submission | Read only |
| Entitlements | `entitlements` | `EntitlementService` only | Never |
| Payments | `payments` | Gateway webhook, after signature check | Never |
| **Retrieval corpus** | `ai_chunks` | *Derived* — rebuilt from owners | Derived, never authoritative |

---

## 2. How the corpus stays honest

```
OWNER TABLES              DERIVED            SERVED

exams.syllabus  ─┐
exam_cutoffs    ─┤
notifications   ─┼──►  ai_chunks  ──────►  retrieval  ──►  AI answer
materials       ─┤     (+ Qdrant)              │
questions       ─┤                             ▼
posts + answers ─┤                    no chunk = no answer
news_items      ─┘
```

Any write to an owner table fires `ReindexChunks` for that record. Delete an owner row and
its chunks go with it — **including the Qdrant points** — so the assistant can never quote a
notification that was cancelled or material that was taken down.

**This is the single most important wiring decision in the AI layer.** It means a correction
made once in the admin panel propagates to every AI answer within a minute. And it means
nobody can ever "fix" an AI answer by editing something the rest of the product cannot see.

### The deletion observer is the one people forget

If a material file is removed after a copyright complaint but its chunks stay in the corpus,
the assistant keeps quoting it. **The takedown is then incomplete in exactly the way that
matters legally.** This is why `deleteBySource()` exists on the `VectorStore` contract and
why the `Material` observer fires on unpublish as well as delete.

---

## 3. Observers — what triggers a reindex

| Model | Event | Action | Why it matters |
|---|---|---|---|
| `ExamNotification` | published, updated, deleted | `ReindexChunks` + purge CDN | Otherwise the assistant quotes a cancelled notification, or misses one published an hour ago |
| `Exam` | syllabus or pattern saved | `ReindexChunks` | A corrected syllabus must reach notes and question generation immediately |
| `Material` | approved, unpublished, deleted | `ReindexChunks` | Rejected material must leave the corpus, or a copyright removal is only half done |
| `Answer` | marked best | `ReindexChunks` | How the community archive compounds — each answered doubt answers the next person for free |
| `NewsItem` | published | `ReindexChunks` | Makes today's current affairs answerable today, not tomorrow |
| `Profile` | saved | Invalidate eligibility cache, mark study plan stale | A changed qualification changes dozens of eligibility results and the whole plan |
| `Subscription` | any status change | Flush entitlement cache | A cancelled subscription that still grants access is revenue leaking |

---

## 4. The twelve gaps from the Integration Map — status

All twelve are closed in the initial migration set.

### Blocking — nothing shipped without these

| # | Gap | Resolution |
|---|---|---|
| 1 | The retrieval corpus did not exist | `ai_chunks` created. Vectors in Qdrant (**D16**), not a MySQL VECTOR column, which MySQL 8 does not have. |
| 2 | The glossary was a screen with no table | `glossary` created. Ten features inject it. **Build it first** — half a day, no dependencies. |
| 3 | Nothing stored a target exam date | `user_exam_preferences.target_date` + `is_primary` |
| 4 | Per-user AI caps had nowhere to count | Index `(user_id, feature, created_at)` on `ai_requests`; Redis counter seeded from it |
| 5 | AI answers indistinguishable from human ones | `answers.is_ai` + `ai_request_id`. Sort is `is_ai ASC, is_best DESC, upvotes DESC`. AI earns no reputation and can never be best answer. |

### Missing screens and routes

| # | Gap | Resolution |
|---|---|---|
| 6 | Current affairs had no home | `news_items` + route `/{locale}/current-affairs/{date}` + its own sitemap. **The most SEO-valuable page type available** — daily fresh Telugu content on high-volume queries. |
| 7 | Ask Sadhana had no entry point | Persistent icon beside search on every page, plus a context-scoped "Ask about this job" |
| 8 | Flashcards had no table or scheduler | `flashcards` with `ease`, `interval_days`, `due_at` + nightly due-card job |
| 9 | Five AI admin screens were not in the sidebar | An `AI` group in the Filament sidebar, gated to Content lead and Owner |

### Policy holes

| # | Gap | Resolution |
|---|---|---|
| 10 | The push budget did not know about AI content | `notification_preferences` per type. Current affairs competes **inside** the five-a-day cap, never on top of it. |
| 11 | Generated material was not labelled publicly | `materials.source_type = 'ai_assisted'` + `edited_by`, shown as a badge with the editor's name |
| 12 | The DPDP export omitted AI data | Export and deletion now cover `ai_requests`, `study_plans`, `flashcards`, `answer_evaluations` and `agent_memories` |

---

## 5. Before you write a migration

1. **Which table owns this fact?** If the answer is "two of them", stop and fix that first.
2. **Who is allowed to write it?** If a student-facing fact can be written by anything other
   than a human review action, that is the bug.
3. **Does an AI feature read it?** Then an observer must reindex on change, and on delete.
4. **Is it money?** Integer paise. Never a float (**D15**).
5. **Is it translatable?** JSON column, and the slug is not translated.
6. **Is it personal data?** It belongs in the DPDP export and the deletion routine.
7. **Does it need an index?** Check the read/write matrix above for who queries it and how.
