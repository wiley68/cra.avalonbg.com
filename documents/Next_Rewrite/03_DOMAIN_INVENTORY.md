# Next Rewrite — Domain Inventory

**Версия:** 1.1  
**Laravel models:** 59 under `app/Models/`  
**Референс root:** локален клонинг на Laravel repo (`LARAVEL_REFERENCE_ROOT` на всяка машина)

---

## 1. Домейни (подредени)

| #   | Домейн                              | Сложност | Вълна      | Laravel примери                                         | Next targets (ориентир)                        |
| --- | ----------------------------------- | -------- | ---------- | ------------------------------------------------------- | ---------------------------------------------- |
| 1   | Auth & users                        | L        | 0–1        | `User`, Fortify, `Auth/*`, `pages/auth/*`               | Better Auth, `(auth)/*`, `(app)/users`         |
| 2   | Platform admin                      | L        | Admin ∥ 1+ | `admin/organizations/**`, `admin/requirements`          | `(admin)/organizations`, requirements          |
| 3   | Products core                       | L        | 2          | `Product`, `ProductVersion`, scope/classification       | `(app)/products/**`                            |
| 4   | Requirements & controls             | M        | 2          | `Requirement*`, `Control`, `ProductControl`             | products/requirements, controls; admin catalog |
| 5   | Risks                               | M        | 2          | `ProductRisk`                                           | products/risks                                 |
| 6   | SBOM / components                   | M        | 3          | `Sbom`, `ProductComponent`                              | products/components                            |
| 7   | Vulnerabilities + reporting         | L        | 3          | `ProductVulnerability`, `VulnerabilityReportSubmission` | products/vulnerabilities                       |
| 8   | Incidents                           | XL       | 6          | `ProductIncident` + timeline/reports/comms              | products/incidents, org incidents              |
| 9   | SDL                                 | L        | 6          | `SdlRun`, `SdlStageEntry`, `SdlException`               | products/sdl, org sdl                          |
| 10  | Technical documentation             | L        | 6          | `TechnicalDocumentationPackage`                         | products/technical-documentation               |
| 11  | USI                                 | M        | 6          | `UserSecurityInstruction*`                              | products/user-security-instructions            |
| 12  | Evidence & tasks                    | M        | 3          | `Evidence`, `Task`                                      | products/evidence, tasks                       |
| 13  | Customers / deployments / campaigns | XL       | 4          | `Customer`, `ProductDeployment`, `PatchCampaign*`       | customers, deployments, campaigns              |
| 14  | Policies & auditor                  | L        | 5          | `OrgPolicy`, `AuditorReviewPackage`, guest              | policies, auditor, guest token                 |
| 15  | Readiness / wizard / passport       | M        | 7          | Services + pages                                        | readiness, wizard, passport                    |
| 16  | AI assistant / RAG                  | XL       | 11         | `Ai*`, `ProductAssistantController`                     | products/assistant, jobs                       |
| 17  | Integrations (VCS + wave2)          | XL       | 9–10       | VCS + `OrganizationIntegration*`                        | settings/integrations, webhooks                |
| 18  | Billing & SSO                       | L        | 8          | Bank/Stripe/SSO models + settings                       | settings/billing, settings/sso                 |
| 19  | Audit / dashboard / i18n / settings | M        | 0–1, 5     | `AuditLog`, `Dashboard*`, `lang/*`                      | dashboard, audit-logs, settings                |

---

## 2. Laravel page map → Next routes

| Laravel Inertia (`resources/js/pages/`)             | Next App Router                   |
| --------------------------------------------------- | --------------------------------- |
| `auth/Login`, `Register`, …                         | `(auth)/login`, `register`        |
| `Dashboard.vue`                                     | `(app)/dashboard`                 |
| `users/*`                                           | `(app)/users`                     |
| `products/**`                                       | `(app)/products/...`              |
| `customers/*`                                       | `(app)/customers`                 |
| `controls/*`                                        | `(app)/controls`                  |
| `incidents/*`, `sdl/*`, `technical-documentation/*` | org-level indexes                 |
| `policies/*`, `auditor/*`                           | `(app)/policies`, `(app)/auditor` |
| `audit-logs/*`                                      | `(app)/audit-logs` + admin        |
| `settings/*`                                        | `(app)/settings/...`              |
| `admin/**`                                          | `(admin)/...`                     |
| `integrations/Health`                               | `(app)/integrations/health`       |

---

## 3. Ключови service класове (порт приоритет)

Копирай **поведението**, не PHP API 1:1:

| Laravel `app/Services/`                                                                                        | Вълна      |
| -------------------------------------------------------------------------------------------------------------- | ---------- |
| `OrganizationRegistrationService`, membership                                                                  | 1 / 8      |
| Product / Risk / Component / Vulnerability / Evidence / Task services                                          | 2–3        |
| `PatchCampaignService`, customer/deployment                                                                    | 4          |
| Auditor / OrgPolicy / AI services                                                                              | 5 / 11     |
| Incident / SDL / TechDoc / USI                                                                                 | 6          |
| `ProductReadinessService`, wizard                                                                              | 7          |
| `BankPaymentService`, `StripeBillingService`, `BillingDocumentService`, `OrganizationSsoService`, `OidcClient` | 8          |
| VCS + Integration sync / SARIF                                                                                 | 9–10       |
| `AuditLogger` / `AuditLogService`                                                                              | 0+ ongoing |
| `DashboardService`                                                                                             | 1          |

---

## 4. Phase docs ↔ вълни

| Doc                         | Вълна          |
| --------------------------- | -------------- |
| MVP / Nachalen product core | 2–3            |
| Phase 2.1 VCS               | 9              |
| Phase 2.2 Customers         | 4              |
| Phase 2.3 Policy/Auditor/AI | 5 + 11         |
| Phase 2.4 USI               | 6              |
| Phase 2.5 Incidents         | 6              |
| Phase 2.6 SDL               | 6              |
| Phase 2.7 Tech docs         | 6              |
| Phase 2.8 Integrations      | 10             |
| Phase 2_E polish/ops/LLM    | 1 / 11 / infra |
| Phase 2_F Billing/SSO       | 8              |
| Product Compliance Wizard   | 7              |

---

## 5. История

| Версия | Дата       | Промяна           |
| ------ | ---------- | ----------------- |
| 1.0    | 2026-07-29 | Initial inventory |
