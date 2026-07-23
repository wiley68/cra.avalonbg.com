# Phase 2.3 Release Closeout

**Версия:** 1.0  
**Дата:** 22 юли 2026 г.  
**Статус:** Closed — Phase 2.3 exited (2026-07-22)  
**Родителски документи:**

- [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) (§7 slices, §9 AC)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§6 AI, §14 Policy / Auditor / AI, §5.17 next)
- [Phase2_2_Release_Closeout.md](Phase2_2_Release_Closeout.md) (Closed — Phase 2.2 exited)
- [MVP_Release_Closeout.md](MVP_Release_Closeout.md) (Closed — MVP 0.1 exited)

> Цел: затваряне и валидация на Phase 2.3 (Policy library → Auditor portal → AI assistant) преди **Phase 2.4 User Security Instructions**. Не въвежда нови големи модули извън §7 на Phase 2.3 плана.

---

## 1. Контекст

Phase 2.3 доставя три свързани модула в фиксиран ред:

1. **2.3A Policy library** — org-scoped политики с lifecycle, templates, readiness gaps, evidence publish;
2. **2.3B Auditor portal** — read-only review packages, findings, export, guest magic link;
3. **2.3C AI assistant** — grounded chat, document analyse, campaign drafts, vuln triage, local RAG, queued jobs — винаги с human review (§6).

Този документ покрива:

1. acceptance criteria от Phase 2.3 §9 — текущ статус;
2. имплементационен checklist (Must / Should / Could по A/B/C);
3. closeout backlog (отложено извън 2.3);
4. exit criteria за „Phase 2.3 готов“;
5. pointer към Phase 2.4.

---

## 2. Acceptance criteria (Phase 2.3 §9) — статус

| #   | Критерий                                                                                             | Статус | Бележки                                                            |
| --- | ---------------------------------------------------------------------------------------------------- | ------ | ------------------------------------------------------------------ |
| 1   | Owner създава и одобрява поне една policy от всеки задължителен type (или documented exception)      | Done   | Policy CRUD + lifecycle + 6 policy types (2.3A)                    |
| 2   | Compliance owner създава auditor package; Auditor вижда passport/readiness/evidence и добавя finding | Done   | Package CRUD + read-only review + findings (2.3B)                  |
| 3   | Owner маркира finding remediation status; export на package е наличен                                | Done   | Remediation + ZIP/PDF export (2.3B)                                |
| 4   | User стартира AI chat; grounded отговор; disclaimer видим; AI не manage-ва                           | Done   | Chat + `AiContextBuilder` + §6 disclaimer; no write actions (2.3C) |
| 5   | Viewer/Auditor не може да променя policies или product data чрез AI/auditor UI                       | Done   | RBAC tests (policies + auditor portal + AI)                        |
| 6   | Промените са в audit log                                                                             | Done   | Policy / auditor / AI event types (без prompt secrets)             |

**Всички AC са изпълнени** (2026-07-22). Оперативна smoke проверка в реална org остава препоръчителна (не блокира exit).

---

## 3. Имплементационни slices (§7) — статус

### 2.3A Policy library

| Слой   | #     | Slice                                             | Статус |
| ------ | ----- | ------------------------------------------------- | ------ |
| Must   | 1–6   | Schema, CRUD, lifecycle, templates, i18n, tests   | Done   |
| Should | 7–9   | Readiness gaps, publish → Evidence, UI links      | Done   |
| Could  | 10–12 | Markdown preview/diff, Task on submit, PDF export | Done   |

### 2.3B Auditor portal

| Слой   | #    | Slice                                                         | Статус |
| ------ | ---- | ------------------------------------------------------------- | ------ |
| Must   | 1–6  | Schema, package CRUD, review UI, findings, export, RBAC tests | Done   |
| Should | 7–8  | Evidence preselect, email notify stub on share                | Done   |
| Could  | 9–10 | Guest magic link, finding → Task                              | Done   |

### 2.3C AI assistant

| Слой   | #     | Slice                                                        | Статус |
| ------ | ----- | ------------------------------------------------------------ | ------ |
| Must   | 1–6   | Config/stub, conversations, chat UI, context, audit, tests   | Done   |
| Should | 7–9   | OpenAI/Anthropic, document analyse, campaign draft generator | Done   |
| Could  | 10–12 | Local RAG embeddings, vuln triage suggestions, queued jobs   | Done   |

**Всички slices Done** (2026-07-22). План: [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) **v1.30** (Closed).

---

## 4. Доставени модули (референция)

| Модул        | Nav / scope                                           | Ключови повърхности                                  |
| ------------ | ----------------------------------------------------- | ---------------------------------------------------- |
| Policies     | Top-level `/policies`                                 | CRUD, lifecycle, preview/diff, PDF, publish evidence |
| Auditor      | Top-level `/auditor` + guest `/auditor/guest/{token}` | Packages, findings, review, export, magic link       |
| AI assistant | Product module `/products/{id}/assistant`             | Chat, analyse, draft, triage, queued jobs UI poll    |
| Readiness    | Product module                                        | Gaps `policies_missing` / `policies_review_due`      |

