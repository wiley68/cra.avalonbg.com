# Phase 2.4 Release Closeout

**Версия:** 1.3  
**Дата:** 23 юли 2026 г.  
**Статус:** Closed — Phase 2.4 exited (2026-07-23)  
**Родителски документи:**

- [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) (§7 slices, §9 AC)
- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§5.17 User Security Instructions, §14)
- [Phase2_3_Release_Closeout.md](Phase2_3_Release_Closeout.md) (Closed — Phase 2.3 exited)
- [MVP_Release_Closeout.md](MVP_Release_Closeout.md) (Closed — MVP 0.1 exited)

> Цел: затваряне и валидация на Phase 2.4 (User Security Instructions) преди планиране на следваща вълна. Не въвежда нови големи модули извън §7 на Phase 2.4 плана.

---

## 1. Контекст

Phase 2.4 доставя **product-scoped User Security Instructions** (§5.17):

1. структуриран документ + фиксирани section keys;
2. lifecycle `draft` → `under_review` → `published` → `retired`;
3. EN/BG templates, export (HTML/PDF/README/release), evidence + readiness hooks;
4. Could: customer guide export, AI section drafts, supersede diff, review tasks, locale pairs.

Този документ покрива:

1. acceptance criteria от Phase 2.4 §9 — статус;
2. имплементационен checklist (Must / Should / Could);
3. closeout backlog (отложено извън 2.4);
4. exit criteria за „Phase 2.4 готов“;
5. pointer към следващо планиране (Phase 2 §14 е изчерпан).

---

## 2. Acceptance criteria (Phase 2.4 §9) — статус

| #   | Критерий                                                             | Статус | Бележки                                             |
| --- | -------------------------------------------------------------------- | ------ | --------------------------------------------------- |
| 1   | Owner създава instructions и попълва задължителните секции (или N/A) | Done   | CRUD + section editor + publish validation          |
| 2   | Owner publish-ва; Viewer вижда/export-ва published, но не edit-ва    | Done   | RBAC + export permissions                           |
| 3   | HTML и PDF export са налични и одитируеми                            | Done   | + README / release ZIP (Should)                     |
| 4   | Readiness показва gap при липса на published instructions            | Done   | `security_instructions_missing`                     |
| 5   | Промените са в audit log                                             | Done   | create/update/delete/submit/publish/retire/export/… |
| 6   | AI (Could) не publish-ва и не overwrite-ва без human confirmation    | Done   | suggest → Apply → Save; no auto-write DB/publish    |

**Всички AC са изпълнени** (2026-07-23). Оперативна smoke проверка в реална org остава препоръчителна (не блокира exit).

---

## 3. Имплементационни slices (§7) — статус

### Must

| #   | Slice                                                       | Статус |
| --- | ----------------------------------------------------------- | ------ |
| 1   | Migrations + models + enums                                 | Done   |
| 2   | CRUD + section editor (product-scoped)                      | Done   |
| 3   | Lifecycle draft → publish (submit / publish / retire)       | Done   |
| 4   | Starter templates EN/BG per section key                     | Done   |
| 5   | Export HTML + PDF                                           | Done   |
| 6   | i18n EN/BG + feature tests (CRUD + viewer forbidden manage) | Done   |

### Should

| #   | Slice                                              | Статус |
| --- | -------------------------------------------------- | ------ |
| 7   | README / release-package markdown export           | Done   |
| 8   | Readiness gap `security_instructions_missing`      | Done   |
| 9   | Publish published instructions → Evidence          | Done   |
| 10  | Version-pinned instructions (`product_version_id`) | Done   |
| 11  | Markdown preview (reuse 2.3A helpers)              | Done   |

### Could

| #   | Slice                                                 | Статус |
| --- | ----------------------------------------------------- | ------ |
| 12  | Customer-specific installation guide variant          | Done   |
| 13  | AI draft per section (human review; reuse AiProvider) | Done   |
| 14  | Diff between superseding versions                     | Done   |
| 15  | Task on submit-for-review                             | Done   |
| 16  | Multi-locale document pairs (en/bg linked)            | Done   |

**Всички slices Done** (2026-07-23). План: [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) **v1.0** (Closed).

---

## 4. Доставени модули (референция)

| Повърхност   | Nav / scope                                         | Ключови capabilities                                      |
| ------------ | --------------------------------------------------- | --------------------------------------------------------- |
| USI Index    | `/products/{id}/security-instructions`              | Server-side DataTable, version filter, translation column |
| USI Edit     | same + `/{instruction}/edit`                        | Sections, preview, diff, AI draft, lifecycle, pair link   |
| Export       | `…/export/{html\|pdf\|readme\|release}`             | Audit + customer-guide query params                       |
| Internal API | `/internal-api/products/{id}/security-instructions` | Paginated list for DataTable                              |
| Readiness    | Product readiness                                   | Gap `security_instructions_missing`                       |
| Tasks        | Product tasks                                       | Subject type `user_security_instruction`                  |

