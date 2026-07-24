# Phase 2.7 Release Closeout

**Версия:** 1.0  
**Дата:** 24 юли 2026 г.  
**Статус:** Closed — Phase 2.7 exited (2026-07-24)  
**Родителски документи:**

- [Phase2_7_Technical_Documentation.md](Phase2_7_Technical_Documentation.md) (§7 slices, §9 AC)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§5.12 Technical Documentation Workspace, §14)
- [Phase2_6_Release_Closeout.md](Phase2_6_Release_Closeout.md) (Closed — Phase 2.6 exited; §8 кандидат C)
- [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) (Closed — USI §5.17)
- [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) (Closed — light tech-doc delta flag)

> Цел: затваряне и валидация на Phase 2.7 (Technical Documentation) преди планиране на следваща вълна. Не въвежда нови големи модули извън §7 на Phase 2.7 плана.

---

## 1. Контекст

Phase 2.7 доставя product-/version-scoped **Technical Documentation workspace** (§5.12) като **structured package**, отделно от USI, SDL board и Passport thin outline:

1. Packages + fixed §5.12 sections + generate-from-modules + publish lifecycle;
2. Version inherit / delta, export (PDF / Markdown / release ZIP), USI + SDL links;
3. Should: readiness gap, dedicated RBAC;
4. Could: org index, AI drafts, evidence freshness cron, SBOM dependency delta, manual conformity / DoC field packs.

Този документ покрива:

1. acceptance criteria от Phase 2.7 §9 — статус;
2. имплементационен checklist (Must / Should / Could);
3. closeout backlog (отложено извън 2.7);
4. exit criteria за „Phase 2.7 готов“;
5. pointer към следващо планиране (Phase 2.8).

---

## 2. Acceptance criteria (Phase 2.7 §9) — статус

| #   | Критерий                                                                           | Статус | Бележки                                                |
| --- | ---------------------------------------------------------------------------------- | ------ | ------------------------------------------------------ |
| 1   | Owner създава tech-doc package за продукт (опционално version-pinned)              | Done   | CRUD + inherit / version pin                           |
| 2   | Core §5.12 секции са налични; generated секции се попълват от модулни данни        | Done   | Generator + Refresh generated                          |
| 3   | Publish е одитируем; viewer вижда, но не manage-ва                                 | Done   | Lifecycle + `technical_documentation.view` / `.manage` |
| 4   | При нова версия може да се наследи предишен package и да се видят променени секции | Done   | `changed_since_parent` + dependency delta              |
| 5   | Export PDF/Markdown е наличен за release / assessment package                      | Done   | + release ZIP (Could 15)                               |
| 6   | USI и SDL **не** се заместват — само link / summary                                | Done   | Explicit boundary                                      |
| 7   | Няма автоматичен правен сертификат / DoC auto-sign в scope                         | Done   | Manual conformity checklist + DoC field pack only      |

**Всички AC са изпълнени** (2026-07-24). Оперативна smoke проверка в реална org остава препоръчителна (не блокира exit).

> **Ops note:** след Should 12 е нужен `php artisan db:seed --class=RolePermissionSeeder` на live DB за `technical_documentation.*`. Scheduler трябва да пуска `evidence:refresh-freshness` (Could 16).

---

## 3. Имплементационни slices (§7) — статус

### Must

| #   | Slice                                                                 | Статус |
| --- | --------------------------------------------------------------------- | ------ |
| 1   | Migrations + models + enums (package, sections, status, section keys) | Done   |
| 2   | CRUD + Index DataTable (product-scoped)                               | Done   |
| 3   | Section editor UI (authored + generated placeholders)                 | Done   |
| 4   | Generate-from-modules for core sections                               | Done   |
| 5   | Publish lifecycle + audit                                             | Done   |
| 6   | i18n EN/BG + feature tests (CRUD + viewer forbidden manage)           | Done   |

### Should

