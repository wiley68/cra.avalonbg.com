# Phase 2.6 Release Closeout

**Версия:** 1.0  
**Дата:** 23 юли 2026 г.  
**Статус:** Closed — Phase 2.6 exited (2026-07-23)  
**Родителски документи:**

- [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) (§7 slices, §9 AC)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§5.14 Secure Development Lifecycle, §14)
- [Phase2_5_Release_Closeout.md](Phase2_5_Release_Closeout.md) (Closed — Phase 2.5 exited; §8 кандидат B)
- [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md) (Closed — PR / CI evidence hooks)
- [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) (Closed — `secure_development` policy type)

> Цел: затваряне и валидация на Phase 2.6 (Secure Development Lifecycle) преди планиране на следваща вълна. Не въвежда нови големи модули извън §7 на Phase 2.6 плана.

---

## 1. Контекст

Phase 2.6 доставя product-scoped **SDL workspace** (§5.14) като **operational board**, отделно от Policy `secure_development` и USI:

1. SDL runs + fixed stage checklist + evidence links;
2. release security approval gate + exceptions + version pin;
3. Should: templates, Git quick-link, readiness gap, dedicated RBAC;
4. Could: org index, AI stage notes, export, Git suggest, post-release monitoring, light USI/tech-doc links.

Този документ покрива:

1. acceptance criteria от Phase 2.6 §9 — статус;
2. имплементационен checklist (Must / Should / Could);
3. closeout backlog (отложено извън 2.6);
4. exit criteria за „Phase 2.6 готов“;
5. pointer към следващо планиране (Phase 2.7).

---

## 2. Acceptance criteria (Phase 2.6 §9) — статус

| #   | Критерий                                                              | Статус | Бележки                                            |
| --- | --------------------------------------------------------------------- | ------ | -------------------------------------------------- |
| 1   | Owner създава SDL run за продукт и преминава през stage checklist     | Done   | CRUD + stage board                                 |
| 2   | Stage completion е одитируема; N/A / exception са явни                | Done   | Audit + exception owner/expiry/task                |
| 3   | Evidence може да се свърже към run/stage                              | Done   | pivots + Git quick-link / suggest                  |
| 4   | Release security approval е задължителен gate преди „approved“ status | Done   | Approve / revoke + lock; post-gate stages editable |
| 5   | Viewer вижда SDL, но не manage / approve-ва                           | Done   | `sdl.view` / `sdl.manage`                          |
| 6   | Org Policy `secure_development` и USI **не** се заместват             | Done   | Explicit boundary; light USI link only             |
| 7   | Няма Git merge-block enforcement / scanner engine в scope             | Done   | Explicit out-of-scope                              |

**Всички AC са изпълнени** (2026-07-23). Оперативна smoke проверка в реална org остава препоръчителна (не блокира exit).

> **Ops note:** след Should 12 е нужен `php artisan db:seed --class=RolePermissionSeeder` на live DB за `sdl.*`. След Could 18 — `php artisan migrate` за documentation link колоните.

---

## 3. Имплементационни slices (§7) — статус

### Must

| #   | Slice                                                       | Статус |
| --- | ----------------------------------------------------------- | ------ |
| 1   | Migrations + models + enums (run, stages, status)           | Done   |
| 2   | CRUD + Index DataTable (product-scoped)                     | Done   |
| 3   | Stage checklist UI (complete / N/A + notes)                 | Done   |
| 4   | Evidence link на stage / run                                | Done   |
| 5   | Release security approval gate + audit                      | Done   |
| 6   | i18n EN/BG + feature tests (CRUD + viewer forbidden manage) | Done   |

### Should

| #   | Slice                                                 | Статус |
| --- | ----------------------------------------------------- | ------ |
| 7   | Version-pinned SDL runs                               | Done   |
| 8   | Secure coding / threat checklist templates EN/BG      | Done   |
| 9   | GitHub/GitLab PR / CI artifact quick-link (reuse 2.1) | Done   |
| 10  | Exception handling (owner + expiry + task)            | Done   |
| 11  | Readiness gap `sdl_release_approval_missing`          | Done   |
| 12  | Dedicated `sdl.*` permissions + product nav card      | Done   |

### Could

| #   | Slice                                                 | Статус |
| --- | ----------------------------------------------------- | ------ |
| 13  | Org-level cross-product SDL index                     | Done   |
| 14  | AI draft for threat notes / checklist (human review)  | Done   |
| 15  | Export PDF/Markdown SDL summary for release package   | Done   |
| 16  | Auto-suggest evidence from recent Git sync            | Done   |
| 17  | Post-release monitoring checklist + dashboard counts  | Done   |
| 18  | Link SDL run → published USI / tech-doc delta (light) | Done   |

**Всички slices Done** (2026-07-23). План: [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) **v2.0** (Closed).

---

## 4. Доставени модули (референция)

| Повърхност    | Nav / scope                | Ключови capabilities                                    |
| ------------- | -------------------------- | ------------------------------------------------------- |
| Org SDL       | `/sdl`                     | Cross-product DataTable                                 |
| Product Index | `/products/{id}/sdl`       | Server-side DataTable                                   |
| Create / Edit | same + `/{run}/edit`       | Stages, evidence, Git, approval, AI, docs links, export |
| Export        | `…/export/{pdf\|markdown}` | Release package summary                                 |
| Documentation | `…/documentation`          | Published USI FK + tech-doc delta reviewed              |
| Internal API  | `/internal-api/…/sdl`      | Product + org paginated lists                           |
| Dashboard     | Organization dashboard     | `sdl_approved`, `sdl_pending_monitoring`                |

