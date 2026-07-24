# Phase 2.8 — Integration Wave 2

**Версия:** 0.1  
**Дата:** 24 юли 2026 г.  
**Статус:** Active — skeleton (след Phase 2.7 closeout; кандидат D)  
**Родителски документи:**

- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§7 Интеграции — Втора вълна, §14)
- [Phase2_7_Release_Closeout.md](Phase2_7_Release_Closeout.md) (Closed — Phase 2.7 exited; §8 кандидат D)
- [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md) (Closed — първа интеграционна вълна)

> **Цел на вълната:** втора интеграционна вълна (§7) — **compliance-relevant** import от ALM / scanner / dependency tooling, без да се превръща workspace-ът в Jira clone или собствен scanner engine.

> **Ред на имплементация (предложен):** scope freeze (кои конектори първи) → connector framework reuse от 2.1 → 1–2 Must connectors → evidence / vulnerability / task hooks → Should polish → Could depth.

> **Граница с вече доставеното:** Phase 2.1 покрива GitHub/GitLab sync, CI, Dependabot **suggestions**, webhooks, GitHub App. Phase 2.8 **надгражда** с ticket/work-item import и/или по-дълбоки scanner feeds — не пренаписва 2.1.

---

## 1. Цел

Да може производителят да:

- свърже **ALM** (напр. Jira / Azure DevOps) за compliance-relevant tickets → tasks / evidence references;
- импортира **scanner / dependency findings** (Snyk, Trivy, Renovate, …) като reviewable drafts (не silent auto-create);
- запази принципа от §7: само repository / scan / release / immutable reference данни;
- ползва съществуващите Vulnerability / Evidence / Tasks / SDL / Tech-doc модули като consumers.

---

## 2. Scope (in) — чернова

| Възможност               | Описание                                                             |
| ------------------------ | -------------------------------------------------------------------- |
| Connector framework      | Reuse / extend 2.1 provider patterns (credentials, sync jobs, audit) |
| ALM import (slice 1)     | Jira **или** Azure DevOps — tickets → CRA tasks / links              |
| Scanner import (slice 1) | Snyk **или** Trivy — findings → vuln / component suggestions         |
| Review gate              | Accept / dismiss drafts (като Dependabot suggestions в 2.1)          |
| Evidence hooks           | Immutable refs (URL, ID, timestamp, hash where available)            |
| RBAC / org settings      | Connect / disconnect / sync permissions                              |

### Кандидати от Nachalen §7 (вълна 2)

```text
Jira
Azure DevOps
Dependabot (depth beyond 2.1 suggestions — TBD)
Renovate
Snyk
Trivy
OWASP Dependency-Check
SonarQube
container registries
vulnerability feeds
customer support systems
```

> MVP за 2.8: **не** всички наведнъж. Избери 1 ALM + 1 scanner за Must; останалите → Should/Could или Phase 2.9.

---

## 3. Scope (out) — изрично

- Full ALM clone / two-way sync на всички issue fields
- Собствен vulnerability scanner / pen-test engine
- Silent auto-create на vulnerabilities без human review
- SRP / ENISA auto-submit
- Billing / SSO като част от wave 2 (отделен platform track)
- DoC auto-sign / notified-body portal

---

## 4. Зависимости и ред

```text
Phase 2.1 GitHub/GitLab — Closed (connector patterns + Dependabot suggestions)
Phase 2.2–2.7 product modules — Closed
    ↓
Phase 2.8 Integration wave 2 (този документ)
    ↓
(по-късно) Cross-phase polish / platform (SSO, billing) — TBD
```

Reuse:

- VCS provider / sync job / suggestion review UX от 2.1;
- Vulnerability register + Evidence + Tasks;
- AuditLogger, DataTable, queue workers, org settings patterns.

---

## 5. Имплементационен ред (Must → Should → Could) — чернова

### Must (предложение)

1. Scope freeze: избор на първи ALM + първи scanner + success metrics
2. Shared connector settings / credentials model (extend 2.1 where possible)
3. ALM connector MVP (read-only import + link to tasks)
4. Scanner connector MVP (findings → reviewable suggestions)
5. Audit + RBAC + feature tests
6. i18n EN/BG + ops runbook (secrets, rate limits)

### Should (предложение)

7. Scheduled sync + manual „Sync now“
8. Webhooks where vendor supports them
9. Map findings → existing SBOM / component rows
10. Readiness / dashboard hints for open import suggestions
11. Second scanner **или** second ALM (whichever delivers more value)
12. Docs: operator guide + threat model for stored tokens

### Could (предложение)

13. Renovate / deeper Dependabot campaign links
14. SonarQube / container registry slice
15. Customer support system light link (≠ deployments module rewrite)
16. AI triage summary for imported findings (human review)
17. Org-level integrations index
18. Export of sync health / last-error for auditors

---

## 6. MVP slice за 2.8 (резюме)

**Must** — 1 ALM + 1 scanner, reviewable imports, audit/RBAC/tests.

**Should** — schedule/webhooks, component mapping, second connector, ops docs.

**Could** — more vendors, AI triage, org index, auditor sync export.

---

## 7. Acceptance criteria (чернова)

1. Owner свързва избран ALM connector и вижда importнати tickets като reviewable links/tasks.
2. Owner свързва избран scanner и вижда findings като suggestions (Accept/Dismiss).
3. Няма silent vulnerability create без human action.
4. Viewer не manage-ва connectors / sync.
5. Phase 2.1 GitHub/GitLab flows остават непроменени по контракт.
6. Няма full Jira clone / собствен scanner в scope.

---

## 8. Отворени решения (преди Must 1)

1. **Първи ALM:** Jira Cloud vs Azure DevOps — кое е по-често за целевите SME?
2. **Първи scanner:** Snyk vs Trivy — SaaS API vs self-hosted CLI/CI artifact?
3. **Auth model:** OAuth app vs PAT vs GitHub-App-like install?
4. **Queue:** задължителен ли е production worker преди live connectors?
5. Паралелен ли е **кандидат E** (merged-PR summary, live LLM) като малък polish track?

---

## 9. История

| Версия | Дата       | Промяна                                                               |
| ------ | ---------- | --------------------------------------------------------------------- |
| 0.1    | 2026-07-24 | Skeleton след Phase 2.7 closeout — §7 Integration wave 2 (кандидат D) |
