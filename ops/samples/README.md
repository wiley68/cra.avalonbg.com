# Ops process-manager samples (Could 13)

Sample configs for keeping **both** production processes alive:

| Process                                        | Role                                                                   |
| ---------------------------------------------- | ---------------------------------------------------------------------- |
| `queue:work`                                   | Consumes `dispatch()` jobs (scheduled VCS/integration sync, queued AI) |
| `schedule:run` (or `schedule:work` in Compose) | Fires Laravel scheduler every minute                                   |

Parent docs: [Phase2_E_Ops_Baseline.md](../../documents/Phase2_E_Ops_Baseline.md), [Phase2_E_Cross_Phase_Polish.md](../../documents/Phase2_E_Cross_Phase_Polish.md).

> **Samples only** — adapt `User`, paths, `php` binary, and log dirs before enabling on a host. Do **not** commit secrets.

## Choose one stack

| Stack              | Files                                                                  | Notes                                                                               |
| ------------------ | ---------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| **Supervisor**     | [`supervisor/cra-queue-worker.conf`](supervisor/cra-queue-worker.conf) | Queue daemon; keep cron for `schedule:run` (or use systemd timer)                   |
| **systemd**        | [`systemd/`](systemd/)                                                 | `cra-queue-worker.service` + `cra-scheduler.service` / `.timer` (no crontab needed) |
| **Docker Compose** | [`docker-compose.workers.yml`](docker-compose.workers.yml)             | Sidecar workers against an existing app image; uses `schedule:work`                 |

Manual **Sync now** stays `dispatchSync` and does **not** require these processes.

## After deploy

```bash
php artisan queue:restart
php artisan ops:baseline-check
```

Restart the worker process manager after code deploys so workers pick up new code (Laravel `queue:restart` alone is enough when workers already poll; still restart units after PHP/extension changes).

## Horizon

**Not used in 2_E.** Default queue is `database`; Horizon needs Redis. See [Phase2_E_Ops_Baseline.md](../../documents/Phase2_E_Ops_Baseline.md) §4b (Could 14 Skipped).
