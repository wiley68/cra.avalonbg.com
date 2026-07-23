# Phase 2.5 Release Closeout

**Версия:** 1.1  
**Дата:** 23 юли 2026 г.  
**Статус:** Closed — Phase 2.5 exited (2026-07-23)  
**Родителски документи:**

- [Phase2_5_Security_Incident_Management.md](Phase2_5_Security_Incident_Management.md) (§7 slices, §9 AC)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§5.10 Security Incident Management, §14)
- [Phase2_4_Release_Closeout.md](Phase2_4_Release_Closeout.md) (Closed — Phase 2.4 exited; §8 кандидат A)
- [MVP_Release_Closeout.md](MVP_Release_Closeout.md) (P2 #10 — deferred Incident module)

> Цел: затваряне и валидация на Phase 2.5 (Security Incident Management) преди планиране на следваща вълна. Не въвежда нови големи модули извън §7 на Phase 2.5 плана.

---

## 1. Контекст

Phase 2.5 доставя **product-scoped Security Incident** register (§5.10) като **отделен обект** от vulnerability:

1. CRUD + status lifecycle + core timestamps + append-only timeline;
2. link / create vulnerability (`incident_investigation`); tasks; audit;
3. Should: customers/deployments, closure, investigation fields, dashboard, export, dedicated RBAC;
4. Could: authority reports, customer communications, CIA/attack vector, AI summary draft, lessons → evidence/controls, org-wide index.

Този документ покрива:

1. acceptance criteria от Phase 2.5 §9 — статус;
2. имплементационен checklist (Must / Should / Could);
3. closeout backlog (отложено извън 2.5);
4. exit criteria за „Phase 2.5 готов“;
5. pointer към следващо планиране (Phase 2.6).

---

## 2. Acceptance criteria (Phase 2.5 §9) — статус

| #   | Критерий                                                               | Статус | Бележки                                                    |
| --- | ---------------------------------------------------------------------- | ------ | ---------------------------------------------------------- |
| 1   | Owner създава incident за продукт и записва awareness / classification | Done   | CRUD + core timestamps                                     |
| 2   | Owner добавя timeline events; историята е одитируема                   | Done   | Append-only timeline + audit                               |
| 3   | Incident може да се свърже с (или да създаде) vulnerability            | Done   | discovery `incident_investigation`                         |
| 4   | Viewer вижда incidents, но не manage-ва                                | Done   | `incidents.view` / `incidents.manage` (Should 12)          |
| 5   | Task може да сочи към incident (`subject_type=incident`)               | Done   | TaskService morph                                          |
| 6   | Vulnerability reporting wizard **не** се замества                      | Done   | CRA 24h/72h/final остава върху `ProductVulnerability`      |
| 7   | Няма SRP auto-submit / full orchestration в scope                      | Done   | Explicit out-of-scope; authority reports = manual log only |

**Всички AC са изпълнени** (2026-07-23). Оперативна smoke проверка в реална org остава препоръчителна (не блокира exit).

> **Ops note:** след Should 12 е нужен `php artisan db:seed --class=RolePermissionSeeder` на live DB, за да получат ролите `incidents.*` (owner/security_owner: view+manage; auditor/read_only: view).

---

## 3. Имплементационни slices (§7) — статус

### Must

| #   | Slice                                                            | Статус |
| --- | ---------------------------------------------------------------- | ------ |
| 1   | Migrations + models + enums (incident, timeline, version pivot)  | Done   |
| 2   | CRUD + Index DataTable (product-scoped)                          | Done   |
| 3   | Timeline UI (append events) + core timestamp fields              | Done   |
| 4   | Link / create vulnerability (`incident_investigation` discovery) | Done   |
| 5   | Task subject `incident` + basic audit events                     | Done   |
| 6   | i18n EN/BG + feature tests (CRUD + viewer forbidden manage)      | Done   |

### Should

| #   | Slice                                                         | Статус |
| --- | ------------------------------------------------------------- | ------ |
| 7   | Affected customers / deployments multi-select                 | Done   |
| 8   | Closure flow (closed_at / closed_by + optional approval task) | Done   |
| 9   | Root cause + corrective measures on Edit                      | Done   |
| 10  | Dashboard counts (`open_incidents`, unclassified)             | Done   |
| 11  | PDF/Markdown incident summary export                          | Done   |
| 12  | Product module nav card + dedicated `incidents.*` permissions | Done   |

### Could

| #   | Slice                                                             | Статус |
| --- | ----------------------------------------------------------------- | ------ |
| 13  | Authority reports (`incident_reports`) — manual submission record | Done   |
| 14  | Customer communications log (≠ patch campaigns)                   | Done   |
| 15  | CIA impacts + attack vector enums                                 | Done   |
| 16  | AI `incident_summary` draft (human review; no auto-save)          | Done   |
| 17  | Lessons learned → evidence / controls M2M                         | Done   |
| 18  | Org-level cross-product incident index                            | Done   |

**Всички slices Done** (2026-07-23). План: [Phase2_5_Security_Incident_Management.md](Phase2_5_Security_Incident_Management.md) **v1.9** (Closed).

---

## 4. Доставени модули (референция)

| Повърхност    | Nav / scope                 | Ключови capabilities                                  |
| ------------- | --------------------------- | ----------------------------------------------------- |
| Org Incidents | `/incidents`                | Cross-product DataTable + product column              |
| Product Index | `/products/{id}/incidents`  | Server-side DataTable                                 |
| Create / Edit | same + `/{incident}/edit`   | Timeline, vuln link, close, reports, comms, AI, links |
| Export        | `…/export/{pdf\|markdown}`  | Audit + CIA / lessons / evidence-controls             |
| Internal API  | `/internal-api/…/incidents` | Product + org paginated lists                         |
| Dashboard     | Organization dashboard      | Open / unclassified incident counts                   |

### Данни

- `product_incidents` (+ CIA / attack vector / investigation / closure columns)
- `incident_timeline_events` (append-only)
- pivots: versions, customers, deployments, evidence, controls
- `incident_reports`, `incident_customer_communications` (append-only)

### Конфигурация / reuse

- AI: `CRA_AI_ENABLED` + mode `incident_summary` (suggest → Apply → Save)
- Permissions: `incidents.view` / `incidents.manage` (`config/cra.php` + RolePermissionSeeder)
- Patterns: vulnerability UI, `TaskService`, deployments/customers, AuditLogger, DataTable

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.5 exit; **P1** = polish; **P2** = извън 2.5.

### P0 — валидация

1. **Must/Should/Could slices Done** — **Done** (2026-07-23)
2. **§9 AC покрити в код + feature tests** — **Done** (2026-07-23)
3. **Няма отворени P0 дефекти в incident flows** — **Done** (2026-07-23)

### P1 — polish (не блокира exit)

4. **Live DB RolePermissionSeeder** — задължителен след deploy на `incidents.*`.
5. **Production queue worker** — за AI draft при live provider (ако се ползва queue path).
6. **Live LLM keys** — optional; stub остава default за dev/CI.
7. **Parent-plan status sync** — актуализиран в този closeout / Nachalen план.
8. **Org-index deep link filters** — optional (status / product chips).

### P2 — изрично извън Phase 2.5 (§3 out-of-scope)

9. Full incident orchestration / SOAR
10. Автоматично SRP / ENISA submission
11. Merge с `VulnerabilityReportingService` (24h/72h/final)
12. Customer self-service incident portal
13. Real-time SIEM / log ingestion
14. Penetration-testing / scanner engine
15. Billing / SSO-специфични incident tiers

---

## 6. Exit criteria — „Phase 2.5 готов“

Phase 2.5 се счита за готов, когато:

1. Всички §9 acceptance criteria са **Done**. — **Done**
2. Всички Must slices са **Done**. — **Done**
3. Should slices по плана са **Done**. — **Done**
4. Could slices по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти. — **Done**
6. Feature тестовете за incident модула минават. — **Done** (2026-07-23)

**Phase 2.5 е официално exited** (2026-07-23).

След exit:

- Phase 2.5 план: [Phase2_5_Security_Incident_Management.md](Phase2_5_Security_Incident_Management.md) → **Closed**;
- следваща вълна: [Phase2_7_Technical_Documentation.md](Phase2_7_Technical_Documentation.md) (§5.12 — кандидат C; Phase 2.6 Closed).

---

## 7. Тестове (Phase 2.5 scope — референция)

| Област      | Файлове                         |
| ----------- | ------------------------------- |
| Model       | `ProductIncidentModelTest`      |
| CRUD        | `ProductIncidentCrudTest`       |
| RBAC        | `ProductIncidentRbacTest`       |
| Tasks/Audit | `ProductIncidentTaskAuditTest`  |
| Export      | `ProductIncidentExportTest`     |
| AI draft    | `ProductIncidentAiDraftTest`    |
| Org index   | `OrganizationIncidentIndexTest` |

---

## 8. Следващо планиране (след Phase 2.5)

**[Phase 2.5 — Security Incident Management](Phase2_5_Security_Incident_Management.md)** — **Closed** (2026-07-23).

Следващите кандидати от [Nachalen плана](CRA_Compliance_Workspace_Nachalen_Plan.md) / [Phase 2.4 closeout §8](Phase2_4_Release_Closeout.md):

| Приоритет (предложение) | Кандидат                           | Източник в плана                                                                   |
| ----------------------- | ---------------------------------- | ---------------------------------------------------------------------------------- |
| B → **Closed**          | **SDL workspace**                  | §5.14 — [Phase2_6_Release_Closeout.md](Phase2_6_Release_Closeout.md)               |
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

| Версия | Дата       | Промяна                                                                |
| ------ | ---------- | ---------------------------------------------------------------------- |
| 1.1    | 2026-07-23 | Pointer sync — Phase 2.6 Closed; Phase 2.7 Technical Docs Active       |
| 1.0    | 2026-07-23 | Formal Phase 2.5 exit; Must+Should+Could Done; pointer → Phase 2.6 SDL |