| #   | Slice                                                        | Статус |
| --- | ------------------------------------------------------------ | ------ |
| 7   | Version-pinned packages + inherit from previous published    | Done   |
| 8   | Delta UI (changed sections / stale evidence hints)           | Done   |
| 9   | PDF/Markdown export                                          | Done   |
| 10  | Link published USI + optional SDL run reference              | Done   |
| 11  | Readiness gap upgrade (beyond thin outline)                  | Done   |
| 12  | Dedicated `technical_documentation.*` permissions + nav card | Done   |

### Could

| #   | Slice                                                          | Статус |
| --- | -------------------------------------------------------------- | ------ |
| 13  | Org-level cross-product tech-doc index                         | Done   |
| 14  | AI draft for authored sections (human review)                  | Done   |
| 15  | Release ZIP export (PDF + Markdown + linked USI README)        | Done   |
| 16  | Auto-mark stale evidence when freshness expires                | Done   |
| 17  | Dependency / SBOM compare between versions in delta report     | Done   |
| 18  | Conformity assessment path checklist + DoC field pack (manual) | Done   |

**Всички slices Done** (2026-07-24). План: [Phase2_7_Technical_Documentation.md](Phase2_7_Technical_Documentation.md) **v2.0** (Closed).

---

## 4. Доставени модули (референция)

| Повърхност    | Nav / scope                               | Ключови capabilities                                  |
| ------------- | ----------------------------------------- | ----------------------------------------------------- |
| Org tech-doc  | `/technical-documentation`                | Cross-product DataTable                               |
| Product Index | `/products/{id}/technical-documentation`  | Server-side DataTable                                 |
| Create / Edit | same + `/{package}/edit`                  | Sections, generate, delta, USI/SDL, AI, packs, export |
| Export        | `…/export/{markdown\|pdf\|release}`       | Assessment package (+ ZIP)                            |
| Internal API  | `/internal-api/…/technical-documentation` | Product + org paginated lists                         |
| Scheduler     | `evidence:refresh-freshness`              | Daily derived freshness statuses                      |
| Readiness     | Passport / readiness report               | Published package + incomplete / USI gaps             |

### Данни

- `technical_documentation_packages` (+ version pin, supersedes, USI/SDL FKs, lifecycle)
- `technical_documentation_sections` (+ source, body, generated_payload, applicability, delta flag)
- Conformity packs: structured `generated_payload` (`conformity_assessment_checklist`, `declaration_of_conformity_fields`) synced to `body_markdown`

### Конфигурация / reuse

- AI: `CRA_AI_ENABLED` + tech-doc section draft (suggest → Apply → Save; pack sections excluded)
- Permissions: `technical_documentation.view` / `.manage` (`config/cra.php` + RolePermissionSeeder)
- Patterns: DataTable, USI/SDL, DomPDF export, AuditLogger, readiness, Evidence freshness

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.7 exit; **P1** = polish; **P2** = извън 2.7.

### P0 — валидация

1. **Must/Should/Could slices Done** — **Done** (2026-07-24)
2. **§9 AC покрити в код + feature tests** — **Done** (2026-07-24)
3. **Няма отворени P0 дефекти в tech-doc flows** — **Done** (2026-07-24)

### P1 — polish (не блокира exit)

4. **Live DB RolePermissionSeeder** — задължителен след deploy на `technical_documentation.*`.
5. **Scheduler** — потвърди `evidence:refresh-freshness` на production cron/schedule.
6. **Production queue / live LLM** — optional за AI section drafts.
7. **Parent-plan status sync** — актуализиран в този closeout / Nachalen план.
8. **Org-index filters** — optional (status / version chips).

### P2 — изрично извън Phase 2.7 (§3 out-of-scope)

9. Автоматичен правен DoC / auto-sign / notified-body portal
10. Full GRC document management / DMS / real-time collaboration
11. Scanner / pen-test engine
12. **Integration wave 2** (Jira, Snyk/Trivy, Dependabot depth, …) → **Phase 2.8**
13. Billing / SSO-специфични tech-doc tiers
14. Заместване на USI / SDL / Passport thin outline

---

