# Phase 2.2 Release Closeout

**Версия:** 1.1  
**Дата:** 22 юли 2026 г.  
**Статус:** Closed — Phase 2.2 exited (2026-07-21)  
**Родителски документи:**

- [Phase2_2_Customer_Deployments.md](Phase2_2_Customer_Deployments.md) (§7 slices, §10 AC)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§14 Customer deployments, §5.15)
- [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md) (Closed — VCS sync Done)
- [MVP_Release_Closeout.md](MVP_Release_Closeout.md) (Closed — MVP 0.1 exited)

> Цел: затваряне и валидация на Phase 2.2 (Customer deployments + patch campaigns) преди следващите модули от Втора фаза (AI / Policy library / Auditor portal). Не въвежда нови големи модули извън §7 на Phase 2.2 плана.

---

## 1. Контекст

Phase 2.2 добавя org-level **customer register**, product-scoped **customer installations** (`product_deployments`) и **patch campaigns** с ръчен rollout tracking, audit trail и readiness интеграция.

Must slice-ът е без задължителен външен email/SMS provider. Should/Could разширенията (export, readiness gaps, CSV import, email stub, EOS cross-check, notification event log) са също доставени.

Този документ покрива:

1. acceptance criteria от Phase 2.2 §10 — текущ статус;
2. имплементационен checklist (Must / Should / Could);
3. closeout backlog (отложено извън 2.2);
4. exit criteria за „Phase 2.2 готов“;
5. pointer към следващите планове.

---

## 2. Acceptance criteria (Phase 2.2 §10) — статус

| #   | Критерий                                                                          | Статус | Бележки                                                               |
| --- | --------------------------------------------------------------------------------- | ------ | --------------------------------------------------------------------- |
| 1   | Owner създава customer и deployment за продукт/версия                             | Done   | Customer CRUD + Product deployments CRUD (2026-07-21)                 |
| 2   | Owner стартира patch campaign и вижда seed-нати targets                           | Done   | Draft → activate + auto-seed по §5 правило (2026-07-21)               |
| 3   | Owner маркира notified/updated; при `updated` deployment version = target version | Done   | Target status sync + `last_confirmed_at` (2026-07-21)                 |
| 4   | Viewer (`products.view` без manage) вижда списъците, но не manage-ва              | Done   | Feature tests view-only forbidden manage (2026-07-21)                 |
| 5   | Промените са в audit log                                                          | Done   | `customer_*`, `deployment_*`, `patch_campaign_*`, `campaign_target_*` |
| 6   | Няма задължителен външен messaging provider за Must                               | Done   | Email stub е Could + `CRA_CUSTOMER_NOTIFICATIONS_ENABLED`; не е Must  |

**Всички AC са изпълнени** (2026-07-21).

---

## 3. Имплементационни slices (§7) — статус

### Must (1–7)

| #   | Slice                                                   | Статус |
| --- | ------------------------------------------------------- | ------ |
| 1   | Migrations + models + enums                             | Done   |
| 2   | Customer CRUD (DataTable + internal API + audit)        | Done   |
| 3   | Product deployments CRUD                                | Done   |
| 4   | Patch campaign create/activate + auto-seed targets      | Done   |
| 5   | Target status updates + deployment version sync + audit | Done   |
| 6   | Feature tests (CRUD + campaign flow + view-only)        | Done   |
| 7   | i18n EN/BG (deployment model vs customer installations) | Done   |

### Should (8–11)

| #   | Slice                                                    | Статус |
| --- | -------------------------------------------------------- | ------ |
| 8   | Campaign auto-complete when all targets updated/excepted | Done   |
| 9   | Affected-customer XLSX export от campaign                | Done   |
| 10  | Readiness gap `unresolved_exposed_deployments`           | Done   |
| 11  | Bidirectional campaign ↔ vulnerability link              | Done   |

### Could (12–15)

| #   | Slice                                                       | Статус |
| --- | ----------------------------------------------------------- | ------ |
| 12  | Email notification stub (Mailable + queued job)             | Done   |
| 13  | CSV bulk import (customers + deployments + templates)       | Done   |
| 14  | Support-period cross-check (unsupported installations list) | Done   |
| 15  | Append-only notification event log per target               | Done   |

**15/15 slices Done.**

---

## 4. Доставени модули и routes (референция)

| Модул           | Nav / scope            | Ключови routes                                                        |
| --------------- | ---------------------- | --------------------------------------------------------------------- |
| Customers       | Top-level `/customers` | CRUD, `GET/POST /customers/import`, internal API                      |
| Deployments     | Product module         | CRUD, import, `GET .../deployments/unsupported`, internal API         |
| Patch campaigns | Product module         | CRUD, activate, target status, notify stub, XLSX export, internal API |
| Readiness       | Product module         | Gap `unresolved_exposed_deployments`, gap `unsupported_deployments`   |
| Vulnerabilities | Product module         | Linked campaigns на Edit + create preselect                           |

### Данни (нови таблици)

- `customers`
- `product_deployments`
- `patch_campaigns`
- `patch_campaign_targets`
- `patch_campaign_target_notification_events` (Could 15 — append-only)

### Конфигурация

- `CRA_CUSTOMER_NOTIFICATIONS_ENABLED` — email stub on/off (`config/customer_notifications.php`)

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.2 exit; **P1** = желателно polish; **P2** = след Phase 2.2 / друга фаза.

### P0 — валидация

1. **Must slices 1–7 end-to-end** — **Done** (2026-07-21)  
   Customer → deployment → campaign draft → activate → target status → audit.

2. **Viewer read-only enforcement** — **Done** (2026-07-21)

3. **Feature tests за Phase 2.2 модулите** — **Done** (2026-07-21)  
   59 dedicated tests (виж §8); full suite 340 passed / 2 skipped.

