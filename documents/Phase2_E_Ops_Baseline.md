# Phase 2_E — Ops baseline (scheduler + queue)

**Версия:** 1.3  
**Дата:** 24 юли 2026 г.  
**Родител:** [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md) (Must 1–2; Should 9 UI hint; Could 13 samples)  
**Свързано:** [Phase2_8_Integrations_Operator_Runbook.md](Phase2_8_Integrations_Operator_Runbook.md) §4 · [`ops/samples/`](../ops/samples/)

> Цел: production/staging path за **scheduled** VCS + integration sync. Manual **Sync now** не зависи от този baseline.

---

## 1. Какво трябва да върви (два процеса)

| Процес       | Команда                                            | Роля                                                                 |
| ------------ | -------------------------------------------------- | -------------------------------------------------------------------- |
| Scheduler    | `php artisan schedule:run` (всяка минута via cron) | Стартира hourly `vcs:sync-scheduled` и `integrations:sync-scheduled` |
| Queue worker | `php artisan queue:work` (daemon / supervisor)     | Консумира jobs, диспатнати с `dispatch()`                            |

Без **и двата**: scheduled sync **не** тече. **Sync now** остава OK (`dispatchSync`).

```text
cron (* * * * *) → schedule:run
                         ├─ (hourly) vcs:sync-scheduled          → SyncProductRepositoryJob::dispatch
                         └─ (hourly) integrations:sync-scheduled → SyncProductIntegrationJob::dispatch
                                                                              ↓
                                                                    queue:work (jobs table)
```

Регистрация в код: `routes/console.php`.

---

## 2. Env notes

| Променлива             | Препоръка (staging/prod)         | Бележка                                                                                |
| ---------------------- | -------------------------------- | -------------------------------------------------------------------------------------- |
| `QUEUE_CONNECTION`     | `database` (default) или `redis` | **Не** `sync` — иначе `dispatch()` се изпълнява inline в `schedule:run` и блокира cron |
| `DB_QUEUE_RETRY_AFTER` | **≥ 150** (default 150)          | Трябва да е **>** sync job `$timeout` (90), иначе Laravel може да пусне дубликат job   |
| `DB_*`                 | Работеща DB                      | Нужна за `jobs` / `failed_jobs` при `database` driver                                  |
| `APP_ENV`              | `staging` / `production`         | —                                                                                      |
| `APP_KEY`              | Set                              | Encrypt за integration/VCS credentials                                                 |

Таблици (миграция `0001_01_01_000002_create_jobs_table`): `jobs`, `job_batches`, `failed_jobs`.

Проверка:

```bash
php artisan ops:baseline-check
php artisan schedule:list
# Очаквай hourly:
#   vcs:sync-scheduled
#   integrations:sync-scheduled
```

---

## 3. Cron (host)

Един ред в crontab на app user (път към `php` и проекта — адаптирай):

```cron
* * * * * cd /var/www/cra.avalonbg.com && php artisan schedule:run >> /dev/null 2>&1
```

Алтернатива: systemd timer, който пуска `schedule:run` всяка минута — sample: [`ops/samples/systemd/`](../ops/samples/systemd/).

---

## 4. Queue worker

Минимум (foreground / screen за smoke):

```bash
cd /var/www/cra.avalonbg.com
php artisan queue:work --sleep=1 --tries=3 --timeout=90
```

Job-level defaults (Must 2): `$tries = 3`, `$backoff = [15, 60, 120]`, `$timeout = 90` на `SyncProductIntegrationJob` / `SyncProductRepositoryJob`. Soft-fail HTTP (401/403/429) **не** хвърля — job успява с `last_error`. Hard exception → retries → `failed_jobs` + `last_sync_summary.queue_failed` (видимо в `/integrations/health`).

Production: supervisor/systemd с `queue:work` (или `queue:listen`) + `queue:restart` след deploy.

**Sample units (Could 13):** [`ops/samples/`](../ops/samples/) — Supervisor, systemd (worker + scheduler timer), Docker Compose workers. README с install steps.

