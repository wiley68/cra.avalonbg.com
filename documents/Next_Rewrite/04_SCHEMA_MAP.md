# Next Rewrite — Schema Map

**Версия:** 1.0  
**Цел:** Следене кои Laravel таблици/модели влизат в Prisma по вълни.  
**Конвенция:** Prisma model camelCase; DB `@map("snake_case")`.

Източник: `database/migrations/*`, `app/Models/*` (59 models).

---

## 1. Etap 0 — минимален schema

| Prisma / таблица                                                     | Laravel                      | Бележки                                                               |
| -------------------------------------------------------------------- | ---------------------------- | --------------------------------------------------------------------- |
| Better Auth tables (`user`, `session`, `account`, `verification`, …) | `users` + Fortify/2FA fields | Map custom fields onto Better Auth `user` or side table `UserProfile` |
| `Organization`                                                       | `organizations`              | billing fields могат да са stub columns още в E0 (nullable)           |
| `OrganizationUser`                                                   | `organization_user`          | `roleId`, `invitedBy`, `joinedAt`                                     |
| `Role`                                                               | `roles`                      | slug unique                                                           |
| `Permission`                                                         | `permissions`                | slug unique                                                           |
| `RolePermission`                                                     | `permission_role`            | M2M                                                                   |
| `AuditLog`                                                           | `audit_logs`                 | write from day 1 for login etc.                                       |

**Организация — ключови колони (Laravel create migration):**  
`name`, `slug`, `is_active`, `subscription_plan`, `billing_status`, `billing_interval`, `payment_method`, `billing_activated_at`, `trial_ends_at`, `promo_code`, `billing_email`, `stripe_customer_id`, `stripe_subscription_id`, `sso_enabled`, `locale`, `onboarding_checklist_dismissed_at`, `billing_past_due_at`.

В Etap 0: създай колоните (за по-малко alter по-късно) **или** добавяй ги във Wave 8 — предпочитание: **създай nullable billing колони още в E0**, за да избегнеш болезнени alters.

---

## 2. По вълни — групи

### Вълна 1 (разширение auth/settings)

| Модел                             | Таблица(и)                                                                                     |
| --------------------------------- | ---------------------------------------------------------------------------------------------- |
| User custom                       | `must_change_password`, `appearance`, `two_factor_*`, `is_platform_admin`, `email_verified_at` |
| (optional) personal access tokens | skip unless needed                                                                             |

### Вълна 2 — Product core

| Модел                                             | Миграция / бележка                         |
| ------------------------------------------------- | ------------------------------------------ |
| `Product`                                         | `2026_07_17_120000_create_products_tables` |
| `ProductVersion`                                  | same                                       |
| `ProductScopeAssessment`                          | `..._product_scope_assessments`            |
| `ProductClassification`                           | `..._product_classifications`              |
| `ProductSupportPeriod`                            | `..._product_support_periods`              |
| `Regulation`, `Requirement`, `RequirementVersion` | `..._requirements_tables`                  |
| `ProductRequirement`, `ProductRequirementHistory` | same                                       |
| `Control`, `ProductControl`                       | `..._controls_tables`                      |
| `ProductRisk` + pivots                            | `..._product_risks_tables`                 |

### Вълна 3 — Evidence chain

| Модел                           | Миграция                             |
| ------------------------------- | ------------------------------------ |
| `Evidence` + `evidence_links`   | `..._evidence_tables`                |
| `Task`                          | `..._tasks_table`                    |
| `Sbom`, `ProductComponent`      | `..._sbom_components_tables`         |
| `ProductVulnerability` + pivots | `..._product_vulnerabilities_tables` |
| `VulnerabilityReportSubmission` | same                                 |

### Вълна 4 — Customers

| Модел                                  | Миграция                         |
| -------------------------------------- | -------------------------------- |
| `Customer`                             | `..._customer_deployment_tables` |
| `ProductDeployment`                    | same                             |
| `PatchCampaign`, `PatchCampaignTarget` | same                             |
| `PatchCampaignTargetNotificationEvent` | `..._notification_events`        |

### Вълна 5 — Policies / Auditor

