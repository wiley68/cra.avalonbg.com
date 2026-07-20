# MVP Release Closeout

**Версия:** 1.0  
**Дата:** 20 юли 2026 г.  
**Статус:** Planning / active  
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

| #   | Критерий                                         | Статус   | Бележки                                                                                                                                                                                                                            |
| --- | ------------------------------------------------ | -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 1   | Поне два реални продукта са въведени             | TODO     | Оперативна валидация с вътрешни продукти                                                                                                                                                                                           |
| 2   | Поне една vulnerability е симулирана             | TODO     | End-to-end: register → deadlines → reporting draft/approve/submit                                                                                                                                                                  |
| 3   | Поне един incident е симулиран                   | Deferred | Пълен Incident Management (§5.10) е извън MVP; „full incident orchestration“ е в „да не влиза“. Симулацията за closeout = task + vulnerability/evidence сценарий **или** отлагане към фаза след deployments. **Не е MVP blocker.** |
| 4   | Поне един release е преминал през readiness gate | TODO     | Product version + readiness Show + passport                                                                                                                                                                                        |
| 5   | SBOM import работи за Composer                   | Done     | `SbomImportService` — CycloneDX JSON + `composer.lock`                                                                                                                                                                             |
| 6   | Regulatory content е versioned                   | Done     | Requirements catalogue + admin requirements                                                                                                                                                                                        |
| 7   | Evidence има hash и history                      | Done     | Evidence repository с hash/download                                                                                                                                                                                                |
| 8   | Reporting deadlines от awareness timestamp       | Done     | Vulnerability reporting workflow                                                                                                                                                                                                   |
| 9   | Ролите и approvals работят                       | Done     | RBAC + task approve/reject + reporting approval                                                                                                                                                                                    |
| 10  | Readiness report може да бъде експортиран        | Done     | Readiness + reporting PDF export                                                                                                                                                                                                   |
| 11  | Няма обещание за автоматична юридическа гаранция | TODO     | UI copy / Welcome / exports — финален review                                                                                                                                                                                       |

---

## 3. MVP модули (§11) — имплементационен статус

| #   | Модул                              | Статус                                            |
| --- | ---------------------------------- | ------------------------------------------------- |
| 1   | Organizations и users              | Done                                              |
| 2   | Product register                   | Done                                              |
| 3   | Scope/classification questionnaire | Done                                              |
| 4   | Product versions                   | Done                                              |
| 5   | Support periods                    | Done (CRUD); auto-известия — closeout backlog     |
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

1. **Два реални продукта end-to-end**  
   Scope → classification → versions → support → risks → controls → components/SBOM → evidence → task → vulnerability + reporting → readiness + passport.

2. **Симулирана vulnerability с reporting**  
   Awareness timestamps, 24h/72h/14d milestones, draft → approval → mark submitted, PDF export, audit events.

3. **Release през readiness gate**  
   Поне една версия с преминат readiness review и експортиран report.

4. **Disclaimer review**  
   Проверка, че UI/export/landing не обещават автоматична юридическа compliance гаранция.

### P1 — малки продуктови gaps

5. **Support period auto-известия (180/90/30 дни)**  
   Scheduled job + dashboard/action или notification records според §5.16. Не е blocker за модул „Support periods“ (CRUD вече има).

6. **Org / product dashboard polish**  
   Допълване на action items според §10 (където липсват спрямо текущия `DashboardService`).

7. **Technical documentation outline (thin)**  
   Минимален outline в passport/readiness **или** изрично отлагане с бележка „не е MVP blocker“. Предпочитание: thin секция в passport/readiness, без отделен docs workspace.

### P2 — отложени

8. **SPDX SBOM import** — след CycloneDX/Composer; не блокира §20 Composer критерий.
9. **Repositories / deployments** — Втора фаза (GitHub/GitLab + Customer deployments).
10. **Security Incident Management (§5.10)** — отделен модул след MVP; не е MVP blocker.
11. **User Security Instructions, SDL workspace, AI, Auditor portal** — §14 / по-късно.

---

## 5. Incident симулация — решение за closeout

| Подход                | Решение                                                                                                             |
| --------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Пълен Incident module | **Не** в closeout                                                                                                   |
| Минимална симулация   | Task (corrective) + linked vulnerability/evidence + audit trail = достатъчно за §20 #3 като „практическа симулация“ |
| Документиране         | В генералния план: Incident module ≠ MVP blocker                                                                    |

---

## 6. Exit criteria — „MVP 0.1 готов“

MVP 0.1 се счита за готов, когато:

1. Всички P0 точки от §4 са **Done**.
2. Всички §20 редове с изключение на #3 (incident) са **Done**; #3 е покрит с минимална симулация **или** формално deferred в този документ.
3. Няма отворени P0 дефекти, които чупят основния flow (login → product → readiness/reporting).
4. Feature тестовете за core модулите минават в CI / локално.
5. Генералният план отбелязва MVP модулите като Done и сочи към Phase 2.1.

След exit:

- започва планиране/имплементация по [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md);
- не се стартира пълен AI / auditor portal / deployments преди GitHub/GitLab 2.1 scope.

---

## 7. Препоръчителен ред на работа (closeout)

```text
1. Disclaimer / copy review
2. Product A end-to-end (реален)
3. Product B end-to-end (реален)
4. Vulnerability + reporting simulation
5. Readiness gate + export
6. Minimal incident-like task/vuln simulation (или formal defer)
7. P1 items по капацитет (support notifications, dashboard, thin docs outline)
8. Mark MVP 0.1 ready → Phase 2.1
```

---

## 8. Следващ план

**[Phase 2.1 — GitHub/GitLab Integration](Phase2_1_GitHub_GitLab_Integration.md)**

- еднопосочен import/sync (repos, tags/releases, CI status, Dependabot/advisory signals, evidence snapshots);
- **без** пълна двупосочна синхронизация (§11 „да не влизат в първата версия“).

---

## 9. История на документа

| Версия | Дата       | Промяна                                |
| ------ | ---------- | -------------------------------------- |
| 1.0    | 2026-07-20 | Първоначален MVP Release Closeout план |
