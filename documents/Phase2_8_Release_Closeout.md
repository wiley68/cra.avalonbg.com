# Phase 2.8 Release Closeout

**Версия:** 1.0  
**Дата:** 24 юли 2026 г.  
**Статус:** Closed — Phase 2.8 exited (2026-07-24)  
**Родителски документи:**

- [Phase2_8_Integration_Wave2.md](Phase2_8_Integration_Wave2.md) (§8 slices, §10 AC)
- [Phase2_8_Integrations_Operator_Runbook.md](Phase2_8_Integrations_Operator_Runbook.md) (ops: secrets, scopes, rate limits, threat model)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§7 Интеграции — Втора вълна, §14)
- [Phase2_7_Release_Closeout.md](Phase2_7_Release_Closeout.md) (Closed — Phase 2.7 exited; §8 кандидат D)
- [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md) (Closed — първа интеграционна вълна)

> Цел: затваряне и валидация на Phase 2.8 (Integration wave 2) преди планиране на следваща вълна. Не въвежда нови големи модули извън §8 на Phase 2.8 плана.

---

## 1. Контекст

Phase 2.8 доставя **втора интеграционна вълна** (§7) — compliance-relevant import от ALM + scanner tooling, с **human review gate** (Accept/Dismiss), без Jira clone и без собствен scanner engine:

1. Shared integration framework + Jira Cloud + Snyk (Must);
2. Schedule, sync hardening, SBOM mapping, readiness/dashboard, Azure DevOps, operator runbook (Should);
3. Dependabot/Renovate depth, SARIF/Trivy upload, support ticket link, AI finding triage, org health index, auditor export (Could).

Този документ покрива:

1. acceptance criteria от Phase 2.8 §10 — статус;
2. имплементационен checklist (Must / Should / Could);
3. closeout backlog (отложено извън 2.8);
4. exit criteria за „Phase 2.8 готов“;
5. pointer към следващо планиране (кандидат E / F / евентуален 2.9).

---

## 2. Acceptance criteria (Phase 2.8 §10) — статус

| #   | Критерий                                                                                   | Статус | Бележки                                      |
| --- | ------------------------------------------------------------------------------------------ | ------ | -------------------------------------------- |
| 1   | Owner свързва Jira Cloud и линква product → project; Sync now → pending task suggestions   | Done   | Settings + Product Edit                      |
| 2   | Owner Accept → Task с external ref; Dismiss не създава entity                              | Done   | Review gate                                  |
| 3   | Owner свързва Snyk и линква product → target; Sync now → pending vulnerability suggestions | Done   | Settings + Product Edit                      |
| 4   | Accept на finding създава ProductVulnerability; **няма** silent auto-create                | Done   | + SARIF / Dependabot path със същия gate     |
| 5   | Viewer вижда статуси/suggestions, но **не** manage-ва connectors / sync / accept           | Done   | RBAC tests                                   |
| 6   | Phase 2.1 GitHub/GitLab connect/sync/suggestion flows остават непроменени по контракт      | Done   | Паралелни integration tables; VCS недокоснат |
| 7   | Няма full Jira clone / two-way sync / собствен scanner engine в scope                      | Done   | Explicit out-of-scope запазен                |

**Всички AC са изпълнени** (2026-07-24). Оперативна smoke проверка с реални Jira/Snyk credentials остава препоръчителна (не блокира exit).

> **Ops note:** production schedule изисква queue worker за `integrations:sync-scheduled` / `vcs:sync-scheduled` (hourly). Manual **Sync now** остава `dispatchSync` и не зависи от worker. Виж [Phase2_8_Integrations_Operator_Runbook.md](Phase2_8_Integrations_Operator_Runbook.md).

---

## 3. Имплементационни slices (§8) — статус

### Must

| #   | Slice                                                                            | Статус |
| --- | -------------------------------------------------------------------------------- | ------ |
| 1   | Migrations + models + enums (integrations, links, sync runs, import suggestions) | Done   |
| 2   | Settings: connect / verify / disconnect **Jira Cloud** + audit                   | Done   |
| 3   | Product ↔ Jira link + Sync now → task suggestions + Accept/Dismiss → Task        | Done   |
| 4   | Settings **Snyk** + Product link + Sync now → vulnerability suggestions + Accept | Done   |
| 5   | Evidence immutable ref / snapshot on sync + AuditLogger                          | Done   |
| 6   | i18n EN/BG + feature tests (Http::fake; viewer cannot manage)                    | Done   |