| Модел                                    | Миграция                          |
| ---------------------------------------- | --------------------------------- |
| `OrgPolicy`                              | `..._organization_policies_table` |
| `AuditorReviewPackage`, `AuditorFinding` | `..._auditor_review_tables`       |

### Вълна 6 — Heavy modules

| Модел                                                         | Миграция                                |
| ------------------------------------------------------------- | --------------------------------------- |
| USI `UserSecurityInstruction*`                                | `..._user_security_instructions_tables` |
| Incidents `ProductIncident*` + timeline/reports/comms/lessons | `2026_07_23_09*` … `14*`                |
| SDL `SdlRun`, `SdlStageEntry`, `SdlException`, evidence links | `2026_07_23_15*` … `17*`                |
| Tech docs `TechnicalDocumentationPackage/Section`             | `2026_07_23_190000_*`                   |

### Вълна 7

Няма задължителни нови таблици (derived readiness/wizard/passport). Ако Laravel държи wizard state в product fields — преизползвай `Product`.

### Вълна 8 — Billing / SSO

| Модел                            | Миграция                                                         |
| -------------------------------- | ---------------------------------------------------------------- |
| Org billing columns              | вече в `organizations`                                           |
| `OrganizationBankPaymentRequest` | `2026_07_28_130000_*`                                            |
| `OrganizationBillingDocument`    | `2026_07_28_140000_*`                                            |
| `OrganizationSsoConnection`      | `2026_07_28_150000_*` — **encrypt `client_secret` at app layer** |

### Вълна 9 — VCS

| Модел                                                                                | Миграция              |
| ------------------------------------------------------------------------------------ | --------------------- |
| `OrganizationVcsConnection`, `ProductRepository`, `VcsSyncRun`, `VcsWebhookDelivery` | `2026_07_20_122602_*` |
| `VcsImportSuggestion`                                                                | `2026_07_20_163000_*` |

### Вълна 10 — Integrations wave2

| Модел                                                                                         | Миграция              |
| --------------------------------------------------------------------------------------------- | --------------------- |
| `OrganizationIntegration`, `ProductIntegrationLink`, `IntegrationSyncRun`, `ImportSuggestion` | `2026_07_24_100000_*` |

### Вълна 11 — AI

| Модел                         | Миграция              |
| ----------------------------- | --------------------- |
| `AiConversation`, `AiMessage` | `2026_07_22_140000_*` |
| `AiEmbeddingChunk`            | `..._160000_*`        |
| `AiAnalysisJob`               | `..._170000_*`        |

### Infra (Laravel only — прецени)

| Laravel                     | Next                                                    |
| --------------------------- | ------------------------------------------------------- |
| `jobs`, `cache`, `sessions` | Inngest + Better Auth sessions; skip Laravel job tables |
| `personal_access_tokens`    | skip unless API tokens needed                           |

---

## 3. Следене на прогрес (попълвай в Next repo)

| Вълна | Migrate име            | Дата | Done |
| ----- | ---------------------- | ---- | ---- |
| E0    | `0_init_auth_org_rbac` |      | [ ]  |
| 1     |                        |      | [ ]  |
| 2     |                        |      | [ ]  |
| 3     |                        |      | [ ]  |
| 4     |                        |      | [ ]  |
| 5     |                        |      | [ ]  |
| 6     |                        |      | [ ]  |
| 8     |                        |      | [ ]  |
| 9     |                        |      | [ ]  |
| 10    |                        |      | [ ]  |
| 11    |                        |      | [ ]  |

---

## 4. Как да четеш Laravel миграции

1. Отвори съответния файл под `{LARAVEL_REFERENCE_ROOT}/database/migrations/`.
2. Пренеси колони + FK + unique/index.
3. Enum-и като string колони + Zod (по-лесно) **или** Prisma enum със същите `.value` низове.
4. JSON колони → `Json` в Prisma.
5. `encrypted` casts → ciphertext `String`/`Text` + `lib/crypto.ts`.

---

## 5. История

| Версия | Дата       | Промяна                    |
| ------ | ---------- | -------------------------- |
| 1.0    | 2026-07-29 | Initial schema map by wave |