## 6. Exit criteria — „Phase 2.7 готов“

Phase 2.7 се счита за готов, когато:

1. Всички §9 acceptance criteria са **Done**. — **Done**
2. Всички Must slices са **Done**. — **Done**
3. Should slices по плана са **Done**. — **Done**
4. Could slices по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти. — **Done**
6. Feature тестовете за Technical Documentation модула минават. — **Done** (2026-07-24; 74 tests)

**Phase 2.7 е официално exited** (2026-07-24).

След exit:

- Phase 2.7 план: [Phase2_7_Technical_Documentation.md](Phase2_7_Technical_Documentation.md) → **Closed**;
- следваща вълна: [Phase2_8_Integration_Wave2.md](Phase2_8_Integration_Wave2.md) (§7 втора вълна — кандидат D).

---

## 7. Тестове (Phase 2.7 scope — референция)

| Област             | Файлове                                           |
| ------------------ | ------------------------------------------------- |
| Model              | `TechnicalDocumentationModelTest`                 |
| CRUD               | `TechnicalDocumentationCrudTest`                  |
| Lifecycle          | `TechnicalDocumentationLifecycleTest`             |
| Inherit / delta    | `TechnicalDocumentationInheritTest`, `…DeltaTest` |
| USI / SDL links    | `TechnicalDocumentationUsiSdlLinkTest`            |
| Export             | `TechnicalDocumentationExportTest`                |
| Readiness          | `TechnicalDocumentationReadinessTest`             |
| RBAC               | `TechnicalDocumentationRbacTest`                  |
| Org index          | `OrganizationTechnicalDocumentationIndexTest`     |
| AI draft           | `TechnicalDocumentationAiDraftTest`               |
| Dependency delta   | `TechnicalDocumentationDependencyDeltaTest`       |
| Conformity / DoC   | `TechnicalDocumentationConformityPackTest`        |
| Evidence freshness | `RefreshEvidenceFreshnessCommandTest`             |

---

## 8. Следващо планиране (след Phase 2.7)

**[Phase 2.7 — Technical Documentation](Phase2_7_Technical_Documentation.md)** — **Closed** (2026-07-24).

С **§14 Втора фаза** (модулни workspace-и A–C) приключена, следващите кандидати от [Nachalen плана](CRA_Compliance_Workspace_Nachalen_Plan.md):

| Приоритет (предложение) | Кандидат                | Източник в плана                                                                |
| ----------------------- | ----------------------- | ------------------------------------------------------------------------------- |
| D → **Active**          | **Integration wave 2**  | §7 втора вълна — [Phase2_8_Integration_Wave2.md](Phase2_8_Integration_Wave2.md) |
| E                       | Cross-phase polish      | Queue workers, live LLM, GitHub merged-PR summary (deferred от 2.1)             |
| F                       | Platform / go-to-market | SSO, billing tiers, onboarding услуга (§15–§16) — по-късно                      |

### Препоръка за Phase 2.8

**Integration wave 2** е логичният следващ план:

- §14 product modules (USI, Incidents, SDL, Tech Docs) са **Closed**;
- §7 „Втора вълна“ още не е вълна с план — има само списък (Jira, Azure DevOps, Snyk, Trivy, Renovate, …);
- Phase 2.1 вече покрива GitHub/GitLab + Dependabot **suggestions**; wave 2 трябва да **надгражда** (ticket import / deeper scanner feeds), не да дублира 2.1;
- Cross-phase polish (E) може да върви паралелно като малки P1 slices, но не замества wave 2 като основна вълна.

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
Phase 2.7 Technical Documentation — Closed 2026-07-24
    ↓
Phase 2.8 Integration wave 2 — Active (skeleton)
```

---

## 9. История на документа

| Версия | Дата       | Промяна                                                                               |
| ------ | ---------- | ------------------------------------------------------------------------------------- |
| 1.0    | 2026-07-24 | Formal Phase 2.7 exit; Must+Should+Could Done; pointer → Phase 2.8 Integration wave 2 |
