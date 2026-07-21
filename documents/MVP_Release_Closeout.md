# MVP Release Closeout

**Версия:** 1.4  
**Дата:** 20 юли 2026 г.  
**Статус:** Closed — MVP 0.1 exited (2026-07-20) → Phase 2.1  
**Родителски документ:** [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§11 MVP, §13 седмици 11–12, §20)

> Цел: затваряне и валидация на MVP 0.1 преди старт на Втора фаза. Не въвежда нови големи модули.

---

## 1. Контекст

Задължителните 16 MVP модула (§11) плюс Product Compliance Passport (§18) са имплементирани в кода.

Този документ покрива:

1. checklist от §20 с текущ статус;
2. closeout backlog (останали gaps);
3. exit criteria за „MVP 0.1 готов“;
4. pointer към следващия план: Phase 2.1 GitHub/GitLab.

---

## 2. §20 — Преди MVP release (статус)

| #   | Критерий                                         | Статус | Бележки                                                                                                                                                |
| --- | ------------------------------------------------ | ------ | ------------------------------------------------------------------------------------------------------------------------------------------------------ |
| 1   | Поне два реални продукта са въведени             | Done   | Продукт A и B въведени (2026-07-20)                                                                                                                    |
| 2   | Поне една vulnerability е симулирана             | Done   | Vulnerability + reporting workflow OK (2026-07-20)                                                                                                     |
| 3   | Поне един incident е симулиран                   | Done   | Минимална симулация: task + vulnerability (discovery: incident investigation) + evidence (2026-07-20). Пълен Incident module (§5.10) остава извън MVP. |
| 4   | Поне един release е преминал през readiness gate | Done   | Readiness export OK (2026-07-20)                                                                                                                       |
| 5   | SBOM import работи за Composer                   | Done   | `SbomImportService` — CycloneDX JSON + `composer.lock`                                                                                                 |
| 6   | Regulatory content е versioned                   | Done   | Requirements catalogue + admin requirements                                                                                                            |
| 7   | Evidence има hash и history                      | Done   | Evidence repository с hash/download                                                                                                                    |
| 8   | Reporting deadlines от awareness timestamp       | Done   | Vulnerability reporting workflow                                                                                                                       |
| 9   | Ролите и approvals работят                       | Done   | RBAC + task approve/reject + reporting approval                                                                                                        |
| 10  | Readiness report може да бъде експортиран        | Done   | Readiness + reporting PDF export                                                                                                                       |
| 11  | Няма обещание за автоматична юридическа гаранция | Done   | Disclaimer прегледан (2026-07-20)                                                                                                                      |

---

## 3. MVP модули (§11) — имплементационен статус

| #   | Модул                              | Статус                                            |
| --- | ---------------------------------- | ------------------------------------------------- |
| 1   | Organizations и users              | Done                                              |
| 2   | Product register                   | Done                                              |
| 3   | Scope/classification questionnaire | Done                                              |
| 4   | Product versions                   | Done                                              |
| 5   | Support periods                    | Done (CRUD + dashboard 180/90/30 buckets)         |
| 6   | Requirements matrix                | Done                                              |
| 7   | Controls                           | Done                                              |
| 8   | Risk register                      | Done                                              |
| 9   | Component inventory                | Done                                              |
| 10  | SBOM import                        | Partial — CycloneDX + Composer; SPDX → backlog P2 |
| 11  | Vulnerability register             | Done                                              |
| 12  | Evidence repository                | Done                                              |
| 13  | Tasks and approvals                | Done                                              |
| 14  | Audit log                          | Done                                              |
| 15  | Readiness report                   | Done                                              |
| 16  | Reporting-deadline workflow        | Done                                              |
| —   | Product Compliance Passport (§18)  | Done                                              |

---

## 4. Closeout backlog

Приоритет: **P0** = блокира „MVP 0.1 готов“; **P1** = желателно преди announce; **P2** = може след MVP / във фаза 2.

### P0 — валидация и copy

1. **Два реални продукта end-to-end** — **Done** (2026-07-20)  
   Scope → classification → versions → support → risks → controls → components/SBOM → evidence → task → vulnerability + reporting → readiness + passport.

2. **Симулирана vulnerability с reporting** — **Done** (2026-07-20)  
   Awareness timestamps, 24h/72h/14d milestones, draft → approval → mark submitted, PDF export, audit events.

3. **Release през readiness gate** — **Done** (2026-07-20)  
   Поне една версия с преминат readiness review и експортиран report.

