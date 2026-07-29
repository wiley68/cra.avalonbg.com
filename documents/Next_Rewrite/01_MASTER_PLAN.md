# Next Rewrite — Master Plan

**Версия:** 1.1  
**Дата:** 2026-07-29  
**Статус:** Active — awaiting Etap 0 in Next project (local git clones on Windows)  
**Свързани:** [00_README.md](00_README.md), [08_WAVE_CHECKLISTS.md](08_WAVE_CHECKLISTS.md)

---

## 1. Цел

Функционален порт на CRA Compliance Workspace към:

**Next.js (App Router) + Neon (Postgres) + Prisma + Better Auth + shadcn/ui**

за A/B оценка срещу текущия Laravel + Inertia/Vue стек.  
**Не** е pixel-perfect redesign. **Да** е tenant-safe, RBAC-aware, с server-side таблици и същите бизнес правила.

---

## 2. Anti-goals

- Не спирай/не рефакторирай Laravel app заради rewrite.
- Не big-bang на целия Prisma schema в ден 1.
- Не изисквай live Stripe/GitHub webhooks в local Etap 0–7 (fixtures + preview deploy).
- Не пренасяй целия `lang/*.json` наведнъж — ключове по вълни.
- Не добавяй Horizon-еквивалент; Laravel вече е без Horizon ([Phase2_E_Ops_Baseline](../Phase2_E_Ops_Baseline.md)).

---

## 3. Етап 0 — подготовка (задължителен)

