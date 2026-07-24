# Phase 2_E — Live LLM enablement

**Версия:** 1.2  
**Дата:** 24 юли 2026 г.  
**Родител:** [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md) (Must 3; Could 15 RAG schedule)  
**Свързано:** [Phase2_E_Ops_Baseline.md](Phase2_E_Ops_Baseline.md) (queue worker), AI surfaces в Phase 2.3 / 2.8

> Цел: включване на **live** OpenAI / Anthropic за triage MVP, със **stub** като default за CI. Без auto-accept / auto-close.

---

## 1. Принципи

| Правило             | Детайл                                                                                     |
| ------------------- | ------------------------------------------------------------------------------------------ |
| CI / tests          | `CRA_AI_PROVIDER=stub` (записано в `phpunit.xml`) — без външни API calls                   |
| Staging / prod live | `CRA_AI_PROVIDER=openai` **или** `anthropic` + валиден API key                             |
| Human review        | Imported-finding triage и vulnerability triage са **чернови**; Accept / apply остава ръчен |
| Secrets             | Ключовете само в `.env`; никога в git, audit details или UI props                          |
| Queue               | При `CRA_AI_QUEUE_ENABLED=true` част от AI jobs ползват `queue:work` (виж ops baseline)    |

---

## 2. Env (staging / prod)

Минимум за OpenAI:

```env
CRA_AI_ENABLED=true
CRA_AI_PROVIDER=openai
CRA_AI_OPENAI_API_KEY=sk-...
# optional:
# CRA_AI_OPENAI_MODEL=gpt-4o-mini
# CRA_AI_OPENAI_TIMEOUT=60
```

Минимум за Anthropic:

```env
CRA_AI_ENABLED=true
CRA_AI_PROVIDER=anthropic
CRA_AI_ANTHROPIC_API_KEY=sk-ant-...
# optional:
# CRA_AI_ANTHROPIC_MODEL=claude-sonnet-4-20250514
```

След промяна: `php artisan config:clear` (ако config е cached).

Проверка **без** външен call:

```bash
php artisan ops:ai-check
```

Очаквай exit 0; при live provider без key → exit 1.

---

## 3. CI / local tests (stub)

| Файл           | Поведение                                                                       |
| -------------- | ------------------------------------------------------------------------------- |
| `phpunit.xml`  | `CRA_AI_PROVIDER=stub`, `CRA_AI_EMBEDDING_PROVIDER=stub`                        |
| Feature tests  | `ImportSuggestionAiTriageTest`, `AiVulnerabilityTriageTest` и др. — stub drafts |
| `.env.example` | Default `CRA_AI_PROVIDER=stub`                                                  |

Не задавай live keys в CI secrets за default suite. Opt-in:

```bash
CRA_AI_LIVE_TEST=true CRA_AI_OPENAI_API_KEY=... php artisan test --group=live-ai
```

---

## 4. Must 3 triage surfaces (smoke checklist)

### A) Imported finding triage (Phase 2.8 Could 16)

1. [ ] Org с AI enabled + live provider (или stub за dry-run).
2. [ ] Product с pending **vulnerability** import suggestion (Snyk / SARIF / Dependabot-style VCS).
3. [ ] Owner: Product → **Edit** → Integrations / suggestions.
4. [ ] Кликни **AI triage summary** на pending finding.
5. [ ] Очаквай draft: summary + suggested severity + disclaimer; suggestion остава **pending**.
6. [ ] Audit: `ai_imported_finding_triage_suggested` (без API key в details).
7. [ ] Accept все още е отделен ръчен клик (нищо не се auto-accept-ва).

Route: `POST .../import-suggestions/{id}/ai-triage` или `.../vcs-suggestions/{id}/ai-triage`.

### B) Vulnerability register triage (Phase 2.3+)

1. [ ] Съществуваща `ProductVulnerability` на продукта.
2. [ ] Owner: Product → Vulnerabilities → **Edit**.
3. [ ] Генерирай **AI triage** suggestions (severity / versions / remediation draft).
4. [ ] Очаквай suggestions panel; vulnerability fields **не** се мутират автоматично.
5. [ ] Audit: `ai_vulnerability_triage_suggested`.

Route: `POST .../products/{product}/assistant/triage`.

### Smoke резултат

| Provider                     | Очакван резултат                                         |
| ---------------------------- | -------------------------------------------------------- |
| `stub`                       | Canned/parsed draft; бърз; OK за UI walkthrough без cost |
| `openai` / `anthropic` + key | Реален draft; моделът се вижда в response/audit metadata |
| live без key                 | Validation / toast `provider_misconfigured`              |

