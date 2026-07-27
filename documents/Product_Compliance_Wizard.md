# Product Compliance Wizard

**Версия:** 0.8  
**Дата:** 27 юли 2026 г.  
**Статус:** Done — Must / Should / Could complete  
**Родителски документи:**

- [Internal_Manual_Test_Plan.md](Internal_Manual_Test_Plan.md) (§4.1 spine, §9 wizard pack)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§14 следващо)

> **Цел:** product-scoped страница, която води потребителя по каноничната compliance пътека (стъпки 1→25), с акцент върху **следващия** модул и deep-link към съществуващите CRUD екрани.

---

## Решения (MVP)

| Тема           | Решение                                                                             |
| -------------- | ----------------------------------------------------------------------------------- |
| Route          | `GET /products/{product}/wizard`                                                    |
| Edit vs Wizard | Edit = данни; Wizard = пътека                                                       |
| Org prep (0)   | Извън product wizard                                                                |
| Optional       | 18 Incidents, 24 Auditor, 25 AI — в timeline; success при required **1–17 + 19–23** |
| Layout         | Една колона: completed → current card → upcoming                                    |
| CTA            | Само deep-link; без дублиране на форми                                              |
| Back от модул  | Seed return-back към wizard URL                                                     |

---

## Must

1. ~~Docs pointers + A_25 closeout в Internal plan~~ (същата вълна)
2. ~~Spine definition 1→25 + status resolution (readiness + product fields)~~
3. ~~Inertia Show: completed / current card (4 content sections) / upcoming / success~~
4. ~~ProductCard вход: title link + Wizard button; meta в wizard header~~
5. ~~i18n BG+EN shell + step content~~
6. ~~Feature tests (access, current step, success)~~
7. ~~Return-back: wizard е hub; CTA seed-ва wizard като origin~~

## Should (след MVP)

- ~~Attention signals beyond complete/empty~~
- ~~Org prep checklist deep links от header~~
- ~~Persist dismissed optional 24–25~~

## Could

- ~~Граф на side paths (§6)~~
- ~~Progress % strip~~

---

## Spine keys (§4.1)

| #   | Key                       | Required | Href target                      |
| --- | ------------------------- | -------- | -------------------------------- |
| 1   | `product`                 | yes      | Product Edit                     |
| 2   | `scope`                   | yes      | Product Edit (scope)             |
| 3   | `classification`          | yes      | Product Edit (classification)    |
| 4   | `versions`                | yes      | Versions index                   |
| 5   | `support_periods`         | yes      | Support periods                  |
| 6   | `vcs_integrations`        | yes      | Product Edit (integrations)      |
| 7   | `components`              | yes      | Components                       |
| 8   | `risks`                   | yes      | Risks                            |
| 9   | `requirements`            | yes      | Requirements                     |
| 10  | `controls`                | yes      | Controls                         |
| 11  | `evidence`                | yes      | Evidence                         |
| 12  | `tasks`                   | yes      | Tasks                            |
| 13  | `vulnerabilities`         | yes      | Vulnerabilities                  |
| 14  | `reporting`               | yes      | Vulnerabilities (reporting path) |
| 15  | `customers`               | yes      | Org Customers index              |
| 16  | `deployments`             | yes      | Deployments                      |
| 17  | `campaigns`               | yes      | Campaigns                        |
| 18  | `incidents`               | no       | Incidents                        |
| 19  | `sdl`                     | yes      | SDL                              |
| 20  | `security_instructions`   | yes      | USI                              |
| 21  | `technical_documentation` | yes      | Tech docs                        |
| 22  | `passport`                | yes      | Passport                         |
| 23  | `readiness`               | yes      | Readiness                        |
| 24  | `auditor`                 | no       | Auditor packages                 |
| 25  | `assistant`               | no       | AI Assistant                     |

---

## История

| Версия | Дата       | Промяна                                                         |
| ------ | ---------- | --------------------------------------------------------------- |
| 0.8    | 2026-07-27 | Incidents (18) → optional в spine (не блокира success)          |
| 0.7    | 2026-07-27 | Could: progress % strip за required 1–23                        |
| 0.6    | 2026-07-27 | Could: side paths граф (§6) в wizard + payload                  |
| 0.5    | 2026-07-27 | Should: persist dismissed optional 24–25 (DB + dismiss/restore) |
| 0.4    | 2026-07-27 | Should: org prep checklist deep links в wizard header           |
| 0.3    | 2026-07-27 | Should: attention signals (badge, reason, colored upcoming)     |
| 0.2.1  | 2026-07-27 | Must 1–7 отбелязани Done; Should/Could остават следваща вълна   |
| 0.2    | 2026-07-27 | MVP shipped: route, service, Show.vue, ProductCard, i18n, tests |
| 0.1    | 2026-07-27 | Skeleton Active — MVP scope от Internal §9.3                    |