Детайли: [02_STACK_AND_CONVENTIONS.md](02_STACK_AND_CONVENTIONS.md), чеклист: [08_WAVE_CHECKLISTS.md](08_WAVE_CHECKLISTS.md#etap-0).

### 0.1 Scaffold

- [ ] `create-next-app` — App Router, TypeScript, ESLint, Tailwind, `src/`
- [ ] Git remote + `.gitignore` (`.env`, `.env.local`, `uploads/`) — работете от **локални клонинги** (Windows 11 OK; пътят на диска е свободен)
- [ ] `.env.example` според секция Env в `02_` (без machine-specific paths)
- [ ] README с `pnpm install|dev|build|lint`, Prisma команди, Mailpit; забележка за `LARAVEL_REFERENCE_ROOT` per developer
- [ ] `.cursor/rules/` — Laravel ref via env/local path (не hardcode `/var/www/...`); functionality-first; shadcn Switch за booleans

### 0.2 Neon + Prisma

- [ ] Neon project + branch `dev`
- [ ] `DATABASE_URL` (pooled) + `DIRECT_URL` (миграции)
- [ ] `prisma init`; client generator; `prisma migrate dev`
- [ ] **Минимален schema (само Etap 0):** Better Auth tables + `Organization`, `OrganizationUser`, `Role`, `Permission`, `RolePermission`, `AuditLog`
- [ ] Seed: permissions, roles↔permissions, platform admin user (огледало `RolePermissionSeeder` / `PlatformAdminSeeder`)

### 0.3 Better Auth

- [ ] Email/password + session cookies
- [ ] Email verification (dev: Mailpit / log)
- [ ] Полета/флагове: `mustChangePassword`, 2FA (TOTP) — parity с Laravel middleware
- [ ] `currentOrganizationId` в session/plugin
- [ ] Routes: `/login`, `/register` (stub OK), `/logout`; protected `(app)` layout

### 0.4 shadcn + shell

- [ ] `shadcn` init (New York / neutral — без значение за паритет)
- [ ] Компоненти: Button, Input, Label, Switch, Table, Dialog, Select, DropdownMenu, Separator, Card (минимално)
- [ ] App shell: sidebar stubs, settings layout, admin gate
- [ ] i18n EN+BG — next-intl **или** лек JSON loader; subset ключове `common`, `auth`, `nav`, `settings`

### 0.5 Инфра конвенции

- [ ] DataTable API helper (`page`, `per_page`, `sort_by`, `sort_desc`, `search`) — виж [06_API_AND_DATATABLE.md](06_API_AND_DATATABLE.md)
- [ ] Local private uploads dir + authz download route stub
- [ ] Inngest (или документиран temporary stub) + `ENABLE_LIVE_WEBHOOKS=false`
- [ ] Mail: Nodemailer → Mailpit local
- [ ] Stripe/OIDC env placeholders; `POST /api/dev/stripe-replay` stub (empty handler OK)
- [ ] Sample page: platform-admin Organizations DataTable (може да връща seed data)

### 0.6 Exit критерии Etap 0

| #    | Критерий                                                  |
| ---- | --------------------------------------------------------- |
| E0.1 | `pnpm dev` стартира без грешки                            |
| E0.2 | `prisma migrate` + `db seed` към Neon успешни             |
| E0.3 | Login / logout / protected page работят                   |
| E0.4 | Поне една server-side paginated таблица                   |
| E0.5 | `.env.example` пълен спрямо `02_`                         |
| E0.6 | Този документ + чеклист Etap 0 маркирани Done в Next repo |

**Едва след E0.1–E0.6 → Вълна 1.**

---

## 4. Вълни 1–11 (+ Admin parallel)

| Вълна     | Scope                                                                                    | Laravel референс            | Schema група (`04_`)       |
| --------- | ---------------------------------------------------------------------------------------- | --------------------------- | -------------------------- |
| **1**     | Users, membership, RBAC enforce, dashboard, settings profile/security/appearance         | `users/`, Fortify, policies | Auth+RBAC (разширение)     |
| **2**     | Products, versions, support periods, scope/classification, requirements, controls, risks | MVP product core            | Product core               |
| **3**     | Evidence, tasks, SBOM/components, vulnerabilities (+ basic reporting)                    | `products/*`                | Evidence chain             |
| **4**     | Customers, deployments, patch campaigns + mail                                           | Phase 2.2                   | Customers                  |
| **5**     | Org policies, auditor packages (+ guest token), audit log UI                             | Phase 2.3                   | Policies/Auditor           |
| **6**     | Incidents, SDL, USI, technical documentation                                             | Phase 2.4–2.7               | Heavy modules              |
| **7**     | Readiness, compliance wizard, passport                                                   | Wizard doc                  | Derived / thin             |
| **8**     | Billing (plans/limits/seats, bank, Stripe, docs) + OIDC SSO                              | Phase 2_F                   | Billing/SSO                |
| **9**     | VCS GitHub/GitLab + sync + health; webhooks via deploy                                   | Phase 2.1                   | VCS                        |
| **10**    | Jira / Snyk / Azure / SARIF + scheduled sync                                             | Phase 2.8                   | Integrations               |
| **11**    | AI assistant / RAG / queued analysis                                                     | Phase 2.3 / 2_E             | AI                         |
| **Admin** | Platform admin orgs/users/requirements/billing ops                                       | `admin/*`                   | след Вълна 1 (parallel OK) |

Шаблон за всяка вълна: schema delta → migrate → domain services → Route Handlers → UI shell → RBAC → tests → acceptance ([10_ACCEPTANCE.md](10_ACCEPTANCE.md)).

---

## 5. Decision log (rewrite)

| Решение           | Избор                                   | Бележка                               |
| ----------------- | --------------------------------------- | ------------------------------------- |
| Проект hosting    | Отделен **git repo** + локални клонинги | Windows 11; пътят на диска е свободен |
| Auth              | Better Auth + Prisma                    | OIDC + 2FA + credentials              |
| Jobs              | Inngest                                 | Local stub allowed                    |
| Live webhooks     | Preview deploy                          | Local fixtures                        |
| UI                | shadcn/ui                               | Functionality first                   |
| i18n              | EN+BG progressive                       | Не big-bang                           |
| Laravel reference | Per-machine `LARAVEL_REFERENCE_ROOT`    | Не commit-вай абсолютни пътища        |

---

## 6. История

| Версия | Дата       | Промяна                                                          |
| ------ | ---------- | ---------------------------------------------------------------- |
| 1.1    | 2026-07-29 | Git/Windows локални клонинги; премахнат фиксиран `/var/www` path |
| 1.0    | 2026-07-29 | Master plan + Etap 0 + waves                                     |