---

## 5. Queue note

- `CRA_AI_QUEUE_ENABLED=true` (default): **vulnerability register triage** минава през `AiQueuedAnalysisService` → нужен `queue:work` ([Phase2_E_Ops_Baseline.md](Phase2_E_Ops_Baseline.md)).
- **Imported-finding triage** (`suggestAiTriage`) е **синхронен** в HTTP request — не изисква worker.
- Chat messages остават sync независимо от queue flag.
- За smoke на vulnerability triage в staging: пусни worker **или** временно `CRA_AI_QUEUE_ENABLED=false` (само за debug).

---

## 5b. RAG / embedding reindex (Could 15)

Локалният RAG index (`ai_embedding_chunks`) се обновява с:

```bash
php artisan ai:index-embeddings              # всички продукти (queue при CRA_AI_QUEUE_ENABLED)
php artisan ai:index-embeddings 12           # един product id
php artisan ai:index-embeddings --organization=3
php artisan ai:index-embeddings --sync       # inline, без queue
```

**Scheduler (default):** `ai:index-embeddings` daily в `CRA_AI_RAG_REINDEX_AT` (default `02:30`), ако `CRA_AI_RAG_ENABLED=true` и `CRA_AI_RAG_REINDEX_SCHEDULE` ≠ `off`.

| Env                           | Default | Бележка                                                             |
| ----------------------------- | ------- | ------------------------------------------------------------------- |
| `CRA_AI_RAG_ENABLED`          | `true`  | `false` → командата no-op; schedule `when()` skip                   |
| `CRA_AI_RAG_REINDEX_SCHEDULE` | `daily` | `off` \| `daily` \| `hourly`                                        |
| `CRA_AI_RAG_REINDEX_AT`       | `02:30` | само за `daily` (app timezone)                                      |
| `CRA_AI_EMBEDDING_PROVIDER`   | `stub`  | CI/stub; live: `openai` + key при нужда                             |
| `CRA_AI_QUEUE_ENABLED`        | `true`  | schedule без `--sync` → `IndexAiEmbeddingsJob` → нужен `queue:work` |

Проверка:

```bash
php artisan schedule:list | grep index-embeddings
php artisan ops:ai-check
```

Без `schedule:run` + worker scheduled reindex **не** тече; manual `ai:index-embeddings --sync` остава OK.

---

## 6. Troubleshooting

| Симптом                  | Проверка                                                               |
| ------------------------ | ---------------------------------------------------------------------- |
| „AI disabled“            | `CRA_AI_ENABLED=true` + `ops:ai-check`                                 |
| `provider_misconfigured` | Key за избрания provider; `config:clear`                               |
| `provider_timeout`       | Мрежа/бавен vendor; retry; или временно stub                           |
| `provider_failed`        | HTTP 4xx/5xx, празен отговор, model name, billing при vendor           |
| Stub отговори в staging  | `.env` още е `CRA_AI_PROVIDER=stub`                                    |
| CI вика OpenAI           | `phpunit.xml` трябва да форсира stub; не override-вай в CI env         |
| Бавен / висящ triage     | Timeout env; queue worker ако job е queued                             |
| Queued fail „invalid“    | Should 11 — `error_message` вече е преведен `provider_*` text          |
| RAG passages празни      | `ai:index-embeddings` (или daily schedule); `CRA_AI_RAG_ENABLED`       |
| Scheduled reindex skip   | `CRA_AI_RAG_REINDEX_SCHEDULE=off` или RAG disabled; виж `ops:ai-check` |

---

## 7. Out of Must 3

- Нови AI providers / fine-tuning
- Auto-accept на suggestions
- Live LLM за USI / incident / tech-doc drafts (работят с текущия provider as-is; не са Must 3 smoke)
- ~~Consistent live-error UX polish → Should 11~~ **Done** — `AiUserFacingError`, `assistant.provider_timeout`

---

## 8. История

| Версия | Дата       | Промяна                                                          |
| ------ | ---------- | ---------------------------------------------------------------- |
| 1.2    | 2026-07-24 | Could 15 — scheduled `ai:index-embeddings` + RAG ops note §5b    |
| 1.1    | 2026-07-24 | Link Should 11 — timeout vs failed UX + queued translated errors |
| 1.0    | 2026-07-24 | Must 3 — enablement guide + `ops:ai-check` + smoke checklist     |