### P1 — polish (не блокира exit)

4. **Import errors flash на Index UI** — Optional; controller flash-ва `import_errors`, UI може да го render-ва по-късно.

5. **Real SMTP/provider credentials** — Извън 2.2; stub използва конфигурирания Laravel mailer.

### P2 — изрично извън Phase 2.2 (§3 out-of-scope)

6. **Customer self-service portal / magic-link confirmation**
7. **MDM / cloud inventory sync**
8. **Автоматични EOS „unsupported deployments remain“ emails** (dashboard buckets вече съществуват)
9. **`support_contract` поле** (§5.15)
10. **AI draft на customer communications**
11. **Auditor portal package**
12. **Отделни `customers.*` permission slugs** (manage остава `products.manage`)
13. **Production email/SMS/webhook provider integration** (beyond stub)

---

## 6. E2E flow — решение за closeout

| Стъпка | Действие                                              | Покрито от                          |
| ------ | ----------------------------------------------------- | ----------------------------------- |
| 1      | Регистриране на клиент (org)                          | Customer CRUD                       |
| 2      | Регистриране на инсталация (product × customer × env) | Deployments CRUD / CSV import       |
| 3      | Стартиране на patch campaign (optional vuln link)     | Campaign create + activate          |
| 4      | Уведомяване / потвърждение на target                  | Manual status + optional email stub |
| 5      | Sync на deployment version при `updated`              | Target status service               |
| 6      | Export / readiness / unsupported cross-check          | Should + Could slices               |
| 7      | Audit + notification event log                        | AuditLogger + append-only events    |

Automated E2E покритие: `CustomerDeploymentCampaignFlowTest`, `PatchCampaignTargetStatusTest`, `PatchCampaignCompletionTest`.

---

## 7. Exit criteria — „Phase 2.2 готов“

Phase 2.2 се счита за готов, когато:

1. Всички §10 acceptance criteria са **Done**. — **Done**
2. Всички Must slices (1–7) са **Done**. — **Done**
3. Should slices (8–11) по плана са **Done**. — **Done**
4. Could slices (12–15) по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти в customer / deployment / campaign flow. — **Done** (2026-07-21)
6. Feature тестовете минават локално / CI. — **Done** (2026-07-21: `php artisan test` — 340 passed, 2 skipped)

**Phase 2.2 е официално exited** (2026-07-21).

След exit:

- Phase 2.2 план: [Phase2_2_Customer_Deployments.md](Phase2_2_Customer_Deployments.md) → **Closed**;
- не се разширяват customer portal / MDM / real messaging provider в рамките на 2.2;
- следващи модули: AI assistant, Policy library, Auditor portal (§14 / генерален план).

---

## 8. Тестове (Phase 2.2 scope)

Dedicated feature test files (59 tests):

| Файл                                            | Област                       |
| ----------------------------------------------- | ---------------------------- |
| `CustomerCrudTest.php`                          | Customer CRUD + API + RBAC   |
| `CustomerDeploymentModelsTest.php`              | Models / relations           |
| `ProductDeploymentCrudTest.php`                 | Deployments CRUD             |
| `CustomerDeploymentCampaignFlowTest.php`        | E2E campaign flow            |
| `PatchCampaignCrudTest.php`                     | Campaign CRUD + seed         |
| `PatchCampaignTargetStatusTest.php`             | Target status + version sync |
| `PatchCampaignCompletionTest.php`               | Auto-complete                |
| `PatchCampaignExportTest.php`                   | XLSX export                  |
| `VulnerabilityPatchCampaignLinkTest.php`        | Campaign ↔ vulnerability     |
| `UnresolvedExposedDeploymentsReadinessTest.php` | Readiness gap (campaigns)    |
| `PatchCampaignEmailNotificationStubTest.php`    | Email stub + queue           |
| `CustomerDeploymentCsvImportTest.php`           | CSV import                   |
| `UnsupportedDeploymentsCrossCheckTest.php`      | EOS cross-check              |
| `PatchCampaignTargetNotificationLogTest.php`    | Notification event log       |

Full suite at exit: **340 passed**, **2 skipped**, **342 total**.

---

## 9. Препоръчителен ред на работа (closeout)

```text
1. Must 1–7 (core CRUD + campaigns)           ✓
2. Should 8–11 (completion, export, readiness, vuln link) ✓
3. Could 12–15 (email stub, CSV, EOS, event log) ✓
4. AC §10 validation                          ✓
5. Feature test pass                          ✓
6. Mark Phase 2.2 ready → next §14 modules    ✓
```

---

## 10. Следващ план

**[Phase 2.2 — Customer Deployments](Phase2_2_Customer_Deployments.md)** — **Closed** (2026-07-21).

**Следващи:** [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) — **Closed** ([Phase2_3_Release_Closeout.md](Phase2_3_Release_Closeout.md)).

**Активен:** [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) — User Security Instructions (§5.17).

Dependency chain:

```text
MVP 0.1 exit — Done 2026-07-20
    ↓
Phase 2.1 GitHub/GitLab — Closed 2026-07-21
    ↓
Phase 2.2 Customer deployments — Closed 2026-07-21
    ↓
Phase 2.3 Policy / Auditor / AI — Closed 2026-07-22
    ↓
Phase 2.4 User Security Instructions — Active
```

---

## 11. История на документа

| Версия | Дата       | Промяна                                                                |
| ------ | ---------- | ---------------------------------------------------------------------- |
| 1.1    | 2026-07-22 | Next pointer → Phase 2.4 (Phase 2.3 Closed)                            |
| 1.0    | 2026-07-21 | Formal Phase 2.2 exit; 15/15 slices Done; tests 340 passed; → §14 next |