### Should

| #   | Slice                                                                   | Статус |
| --- | ----------------------------------------------------------------------- | ------ |
| 7   | Scheduled sync (`off`/`hourly`/`daily`) + `integrations:sync-scheduled` | Done   |
| 8   | Manual sync hardening (unique job, soft-fail, `last_error`)             | Done   |
| 9   | Snyk findings → SBOM / `product_components` match                       | Done   |
| 10  | Readiness gaps + dashboard pending / failed integration counts          | Done   |
| 11  | Second ALM: **Azure DevOps** work items → task suggestions              | Done   |
| 12  | Operator runbook (secrets, scopes, rate limits, threat model)           | Done   |

### Could

| #   | Slice                                                                       | Статус |
| --- | --------------------------------------------------------------------------- | ------ |
| 13  | Renovate / deeper Dependabot PR links + `remediation_pr_url` + campaign CTA | Done   |
| 14  | SARIF / Trivy artifact upload → vulnerability suggestions                   | Done   |
| 15  | Customer support light link (`external_ticket_url` on vuln + incident)      | Done   |
| 16  | AI triage summary for imported findings (no auto-accept)                    | Done   |
| 17  | Org-level integrations health DataTable                                     | Done   |
| 18  | Auditor export: sync health Markdown/PDF                                    | Done   |

**Всички slices Done** (2026-07-24). План: [Phase2_8_Integration_Wave2.md](Phase2_8_Integration_Wave2.md) **v1.0** (Closed).

---

## 4. Доставени модули (референция)

| Повърхност            | Nav / scope                            | Ключови capabilities                                 |
| --------------------- | -------------------------------------- | ---------------------------------------------------- |
| Settings Integrations | `/settings/integrations`               | GitHub/GitLab + Jira + ADO + Snyk + SARIF connectors |
| Product Edit          | `/products/{id}/edit`                  | Link/sync + pending suggestions + AI triage          |
| Integration health    | `/integrations/health`                 | Org DataTable + Markdown/PDF auditor export          |
| Scheduler             | `integrations:sync-scheduled` (hourly) | Soft-fail + unique sync jobs                         |
| Readiness / Dashboard | product readiness + org dashboard      | Pending suggestions + failed sync counts             |

### Данни

- `organization_integrations`, `product_integration_links`, `integration_sync_runs`, `import_suggestions`
- VCS (2.1): `organization_vcs_connections`, `product_repositories`, `vcs_import_suggestions` — **не** пренаписани
- `product_vulnerabilities.remediation_pr_url`, `external_ticket_url`; `product_incidents.external_ticket_url`

### Конфигурация / reuse

- Encrypted credentials at rest; never in audit details
- Patterns: Settings Integrations, Product Edit suggestions, DomPDF export, AuditLogger, DataTable, stub AI
- Operators: [Phase2_8_Integrations_Operator_Runbook.md](Phase2_8_Integrations_Operator_Runbook.md)

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.8 exit; **P1** = polish; **P2** = извън 2.8.

### P0 — валидация

1. **Must/Should/Could slices Done** — **Done** (2026-07-24)
2. **§10 AC покрити в код + feature tests** — **Done** (2026-07-24)
3. **Няма отворени P0 дефекти в integration flows** — **Done** (2026-07-24)

### P1 — polish (не блокира exit)

4. **Production queue worker** — за scheduled sync (`integrations:sync-scheduled`, `vcs:sync-scheduled`).
5. **Live connector smoke** — реален Jira Cloud / Snyk / ADO / SARIF upload в staging org.
6. **Live LLM** — optional за AI finding triage / vulnerability triage (stub работи в tests).
7. **Parent-plan status sync** — актуализиран в този closeout / Nachalen план.
8. **SonarQube API** — остава optional; SARIF upload вече покрива Sonar SARIF export.

### P2 — изрично извън Phase 2.8 (§4 out-of-scope)

9. Full ALM clone / two-way sync (CRA → Jira writes)
10. Собствен scanner / pen-test engine
11. Container registries / OWASP Dependency-Check depth → евентуален **Phase 2.9**
12. **Merged-PR summary** (deferred от 2.1) → **кандидат E**
13. Billing / SSO / onboarding tiers → **кандидат F** (§15–§16)
14. DoC auto-sign / notified-body portal / SRP auto-submit

