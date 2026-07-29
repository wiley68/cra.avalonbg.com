# Next Rewrite — Stack & Conventions

**Версия:** 1.1  
**Свързани:** [01_MASTER_PLAN.md](01_MASTER_PLAN.md), [06_API_AND_DATATABLE.md](06_API_AND_DATATABLE.md)

---

## 1. Стек

| Слой        | Избор                                         | Бележки                      |
| ----------- | --------------------------------------------- | ---------------------------- |
| Framework   | Next.js App Router (TypeScript)               | `src/app`                    |
| DB          | Neon Postgres                                 | pooled + direct URLs         |
| ORM         | Prisma                                        | migrate per wave             |
| Auth        | Better Auth + Prisma adapter                  | session cookies              |
| UI          | shadcn/ui + Tailwind                          | Switch за booleans           |
| Tables      | TanStack Table + server fetch                 | Laravel `useApiTable` parity |
| Jobs        | Inngest                                       | cron + event jobs            |
| Email       | Nodemailer → Mailpit (local); Resend (deploy) |                              |
| Files       | `storage/uploads` local; Blob later           | private + authz              |
| i18n        | next-intl **или** JSON loader                 | EN + BG                      |
| Validation  | Zod                                           | forms + API                  |
| Package mgr | pnpm                                          | document if npm              |

---

## 2. Директории (препоръчани)

```text
src/
  app/
    (auth)/login|register/...
    (app)/dashboard|users|products|...
    (admin)/admin/...
    api/...                 # Route Handlers (DataTable + webhooks)
    api/inngest/route.ts
    api/dev/stripe-replay/  # local fixtures only
  components/
    ui/                     # shadcn
    data-table/
    layout/
  lib/
    prisma.ts
    auth.ts                 # Better Auth instance
    auth-client.ts
    crypto.ts               # encrypt secrets (SSO/tokens)
    datatable.ts            # parseQuery + paginate helpers
    rbac.ts
    i18n/
  server/
    services/               # domain services (mirror Laravel Services)
    jobs/                   # Inngest functions
  types/
prisma/
  schema.prisma
  migrations/
  seed.ts
documents/
  Next_Rewrite/             # този пакет
uploads/                    # gitignored
```

---

## 3. Naming

| Laravel                     | Next / Prisma                                                                         |
| --------------------------- | ------------------------------------------------------------------------------------- |
| `organization_id`           | `organizationId` (Prisma) / DB column `organization_id` via `@map`                    |
| `snake_case` columns        | keep DB snake via `@@map` / `@map` for easier Laravel diff                            |
| `OrganizationSsoConnection` | `OrganizationSsoConnection` model name                                                |
| Enum string values          | Prisma `enum` **или** String + Zod (предпочитай String + shared Zod за по-лесен порт) |
| Policies                    | `src/lib/rbac.ts` + per-resource `canX(user, org, resource)`                          |
| Services                    | `src/server/services/*`                                                               |

**Конвенция:** DB колони = snake_case (`@@map`), Prisma client = camelCase.

---

## 4. Multi-tenant

1. Почти всеки business ред има `organizationId`.
2. След auth: resolve `currentOrganization` от membership / session.
3. Platform admin (`isPlatformAdmin` / permission `platform.admin`) може cross-tenant само в `(admin)` routes.
4. Guest auditor links: token-scoped, без org session (като Laravel guest routes).

Никога не връщай редове от чужда организация в tenant API.

---

## 5. Env (`.env.example`)

```bash
# App
NEXT_PUBLIC_APP_URL=http://localhost:3000
APP_ENCRYPTION_KEY=                 # 32+ bytes hex/base64 — SSO/token encrypt
ENABLE_LIVE_WEBHOOKS=false

# Local only (do not commit real values — use .env.local)
# LARAVEL_REFERENCE_ROOT=C:\path\to\cra-laravel

# Database (Neon)
DATABASE_URL=                       # pooled
DIRECT_URL=                         # migrations

# Better Auth
BETTER_AUTH_SECRET=
BETTER_AUTH_URL=http://localhost:3000

# Mail (local Mailpit)
SMTP_HOST=127.0.0.1
SMTP_PORT=1025
SMTP_FROM=noreply@cra.local

# Stripe (optional until Wave 8)
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
STRIPE_PRICE_SMALL_MONTH=
STRIPE_PRICE_SMALL_YEAR=
# ... standard/enterprise month/year

# OIDC defaults (Wave 8+)
# per-org issuer/client in DB; platform defaults optional

# VCS / integrations (Wave 9–10)
GITHUB_APP_ID=
GITHUB_APP_PRIVATE_KEY=
# ...

# AI (Wave 11)
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
CRA_AI_PROVIDER=stub

# Inngest
INNGEST_EVENT_KEY=
INNGEST_SIGNING_KEY=
```

---

## 6. Cursor rules (Etap 0)

Добави в Next проекта `.cursor/rules/`:

1. **Laravel reference** — чети от `process.env.LARAVEL_REFERENCE_ROOT` / локален клонинг на разработчика (Windows path OK). **Не** hardcode `/var/www/...` в споделени файлове.
2. **Prefer shadcn** — Switch за booleans; ui/* преди raw HTML.
3. **Server DataTables** — index pages = shell + API paginator; виж `06_`.
4. **Functionality first** — без декоративен redesign.
5. **Button hierarchy** — един primary CTA; outline за secondary (от Laravel rules).
6. **Secrets** — `.env.local` never committed; Neon/Stripe keys per developer or shared vault.

---

## 7. Testing

| Слой             | Инструмент                              | Etap 0 минимум        |
| ---------------- | --------------------------------------- | --------------------- |
| Unit/integration | Vitest + Prisma test DB или Neon branch | auth session helper   |
| API              | Vitest request against route handlers   | DataTable query parse |
| E2E (later)      | Playwright                              | login + one table     |

Laravel Pest suites остават в Laravel repo като behavioral oracle.

---

## 8. Jobs / schedule mapping

| Laravel (`routes/console.php` / jobs)    | Next (Inngest)       |
| ---------------------------------------- | -------------------- |
| `audit-logs:prune`                       | cron `audit/prune`   |
| `evidence:refresh-freshness`             | cron                 |
| `billing:expire-trials`                  | cron (Wave 8)        |
| `vcs:sync-scheduled`                     | cron (Wave 9)        |
| `integrations:sync-scheduled`            | cron (Wave 10)       |
| `ai:index-embeddings`                    | cron/event (Wave 11) |
| Patch notify / auditor mail / AI analyse | event functions      |

Local: Inngest Dev Server **или** temporary `node` script runners документирани в README.

---

## 9. История

| Версия | Дата       | Промяна                                             |
| ------ | ---------- | --------------------------------------------------- |
| 1.1    | 2026-07-29 | Portable Laravel ref path; Windows/.env.local notes |
| 1.0    | 2026-07-29 | Initial conventions                                 |