### Данни

- `sdl_runs` (+ version pin, approval, USI FK, tech-doc flag)
- `sdl_stage_entries` (+ notes / status / completer)
- `sdl_exceptions` (+ follow-up tasks)
- pivots: `sdl_run_evidence`, `sdl_stage_evidence`

### Конфигурация / reuse

- AI: `CRA_AI_ENABLED` + mode `sdl_stage_notes` (suggest → Apply → Save stage)
- Permissions: `sdl.view` / `sdl.manage` (`config/cra.php` + RolePermissionSeeder)
- Patterns: DataTable, Evidence, Git sync (2.1), USI published docs, AuditLogger, readiness

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.6 exit; **P1** = polish; **P2** = извън 2.6.

### P0 — валидация

1. **Must/Should/Could slices Done** — **Done** (2026-07-23)
2. **§9 AC покрити в код + feature tests** — **Done** (2026-07-23)
3. **Няма отворени P0 дефекти в SDL flows** — **Done** (2026-07-23)

### P1 — polish (не блокира exit)

4. **Live DB RolePermissionSeeder** — задължителен след deploy на `sdl.*`.
5. **Migrate documentation columns** — `2026_07_23_180000_add_documentation_links_to_sdl_runs_table`.
6. **Production queue / live LLM** — optional за AI stage notes.
7. **Parent-plan status sync** — актуализиран в този closeout / Nachalen план.
8. **Org-index filters** — optional (`current_stage=monitoring`, approved chips).

### P2 — изрично извън Phase 2.6 (§3 out-of-scope)

9. Full ALM / Jira clone / ticket import (Integration wave 2)
10. GitHub/GitLab merge-block enforcement
11. Penetration-testing / scanner engine
12. SIEM / real-time monitoring pipeline
13. Заместване на Policy `secure_development` или USI
14. Full Technical Documentation workspace / §5.12 version delta engine → **Phase 2.7**

---

## 6. Exit criteria — „Phase 2.6 готов“

Phase 2.6 се счита за готов, когато:

1. Всички §9 acceptance criteria са **Done**. — **Done**
2. Всички Must slices са **Done**. — **Done**
3. Should slices по плана са **Done**. — **Done**
4. Could slices по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти. — **Done**
6. Feature тестовете за SDL модула минават. — **Done** (2026-07-23)

**Phase 2.6 е официално exited** (2026-07-23).

След exit:

- Phase 2.6 план: [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) → **Closed**;
- следваща вълна: [Phase2_7_Technical_Documentation.md](Phase2_7_Technical_Documentation.md) (§5.12 — кандидат C).

---

## 7. Тестове (Phase 2.6 scope — референция)

| Област         | Файлове                      |
| -------------- | ---------------------------- |
| Model          | `SdlRunModelTest`            |
| CRUD / gate    | `ProductSdlCrudTest`         |
| RBAC           | `ProductSdlRbacTest`         |
| Version pin    | `ProductSdlVersionPinTest`   |
| Templates      | `ProductSdlTemplatesTest`    |
| Git quick-link | `ProductSdlGitQuickLinkTest` |
| Git suggest    | `ProductSdlGitSuggestTest`   |
| Exceptions     | `ProductSdlExceptionTest`    |
| Readiness      | `SdlReadinessTest`           |
| Org index      | `OrganizationSdlIndexTest`   |
| AI draft       | `ProductSdlAiDraftTest`      |
| Export         | `ProductSdlExportTest`       |
| Monitoring     | `ProductSdlMonitoringTest`   |
| USI / docs     | `ProductSdlUsiLinkTest`      |

---

## 8. Следващо планиране (след Phase 2.6)

**[Phase 2.6 — Secure Development Lifecycle](Phase2_6_Secure_Development_Lifecycle.md)** — **Closed** (2026-07-23).

Следващите кандидати от [Nachalen плана](CRA_Compliance_Workspace_Nachalen_Plan.md) / [Phase 2.4 closeout §8](Phase2_4_Release_Closeout.md):

| Приоритет (предложение) | Кандидат                           | Източник в плана                                                                   |
| ----------------------- | ---------------------------------- | ---------------------------------------------------------------------------------- |
| C → **Active**          | **Technical Documentation polish** | §5.12 — [Phase2_7_Technical_Documentation.md](Phase2_7_Technical_Documentation.md) |
| D                       | **Integration wave 2**             | §7 втора вълна (Jira, Snyk/Trivy, Dependabot, …)                                   |
| E                       | Cross-phase polish                 | Queue workers, live LLM, GitHub merged-PR summary                                  |

```text
MVP 0.1 exit — Done 2026-07-20
    ↓
Phase 2.1 GitHub/GitLab — Closed 2026-07-21
    ↓
Phase 2.2 Customer deployments — Closed 2026-07-21
    ↓
Phase 2.3 Policy / Auditor / AI — Closed 2026-07-22
    ↓
Phase 2.4 User Security Instructions — Closed 2026-07-23
    ↓
Phase 2.5 Security Incident Management — Closed 2026-07-23
    ↓
Phase 2.6 Secure Development Lifecycle — Closed 2026-07-23
    ↓
Phase 2.7 Technical Documentation — Active (skeleton)
```

---

## 9. История на документа

| Версия | Дата       | Промяна                                                                           |
| ------ | ---------- | --------------------------------------------------------------------------------- |
| 1.0    | 2026-07-23 | Formal Phase 2.6 exit; Must+Should+Could Done; pointer → Phase 2.7 Technical Docs |