---

## 6. Exit criteria — „Phase 2.8 готов“

Phase 2.8 се счита за готов, когато:

1. Всички §10 acceptance criteria са **Done**. — **Done**
2. Всички Must slices са **Done**. — **Done**
3. Should slices по плана са **Done**. — **Done**
4. Could slices по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти. — **Done**
6. Feature тестовете за Integration wave 2 минават. — **Done** (2026-07-24; **125** related tests passed)

**Phase 2.8 е официално exited** (2026-07-24).

След exit:

- Phase 2.8 план: [Phase2_8_Integration_Wave2.md](Phase2_8_Integration_Wave2.md) → **Closed**;
- следващо планиране: **кандидат E** (cross-phase polish) и/или **кандидат F** (platform / go-to-market); евентуален scanner depth **2.9** само при нужда.

---

## 7. Тестове (Phase 2.8 scope — референция)

| Област               | Файлове (представителни)                                       |
| -------------------- | -------------------------------------------------------------- |
| Models               | `IntegrationWave2ModelsTest`                                   |
| Jira                 | `JiraIntegrationSettingsTest`, `ProductJiraIntegrationTest`    |
| Snyk                 | `SnykIntegrationTest`, `SnykComponentMatchTest`                |
| Azure DevOps         | `AzureDevOpsIntegrationTest`                                   |
| Schedule / hardening | `IntegrationScheduledSyncTest`, `IntegrationSyncHardeningTest` |
| Readiness            | `IntegrationReadinessDashboardTest`                            |
| RBAC                 | `IntegrationWave2RbacTest`                                     |
| Dependabot+          | `ProductVcsImportSuggestionTest`                               |
| SARIF                | `SarifIntegrationTest`                                         |
| AI triage            | `ImportSuggestionAiTriageTest`                                 |
| Health / export      | `IntegrationHealthIndexTest`, `IntegrationHealthExportTest`    |
| Support ticket URL   | `ProductVulnerabilityRegisterTest`, `ProductIncidentCrudTest`  |

---

## 8. Следващо планиране (след Phase 2.8)

**[Phase 2.8 — Integration wave 2](Phase2_8_Integration_Wave2.md)** — **Closed** (2026-07-24).

С **§14 Втора фаза** (модули A–C) и **§7 втора вълна** (кандидат D) приключени, следващите кандидати от [Nachalen плана](CRA_Compliance_Workspace_Nachalen_Plan.md):

| Приоритет (предложение) | Кандидат                 | Източник / бележки                                                                  |
| ----------------------- | ------------------------ | ----------------------------------------------------------------------------------- |
| E → **препоръчан next** | **Cross-phase polish**   | Queue workers, live LLM, GitHub **merged-PR summary** (deferred от 2.1), ops harden |
| F                       | Platform / go-to-market  | SSO, billing tiers, onboarding услуга (§15–§16) — по-късно                          |
| (опционално) 2.9        | Scanner / registry depth | Container registries, OWASP Dependency-Check, SonarQube **API** (извън 2.8 freeze)  |

### Препоръка

1. **Кандидат E (Cross-phase polish)** е най-логичният следващ plan skeleton: малки P1 slices, които отключват production reliability (queue), deferred 2.1 polish (merged-PR summary) и live AI — без нова голяма domain вълна.
2. **Кандидат F** (SSO/billing/onboarding) е смислен след като workspace-ът е стабилен за вътрешна употреба и има нужда от multi-tenant commercialisation.
3. **Phase 2.9** (допълнителни scanners/registries) — само ако клиент/ops изисква connectors извън Jira/ADO/Snyk/SARIF/VCS; иначе overlap с вече доставеното.

```text
MVP 0.1 exit — Done 2026-07-20
    ↓
Phase 2.1–2.7 — Closed
    ↓
Phase 2.8 Integration wave 2 — Closed 2026-07-24
    ↓
Candidate E polish (препоръчан next plan)  |  Candidate F platform  |  optional 2.9 scanners
```

---

## 9. История на документа

| Версия | Дата       | Промяна                                                                                        |
| ------ | ---------- | ---------------------------------------------------------------------------------------------- |
| 1.0    | 2026-07-24 | Formal Phase 2.8 exit; Must+Should+Could Done; pointer → Candidate E polish / F platform / 2.9 |