4. **Disclaimer review** — **Done** (2026-07-20)  
   Проверка, че UI/export/landing не обещават автоматична юридическа compliance гаранция.

### P1 — малки продуктови gaps

5. **Support period auto-известия (180/90/30 дни)** — **Done** (2026-07-20)  
   Dashboard actions: `support_ending_180` / `_90` / `_30` / `support_ended`; readiness escalate при ≤30 дни.

6. **Org / product dashboard polish** — **Done** (2026-07-20)  
   Допълнителни counts (risks, overdue_reporting); actions: overdue_tasks, releases_awaiting_approval; release readiness секция.

7. **Technical documentation outline (thin)** — **Done** (2026-07-20)  
   Readiness секция `technical_documentation` + outline в Compliance Passport (без отделен docs workspace).

### P2 — отложени

8. **SPDX SBOM import** — след CycloneDX/Composer; не блокира §20 Composer критерий.
9. **Repositories / deployments** — Втора фаза (GitHub/GitLab + Customer deployments).
10. **Security Incident Management (§5.10)** — отделен модул след MVP; не е MVP blocker.
11. **User Security Instructions, SDL workspace, AI, Auditor portal** — §14 / по-късно.

---

## 5. Incident симулация — решение за closeout

| Подход                | Решение                                                                                |
| --------------------- | -------------------------------------------------------------------------------------- |
| Пълен Incident module | **Не** в closeout / MVP                                                                |
| Минимална симулация   | **Done** (2026-07-20): Task (corrective) + linked vulnerability/evidence + audit trail |
| Документиране         | В генералния план: Incident module ≠ MVP blocker; §20 #3 покрит чрез прокси симулация  |

---

## 6. Exit criteria — „MVP 0.1 готов“

MVP 0.1 се счита за готов, когато:

1. Всички P0 точки от §4 са **Done**. — **Done**
2. Всички §20 редове са **Done** (#3 чрез минимална task/vuln/evidence симулация). — **Done**
3. Няма отворени P0 дефекти, които чупят основния flow (login → product → readiness/reporting). — **Done** (2026-07-20)
4. Feature тестовете за core модулите минават в CI / локално. — **Done** (2026-07-20: `php artisan test` — 218 passed, 2 skipped)
5. Генералният план отбелязва MVP модулите като Done и сочи към Phase 2.1. — **Done** (2026-07-20)

**MVP 0.1 е официално exited** (2026-07-20).

След exit:

- активен план: [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md);
- не се стартира пълен AI / auditor portal / deployments преди GitHub/GitLab 2.1 scope.

---

## 7. Препоръчителен ред на работа (closeout)

```text
1. Disclaimer / copy review          ✓
2. Product A end-to-end (реален)     ✓
3. Product B end-to-end (реален)     ✓
4. Vulnerability + reporting simulation ✓
5. Readiness gate + export           ✓
6. Minimal incident-like task/vuln simulation ✓
7. P1 items (support buckets, dashboard, thin docs outline) ✓
8. Mark MVP 0.1 ready → Phase 2.1   ✓
```

---

## 8. Следващ план

~~**[Phase 2.1 — GitHub/GitLab Integration](Phase2_1_GitHub_GitLab_Integration.md)**~~ — **Closed** (2026-07-21).

**Активен:** ~~[Phase 2.2 — Customer Deployments](Phase2_2_Customer_Deployments.md)~~ — **Closed** (2026-07-21). Closeout: [Phase2_2_Release_Closeout.md](Phase2_2_Release_Closeout.md).

- affected customers, patch campaigns, notification history, update confirmation — **Done**;
- виж Phase 2.1 closeout за VCS integration (еднопосочен sync — Done).

**Следващи:** AI / Policy library / Auditor portal (§14 — TBD).

---

## 9. История на документа

| Версия | Дата       | Промяна                                                                                               |
| ------ | ---------- | ----------------------------------------------------------------------------------------------------- |
| 1.4    | 2026-07-20 | Formal MVP 0.1 exit; feature tests OK (218 passed); → Phase 2.1                                       |
| 1.3    | 2026-07-20 | P1 polish Done: support 180/90/30, dashboard actions, technical documentation outline                 |
| 1.2    | 2026-07-20 | §20 #3 incident → Done (минимална task/vuln/evidence симулация)                                       |
| 1.1    | 2026-07-20 | P0 валидация: продукти A/B, vuln+reporting, readiness, disclaimer → Done; #3 incident остава Deferred |
| 1.0    | 2026-07-20 | Първоначален MVP Release Closeout план                                                                |
