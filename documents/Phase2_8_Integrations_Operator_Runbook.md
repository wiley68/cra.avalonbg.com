# Phase 2.8 — Integrations operator runbook

**Версия:** 1.0  
**Дата:** 24 юли 2026 г.  
**Родител:** [Phase2_8_Integration_Wave2.md](Phase2_8_Integration_Wave2.md)  
**Свързано:** [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md) (VCS connectors)

Кратък ops справочник за ALM/scanner connectors (Jira Cloud, Azure DevOps, Snyk) и как се пазят секрети, какви scopes са нужни, как се държат rate limits и какъв е threat model-ът.

---

## 1. Secrets storage

| Какво                                  | Къде                                    | Бележки                                                       |
| -------------------------------------- | --------------------------------------- | ------------------------------------------------------------- |
| Jira / Snyk / Azure DevOps credentials | `organization_integrations.credentials` | Laravel `encrypted:array` cast; колоната е `Hidden` на модела |
| GitHub / GitLab tokens (2.1)           | `organization_vcs_connections`          | Същият encrypted pattern                                      |
| Webhook HMAC secrets (2.1 GitHub)      | VCS connection metadata                 | Rotate от Settings → Integrations                             |

**Правила:**

- `APP_KEY` е master key за decrypt — третирай го като secret; ротация без re-encrypt на съществуващи редове чупи connectors.
- Никога raw tokens в Inertia props — UI вижда само non-secret metadata (`base_url`, `email`, org name, sync status).
- Audit log маскира `token`, `api_token`, `api_key`, `credentials`, `secret`, `password`, … (`AuditLogger::SENSITIVE_KEYS`).
- Disconnect / rotate: Settings → Integrations → update token или disconnect; след leak — revoke във външния provider **и** disconnect в CRA.

---

## 2. Credentials & recommended scopes

Connectors са **read-only import** към CRA. Не създават/редактират issues, work items или findings във външните системи.

### Jira Cloud