Полезни команди:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:monitor default --max=100
```

---

## 4b. Failed job visibility (Must 2)

| Къде                                            | Какво                                                                          |
| ----------------------------------------------- | ------------------------------------------------------------------------------ |
| `failed_jobs` table / `queue:failed`            | Laravel payload след изчерпани tries                                           |
| `last_sync_summary.queue_failed` + `last_error` | Product link / VCS repo — health = `failed`                                    |
| `ops:baseline-check`                            | Печата `failed_jobs count` + retry_after vs timeout                            |
| Manual Sync now                                 | `dispatchSync` — **не** минава през queue; при hard throw също вика `failed()` |

---

## 5. Staging verification checklist (Must 1–2)

Изпълни в staging (или local с `QUEUE_CONNECTION=database` + worker):

1. [ ] `php artisan migrate` — `jobs` / `failed_jobs` съществуват.
2. [ ] `QUEUE_CONNECTION` ≠ `sync` (`.env`); `DB_QUEUE_RETRY_AFTER` ≥ 150 (или default).
3. [ ] `php artisan ops:baseline-check` → exit 0 (incl. retry_after + failed_jobs count).
4. [ ] `php artisan schedule:list` показва `vcs:sync-scheduled` и `integrations:sync-scheduled` като `0 * * * *`.
5. [ ] Cron (или ръчно) пуска `php artisan schedule:run` без error.
6. [ ] Settings → Integrations: connector **active**, schedule `hourly` или `daily`; product link съществува; org `is_active`.
7. [ ] (Опционално) Нулирай `last_synced_at` на link/repo или изчакай `isDue`, после:
    - `php artisan integrations:sync-scheduled` / `vcs:sync-scheduled` → „Dispatched N …“
    - ред(ове) в `jobs` **или** worker лог показва обработка.
8. [ ] Worker консумира job; `/integrations/health` или product link summary се обновява (без soft-fail при валиден token).
9. [ ] С **спрян** worker: **Sync now** в UI все още успява (`dispatchSync`); **няма** нов ред в `jobs`.
10. [ ] С спрян worker + schedule: jobs се трупат в `jobs` (доказателство, че schedule ≠ sync).
11. [ ] След hard failure: `queue:failed` и/или health `failed` с `queue_failed` в summary.
12. [ ] UI hint: при `sync_schedule` ≠ off + `QUEUE_CONNECTION=sync` (или stale `jobs` > 30m) — banner на `/integrations/health` и Settings → Integrations.

**Verified (automated):** 2026-07-24 — `OpsBaselineScheduleTest` + `QueueHardeningTest` + `OpsQueueHealthHintTest` зелени. Staging/prod: попълни checklist + `ops:baseline-check` на host с работеща DB.

---

## 6. Кога sync се skip-ва

Командите **не** диспатчват когато:

- org `is_active = false`;
- connector status ≠ `active`;
- `sync_schedule = off`;
- `isDue(last_synced_at)` е false (hourly &lt; 1h / daily &lt; 24h от последния sync на link/repo).

---

## 7. Troubleshooting

| Симптом                          | Проверка                                                                     |
| -------------------------------- | ---------------------------------------------------------------------------- |
| Schedule „върви“, но няма sync   | Има ли `queue:work`? `jobs` расте ли?                                        |
| `QUEUE_CONNECTION=sync`          | Смени на `database`; schedule ще блокира иначе                               |
| Dispatched 0; skipped N          | Schedule `off`, не due, или няма links                                       |
| Soft-fail в summary              | Token/scopes — [Phase2_8 runbook](Phase2_8_Integrations_Operator_Runbook.md) |
| Failed jobs / health failed      | `queue:failed` + retry; виж `last_sync_summary.queue_failed` (без secrets)   |
| Дублирани jobs / release mid-run | `DB_QUEUE_RETRY_AFTER` трябва да е > `$timeout` (90)                         |
| Banner на Health / Settings      | `OpsQueueHealthHintService` — sync queue или stale jobs; Sync now OK         |

---

## 8. История

| Версия | Дата       | Промяна                                                              |
| ------ | ---------- | -------------------------------------------------------------------- |
| 1.3    | 2026-07-24 | Could 13 — Supervisor/systemd/Compose sample units under ops/samples |
| 1.2    | 2026-07-24 | Should 9 — UI ops hint on Health + Settings; checklist item 12       |
| 1.1    | 2026-07-24 | Must 2 — retries, failed visibility, retry_after, Sync now checklist |
| 1.0    | 2026-07-24 | Must 1 — ops baseline doc + `ops:baseline-check`                     |
