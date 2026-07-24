# Phase 2_E — Live connector smoke (Jira / Snyk / ADO)

**Версия:** 1.0  
**Дата:** 24 юли 2026 г.  
**Родител:** [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md) (Should 10)  
**Свързано:** [Phase2_8_Integrations_Operator_Runbook.md](Phase2_8_Integrations_Operator_Runbook.md), [Phase2_E_Ops_Baseline.md](Phase2_E_Ops_Baseline.md)

> Цел: **ръчен** smoke checklist за живи Jira Cloud / Snyk / Azure DevOps connectors в staging (или prod-like) org. **Не** е CI gate — feature tests остават на `Http::fake()`.

---

## 1. Принципи

| Правило        | Детайл                                                                            |
| -------------- | --------------------------------------------------------------------------------- |
| CI / tests     | Без live credentials; providers се покриват с `Http::fake()` feature tests        |
| Staging smoke  | Реални tokens в `.env` / Settings UI само на операторска машина / staging secrets |
| Human review   | Sync създава **pending suggestions** — Accept / Dismiss е ръчен                   |
| Secrets        | Никога tokens в git, audit details, export snapshots или chat logs                |
| Sync now       | `dispatchSync` — работи **без** `queue:work`                                      |
| Scheduled sync | Изисква `schedule:run` + `queue:work` ([Ops baseline](Phase2_E_Ops_Baseline.md))  |

---

## 2. Предварителни условия

1. [ ] `php artisan ops:baseline-check` → exit 0 (за scheduled path; не е задължително само за Sync now).
2. [ ] Owner/product-manager роля в тестова organization (`is_active`).
3. [ ] Поне един Product за link + Sync.
4. [ ] Външни sandbox/project данни (не production customer data, ако е възможно).

Scopes / credentials: виж [Phase 2.8 runbook §2](Phase2_8_Integrations_Operator_Runbook.md).

---

## 3. Общ smoke поток (всеки connector)

```text
Settings → Integrations → Connect provider
        → Product Edit → Link target
        → Sync now
        → Pending suggestions (review gate)
        → /integrations/health (ok | soft_fail | failed)
```

След всеки Sync now:

1. [ ] Toast / redirect без unhandled 500.
2. [ ] `last_synced_at` се обновява на product link (или `last_error` / soft-fail е обясним).
3. [ ] Suggestions (ако има данни) са **pending** — нищо не е auto-accepted.
4. [ ] Evidence snapshot (ако connector го създава) е без raw credentials.
5. [ ] Audit: `integration_sync_succeeded` или failed/soft path без token в description.
6. [ ] `/integrations/health` показва реда за product + provider с очаквания health badge.

---

## 4. Jira Cloud

### Setup

1. [ ] Settings → Integrations → **Jira**: `base_url`, `email`, API token → Connect (status **active**).
2. [ ] Product Edit → Integrations → link **project key** (напр. sandbox `CRA`).
3. [ ] (Опционално) `sync_schedule = hourly` само ако ще тестваш scheduled path.

### Sync now smoke

1. [ ] **Sync now** на Jira product link.
2. [ ] Очаквай import на issues → pending **task** suggestions (или 0 при празен project — OK, ако health ≠ hard fail).
3. [ ] Отвори едно suggestion: title/external id налични; Accept създава Task; Dismiss маха pending.
4. [ ] Soft-fail (грешен project key / липсващи Browse права): `soft_fail` + `last_error`, **без** нови suggestions, job не „retry storm“.

### Scheduled (optional)

1. [ ] Worker + cron активни; schedule `hourly`/`daily`.
2. [ ] Нулирай `last_synced_at` или изчакай due → `integrations:sync-scheduled` или почакай cron.
3. [ ] Job се консумира; health се обновява.

---

## 5. Snyk

### Setup

1. [ ] Settings → Integrations → **Snyk**: API token (+ optional base URL) → Connect **active**.
2. [ ] Product Edit → link Snyk `org_id` + `project_id` (sandbox project с известни findings).

### Sync now smoke

1. [ ] **Sync now**.
2. [ ] Pending **vulnerability** suggestions от findings (или 0 + обясним soft-fail).
3. [ ] Accept може да предложи component mapping; не auto-create без Accept.
4. [ ] (Опционално) AI triage summary на pending finding — draft only; suggestion остава pending ([Live LLM](Phase2_E_Live_LLM_Enablement.md)).

### Negative / soft-fail

1. [ ] Невалиден `project_id` или token без View issues → soft-fail на health, без crash.

---

## 6. Azure DevOps

### Setup

1. [ ] Settings → Integrations → **Azure DevOps**: org slug, PAT (Work Items Read + Project/Team Read), optional base URL → Connect **active**.
2. [ ] Product Edit → link ADO **project** name/id.

### Sync now smoke

1. [ ] **Sync now**.
2. [ ] Pending **task** suggestions от work items (WIQL), или 0 при празен project.
3. [ ] Accept → Task; Dismiss → pending cleared.
4. [ ] Soft-fail при липсващ project / недостатъчен PAT scope — `last_error`, health soft_fail/failed според summary.

---

## 7. SARIF upload (optional companion)

Не е Jira/Snyk/ADO API, но често се пуска в същия smoke прозорец:

1. [ ] Settings → Integrations → enable **SARIF / Trivy**.
2. [ ] Product → upload валиден SARIF 2.1.0 → pending vulnerability suggestions.
3. [ ] Невалиден JSON → soft-fail + `last_error`, без suggestions.

---

## 8. Health / ops signals

1. [ ] `/integrations/health` — редовете за тестваните links са видими; export Markdown/PDF **без** tokens.
2. [ ] Settings → Integrations → „View sync health“ линкът работи.
3. [ ] Ако `sync_schedule` ≠ off и `QUEUE_CONNECTION=sync` (или stale jobs): ops banner е видим ([Should 9](Phase2_E_Cross_Phase_Polish.md)).

---

## 9. Какво **не** влиза в CI

| В CI                                            | Извън CI (този документ)                    |
| ----------------------------------------------- | ------------------------------------------- |
| `Http::fake()` Jira / Snyk / ADO feature tests  | Реален Atlassian / Snyk / ADO call          |
| `QueueHardeningTest`, `OpsBaselineScheduleTest` | Staging Sync now с живи tokens              |
| Stub AI triage                                  | Live provider triage върху imported finding |

Няма `--group=live-connectors` gate. По желание операторът може да запази попълнен checklist като internal run note (без secrets).

---

## 10. Минимален pass / fail

**Pass:** за всеки целеви connector (Jira **и/или** Snyk **и/или** ADO, според какво е wired в staging): Connect → Link → Sync now завършва предвидимо; suggestions остават на review gate; health/audit без leaked credentials.

**Fail:** 500 на Sync now; auto-accept; token във UI props / audit / export; scheduled path „тихо мъртъв“ при очакван hourly sync без ops hint / jobs backlog (виж ops baseline).

---

## 11. История

| Версия | Дата       | Промяна                                            |
| ------ | ---------- | -------------------------------------------------- |
| 1.0    | 2026-07-24 | Should 10 — live connector smoke checklist (no CI) |