| Поле        | Описание                                                                           |
| ----------- | ---------------------------------------------------------------------------------- |
| `base_url`  | Напр. `https://your-org.atlassian.net`                                             |
| `email`     | Atlassian account email                                                            |
| `api_token` | [Atlassian API token](https://id.atlassian.com/manage-profile/security/api-tokens) |

**Минимални права:** Browse projects + Browse issues (или еквивалент) за линкнатите project keys. Auth: HTTP Basic (`email` + API token).

**Product link:** Jira project key (напр. `CRA`). Sync вади issues чрез `/rest/api/3/search/jql`.

### Azure DevOps

| Поле           | Описание                                |
| -------------- | --------------------------------------- |
| `organization` | ADO org slug                            |
| `pat`          | Personal Access Token                   |
| `base_url`     | По подразбиране `https://dev.azure.com` |

**Минимални PAT scopes:** **Work Items (Read)** + **Project and Team (Read)** (достатъчни за project lookup + WIQL + work item details).

**Product link:** project name/id. Sync: WIQL → work items → task suggestions.

### Snyk

| Поле        | Описание                              |
| ----------- | ------------------------------------- |
| `api_token` | Snyk personal/service API token       |
| `base_url`  | По подразбиране `https://api.snyk.io` |

**Минимални права:** View organization + View project + View issues/vulnerabilities за линкнатите org/project IDs. Header: `Authorization: token …` (REST API version `2024-10-15`).

**Product link:** Snyk `org_id` + `project_id`. Sync → pending vulnerability suggestions; Accept може да мапне към `product_components` по purl/name.

### VCS (Phase 2.1 — справка)

GitHub PAT / GitHub App / GitLab PAT — виж Phase 2.1. Schedule: `vcs:sync-scheduled` (hourly cron). Webhooks: HMAC verify; URL + rotate secret в Settings.

**Dependabot / Renovate depth (Could 13):** GitHub sync също чете open PRs от `dependabot[bot]` / `renovate[bot]`, линква ги към matching Dependabot alerts по package name и създава suggestions за unmatched Renovate PRs. Accept записва `remediation_pr_url` на vulnerability и redirect-ва към Edit с **Start patch campaign** CTA.

### SARIF / Trivy (Could 14)

| Поле    | Описание                                                                    |
| ------- | --------------------------------------------------------------------------- |
| Auth    | `none` — без API token                                                      |
| Enable  | Settings → Integrations → SARIF / Trivy                                     |
| Product | Upload SARIF 2.1.0 JSON (Trivy `--format sarif`, SonarQube SARIF export, …) |

Import създава pending vulnerability suggestions (същият Accept/Dismiss gate като Snyk). Невалиден JSON/schema → soft-fail + `last_error`, без suggestions. Суровият файл се пази като evidence `vulnerability_scan`; summary → `integration_snapshot`.

### Customer support light link (Could 15)

Опционално поле `external_ticket_url` на **vulnerability** и **incident** (Create/Edit). Ръчна връзка към външен helpdesk (Zendesk, Jira SM, …) — **без** API sync и **без** промяна на deployments. На Edit страницата има „Open support ticket“ линк, когато URL е попълнен.

### AI triage for imported findings (Could 16)

На Product Edit (Snyk / SARIF / VCS vulnerability suggestions) бутон **AI triage summary** генерира чернова (summary + suggested severity) за human review. **Не** Accept-ва suggestion и **не** създава `ProductVulnerability`. Accept остава ръчен клик след преглед.

### Integrations health index (Could 17)

`/integrations/health` — org-level DataTable (server-side) върху product integration links + VCS repositories: provider, product, target, connection status, last sync, health (`ok` / `soft_fail` / `failed` / `never`), last error, pending suggestions. Settings → Integrations има линк „View sync health“.

### Auditor sync health export (Could 18)

От `/integrations/health`: **Export Markdown** / **Export PDF** — snapshot на същите health rows (без credentials/tokens). Audit event `integration_health_exported`. Viewer с products.view също може да експортира.

---

## 3. Rate limits & soft-fail

Providers третират **401 / 403 / 404 / 429** при list/fetch на issues / findings / work items като **soft-fail**:

1. Sync job приключва като **успешен** (не хвърля hard exception към queue retry storm).
2. `product_integration_links.last_sync_summary` съдържа `last_error`, `soft_fail: true`.
3. Не се импортират нови suggestions за този run.
4. Product readiness / dashboard показват soft-fail / failed sync counts за operator follow-up.

**Hard fail** остава за липсващи credentials, грешен provider, или неуспешен connect/verify при Settings.

**Manual Sync now:** `SyncProductIntegrationJob::dispatchSync` — синхронно в HTTP request; не зависи от queue worker.

**Unique job:** `ShouldBeUnique` за `linkId`, `uniqueFor = 300` s — намалява stampede при двойно кликване / overlapping schedule.

---

## 4. Scheduler & queue

| Команда                       | Cron                          | Поведение                                                                                                               |
| ----------------------------- | ----------------------------- | ----------------------------------------------------------------------------------------------------------------------- |
| `integrations:sync-scheduled` | hourly (`routes/console.php`) | Dispatch `SyncProductIntegrationJob` за active connectors с schedule `hourly` / `daily`, когато `isDue(last_synced_at)` |
| `vcs:sync-scheduled`          | hourly                        | Същото за VCS product repos (2.1)                                                                                       |

**Ops P1 за scheduled sync:**

1. Laravel scheduler трябва да върви (`* * * * * php artisan schedule:run`).
2. Queue worker трябва да консумира jobs (`php artisan queue:work` или еквивалент) — schedule ползва `dispatch()`, не `dispatchSync`.
3. Org трябва да е `is_active`; connector status `active`; schedule ≠ `off`.
4. Schedule се задава per connector в Settings → Integrations (`off` / `hourly` / `daily`).

Без queue worker: **Sync now** все още работи; scheduled sync няма да върви.

---

## 5. Threat model (кратко)

| Заплаха                                       | Mitigation                                                                            |
| --------------------------------------------- | ------------------------------------------------------------------------------------- |
| Token leakage в UI / API responses            | Encrypted cast + Hidden; Inertia без secrets                                          |
| Token leakage в audit / logs                  | `SENSITIVE_KEYS` redaction; не логвай request bodies с credentials                    |
| Compromised `APP_KEY` / DB dump               | Физически/backup access controls; rotate APP_KEY + re-connect tokens                  |
| Over-privileged tokens                        | Документирай минимални read scopes; revoke при leave/incident                         |
| Silent auto-create на Tasks / Vulnerabilities | Human **Accept / Dismiss** gate; sync създава само pending suggestions                |
| CRA → ALM writes / two-way sync               | Out of scope за 2.8 — providers само GET/search                                       |
| Abuse of sync (DoS / rate limit)              | Unique job; soft-fail на 429; schedule throttling via `isDue`                         |
| Confused deputy (грешен org product)          | Org-scoped models + RBAC (`integrations.manage` / product policies); viewer read-only |
| Stale / poisoned import                       | Evidence `integration_snapshot` + checksum; audit refs; operator може да dismiss      |

**Trust boundary:** външният ALM/scanner е untrusted input. CRA нормализира към suggestion records; човек решава Accept.

---

## 6. Operator checklist

- [ ] `APP_KEY` и DB backups са защитени; credentials не са в git / chat logs.
- [ ] Connectors ползват **минимални read** scopes; токените имат owner/expiry политика.
- [ ] След connect — Verify/link project; един успешен Sync now.
- [ ] При schedule ≠ off: `schedule:run` + `queue:work` са активни.
- [ ] След 401/403 soft-fail: провери scopes / project id; ротирай token ако е нужно.
- [ ] След suspected leak: revoke във външния provider → Update/Disconnect в Settings.
- [ ] Accept само след review; не третирай suggestion count като „imported vulns“.

---

## 7. Къде в UI / код

| Повърхност                        | Път                                                                     |
| --------------------------------- | ----------------------------------------------------------------------- |
| Org connectors                    | Settings → Integrations                                                 |
| Product link / Sync / suggestions | Product → Edit → Integrations                                           |
| Readiness / dashboard gaps        | Product readiness + org dashboard counts                                |
| Providers                         | `app/Services/Integrations/{JiraCloud,AzureDevOps,SnykApi}Provider.php` |
| Sync services                     | `AlmSyncService`, `ScannerSyncService`                                  |
| Job                               | `SyncProductIntegrationJob`                                             |
| Schedule command                  | `integrations:sync-scheduled`                                           |