### Данни

- `user_security_instructions` (`supersedes_id`, `paired_instruction_id`, version pin, evidence FK)
- `user_security_instruction_sections` (fixed §5.17 keys)

### Конфигурация / reuse

- AI: `CRA_AI_ENABLED` + stub/OpenAI/Anthropic (`usi_section_draft` mode)
- Markdown: `PolicyBodyField` / `TextDiffViewer`
- Evidence + readiness patterns от Phase 2.3

---

## 5. Closeout backlog

Приоритет: **P0** = блокира Phase 2.4 exit; **P1** = polish; **P2** = извън 2.4.

### P0 — валидация

1. **Must/Should/Could slices Done** — **Done** (2026-07-23)
2. **§9 AC покрити в код + feature tests** — **Done** (2026-07-23)
3. **Няма отворени P0 дефекти в USI flows** — **Done** (2026-07-23)

### P1 — polish (не блокира exit)

4. **Production queue worker** — за AI draft при live provider (ако се ползва queue path).
5. **Live LLM keys** — optional; stub остава default за dev/CI.
6. **Parent-plan status sync** — актуализиран в този closeout / Nachalen план.
7. **Readiness warn** „published EN but missing BG pair“ — optional follow-up.
8. **N/A rationale field** — publish позволява N/A чрез `is_applicable`; отделно rationale поле не е било в §7.

### P2 — изрично извън Phase 2.4 (§3 out-of-scope)

9. Автоматично генериране без human review / авто-publish към клиенти
10. Пълен CMS / collaborative real-time editing
11. Customer self-service portal за инструкции
12. Notified-body submission на instructions
13. Billing / SSO за distribution
14. Пълен **SDL workspace** (§5.14) — отделна вълна след 2.4

---

## 6. Exit criteria — „Phase 2.4 готов“

Phase 2.4 се счита за готов, когато:

1. Всички §9 acceptance criteria са **Done**. — **Done**
2. Всички Must slices са **Done**. — **Done**
3. Should slices по плана са **Done**. — **Done**
4. Could slices по плана са **Done**. — **Done**
5. Няма отворени P0 дефекти. — **Done**
6. Feature тестовете за USI модула минават. — **Done** (2026-07-23)

**Phase 2.4 е официално exited** (2026-07-23).

След exit:

- Phase 2.4 план: [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) → **Closed**;
- **§14 Втора фаза** в Nachalen плана е изчерпана (2.1–2.4 Closed);
- следваща вълна се избира от deferred domain модули / polish (виж §8).

---

## 7. Тестове (Phase 2.4 scope — референция)

| Област       | Файлове                                                               |
| ------------ | --------------------------------------------------------------------- |
| Model / CRUD | `UserSecurityInstructionModelTest`, `UserSecurityInstructionCrudTest` |
| RBAC         | `UserSecurityInstructionRbacTest`                                     |
| Export       | `UserSecurityInstructionExportTest`                                   |
| Readiness    | `UserSecurityInstructionsReadinessTest`                               |
| Evidence     | `UserSecurityInstructionEvidenceTest`                                 |
| Version pin  | `UserSecurityInstructionVersionPinTest`                               |
| AI draft     | `UserSecurityInstructionAiDraftTest`                                  |
| Diff         | `UserSecurityInstructionDiffTest`                                     |
| Review task  | `UserSecurityInstructionReviewTaskTest`                               |
| Locale pairs | `UserSecurityInstructionLocalePairTest`                               |

---

## 8. Следващо планиране (след Phase 2)

**[Phase 2.4 — User Security Instructions](Phase2_4_User_Security_Instructions.md)** — **Closed** (2026-07-23).

С **§14 Втора фаза** приключена, следващите кандидати от [Nachalen плана](CRA_Compliance_Workspace_Nachalen_Plan.md):

| Приоритет (предложение) | Кандидат                           | Източник в плана                                                                   |
| ----------------------- | ---------------------------------- | ---------------------------------------------------------------------------------- |
| A                       | **Security Incident Management**   | §5.10 — **Closed** [Phase2_5_Release_Closeout.md](Phase2_5_Release_Closeout.md)    |
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

| Версия | Дата       | Промяна                                                                    |
| ------ | ---------- | -------------------------------------------------------------------------- |
| 1.3    | 2026-07-23 | Pointer sync — Phase 2.6 Closed; Phase 2.7 Technical Docs Active           |
| 1.2    | 2026-07-23 | Pointer → Phase 2.5 Closed; Phase 2.6 SDL Active                           |
| 1.1    | 2026-07-23 | Pointer → Phase 2.5 Security Incident Management (кандидат A)              |
| 1.0    | 2026-07-23 | Formal Phase 2.4 exit; Must+Should+Could Done; §14 complete; next-wave TBD |