### Данни (нови / AI)

- `organization_policies` (+ templates)
- `auditor_review_packages`, `auditor_findings`, pivots, guest tokens
- `ai_conversations`, `ai_messages`
- `ai_embedding_chunks`
- `ai_analysis_jobs`

### Конфигурация

- `CRA_AI_ENABLED`, `CRA_AI_PROVIDER`, OpenAI/Anthropic keys
- `CRA_AI_RAG_*`, `CRA_AI_EMBEDDING_*`
- `CRA_AI_QUEUE_ENABLED`, `CRA_AI_QUEUE_TIMEOUT`
- Auditor guest link TTL (`CRA_AUDITOR_GUEST_LINK_TTL_DAYS`)

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.3 exit; **P1** = polish; **P2** = извън 2.3.

### P0 — валидация

1. **Must/Should/Could slices Done** — **Done** (2026-07-22)
2. **§9 AC покрити в код + feature tests** — **Done** (2026-07-22)
3. **Няма отворени P0 дефекти в policy / auditor / AI flows** — **Done** (2026-07-22)

### P1 — polish (не блокира exit)

4. **Production queue worker** — `php artisan queue:work` + `QUEUE_CONNECTION=database` за AI analyse/draft/triage/RAG.
5. **Live LLM keys** — optional; stub остава default за dev/CI.
6. **Parent-plan status sync** — актуализиран в този closeout / Nachalen план.

### P2 — изрично извън Phase 2.3 (§3 out-of-scope)

7. AI автономно затваря vulns / submit reports / определя CRA applicability
8. Notified-body portal / regulatory submission automation
9. Full external vector DB (локален RAG JSON chunks е Could 10; не „full pipeline“)
10. Real-time collaborative policy editing
11. Billing tier enforcement за auditor/AI
12. Org-level `/assistant` dashboard entry (optional Could в API notes)
13. User Security Instructions / SDL workspace → **Phase 2.4**

---

## 6. Exit criteria — „Phase 2.3 готов“

Phase 2.3 се счита за готов, когато:

1. Всички §9 acceptance criteria са **Done**. — **Done**
2. Всички Must slices (2.3A/B/C) са **Done**. — **Done**
3. Should slices по плана са **Done**. — **Done**
4. Could slices по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти. — **Done**
6. Feature тестовете за Phase 2.3 модулите минават. — **Done** (2026-07-22)

**Phase 2.3 е официално exited** (2026-07-22).

След exit:

- Phase 2.3 план: [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) → **Closed**;
- не се разширяват notified-body / auto-compliance / billing в рамките на 2.3;
- следващ план: [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md).

---

## 7. Тестове (Phase 2.3 scope — референция)

Dedicated feature tests (извадка):

| Област   | Файлове                                                                                                                                                                                                                                                                      |
| -------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Policies | `OrgPolicyLibraryTest.php` (+ related readiness/publish where applicable)                                                                                                                                                                                                    |
| Auditor  | `AuditorReviewPackageTest`, `AuditorFindingTest`, `AuditorPackageExportTest`, `AuditorPortalRbacTest`, `AuditorGuestMagicLinkTest`, `AuditorPackageShareNotificationStubTest`                                                                                                |
| AI       | `AiAssistantStubTest`, `AiConversationPersistenceTest`, `AiContextBuilderTest`, `AiRequestAuditTest`, `AiAssistantRbacTest`, `AiProviderAdapterTest`, `AiDocumentAnalyseTest`, `AiDraftGeneratorTest`, `AiVulnerabilityTriageTest`, `AiRagIndexTest`, `AiQueuedAnalysisTest` |

---

## 8. Следващ план

**[Phase 2.3 — Policy / Auditor / AI](Phase2_3_Policy_Auditor_AI.md)** — **Closed** (2026-07-22).

**Следващ (Active):** [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) — User Security Instructions / SDL workspace (§5.17).

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
Следваща вълна — TBD ([Phase2_4_Release_Closeout.md](Phase2_4_Release_Closeout.md) §8)
```

> **Update (2026-07-23):** Phase 2.4 exited — виж [Phase2_4_Release_Closeout.md](Phase2_4_Release_Closeout.md).  
> **Update (2026-07-23):** Следваща вълна Active — [Phase2_5_Security_Incident_Management.md](Phase2_5_Security_Incident_Management.md).

---

## 9. История на документа

| Версия | Дата       | Промяна                                                                   |
| ------ | ---------- | ------------------------------------------------------------------------- |
| 1.0    | 2026-07-22 | Formal Phase 2.3 exit; A/B/C Must+Should+Could Done; → Phase 2.4 skeleton |
