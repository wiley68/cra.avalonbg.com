# Phase 2_E — Ops baseline (scheduler + queue)

**Версия:** 1.0  
**Дата:** 24 юли 2026 г.  
**Родител:** [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md) (Must 1)  
**Свързано:** [Phase2_8_Integrations_Operator_Runbook.md](Phase2_8_Integrations_Operator_Runbook.md) §4

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

| Променлива         | Препоръка (staging/prod)         | Бележка                                                                                |
| ------------------ | -------------------------------- | -------------------------------------------------------------------------------------- |
| `QUEUE_CONNECTION` | `database` (default) или `redis` | **Не** `sync` — иначе `dispatch()` се изпълнява inline в `schedule:run` и блокира cron |
| `DB_*`             | Работеща DB                      | Нужна за `jobs` / `failed_jobs` при `database` driver                                  |
| `APP_ENV`          | `staging` / `production`         | —                                                                                      |
| `APP_KEY`          | Set                              | Encrypt за integration/VCS credentials                                                 |

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

Алтернатива: systemd timer, който пуска `schedule:run` всяка минута (Could 13 sample units — по-късно).

---

## 4. Queue worker

Минимум (foreground / screen за smoke):

```bash
cd /var/www/cra.avalonbg.com
php artisan queue:work --sleep=1 --tries=3 --timeout=120
```

Production: supervisor/systemd с `queue:work` (или `queue:listen`) + `queue:restart` след deploy. Sample unit → Could 13.

Полезни команди:

```bash
php artisan queue:failed
php artisan queue:retry all
php artisan queue:monitor default --max=100
```

---

## 5. Staging verification checklist (Must 1)

Изпълни в staging (или local с `QUEUE_CONNECTION=database` + worker):

1. [ ] `php artisan migrate` — `jobs` / `failed_jobs` съществуват.
2. [ ] `QUEUE_CONNECTION` ≠ `sync` (`.env`).
3. [ ] `php artisan ops:baseline-check` → exit 0.
4. [ ] `php artisan schedule:list` показва `vcs:sync-scheduled` и `integrations:sync-scheduled` като `0 * * * *`.
5. [ ] Cron (или ръчно) пуска `php artisan schedule:run` без error.
6. [ ] Settings → Integrations: connector **active**, schedule `hourly` или `daily`; product link съществува; org `is_active`.
7. [ ] (Опционално) Нулирай `last_synced_at` на link/repo или изчакай `isDue`, после:
    - `php artisan integrations:sync-scheduled` / `vcs:sync-scheduled` → „Dispatched N …“
    - ред(ове) в `jobs` **или** worker лог показва обработка.
8. [ ] Worker консумира job; `/integrations/health` или product link summary се обновява (без soft-fail при валиден token).
9. [ ] С **спрян** worker: **Sync now** в UI все още успява (`dispatchSync`).
10. [ ] С спрян worker + schedule: jobs се трупат в `jobs` (доказателство, че schedule ≠ sync).

**Verified (automated):** 2026-07-24 — `schedule:list` съдържа и двете hourly команди; artisan signatures налични; feature test `OpsBaselineScheduleTest` (4/4). Staging/prod: попълни checklist по-горе + `php artisan ops:baseline-check` на host с работеща DB.

---

## 6. Кога sync се skip-ва

Командите **не** диспатчват когато:

- org `is_active = false`;
- connector status ≠ `active`;
- `sync_schedule = off`;
- `isDue(last_synced_at)` е false (hourly &lt; 1h / daily &lt; 24h от последния sync на link/repo).

---

## 7. Troubleshooting

| Симптом                        | Проверка                                                                     |
| ------------------------------ | ---------------------------------------------------------------------------- |
| Schedule „върви“, но няма sync | Има ли `queue:work`? `jobs` расте ли?                                        |
| `QUEUE_CONNECTION=sync`        | Смени на `database`; schedule ще блокира иначе                               |
| Dispatched 0; skipped N        | Schedule `off`, не due, или няма links                                       |
| Soft-fail в summary            | Token/scopes — [Phase2_8 runbook](Phase2_8_Integrations_Operator_Runbook.md) |
| Failed jobs                    | `queue:failed` + retry; виж exception (без secrets в logs)                   |

---

## 8. История

| Версия | Дата       | Промяна                                          |
| ------ | ---------- | ------------------------------------------------ |
| 1.0    | 2026-07-24 | Must 1 — ops baseline doc + `ops:baseline-check` |
