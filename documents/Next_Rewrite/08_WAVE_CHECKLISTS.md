# Next Rewrite — Wave Checklists

**Версия:** 1.1  
**Употреба:** Копирай чеклиста в Cursor session / PR. Маркирай `[x]` само след verify.

---

## Etap 0

- [ ] Next.js scaffold (App Router, TS, Tailwind, `src/`)
- [ ] Git remote; локални клонинги на Windows 11 (пътят на диска е свободен)
- [ ] `.env.example` + per-dev `.env.local` (`LARAVEL_REFERENCE_ROOT`, Neon) — secrets never committed
- [ ] README + Cursor rules (portable Laravel ref, shadcn, DataTable, functionality-first)
- [ ] Neon project + `DATABASE_URL` / `DIRECT_URL`
- [ ] Prisma init + first migrate (auth/org/rbac/audit)
- [ ] Seed roles/permissions/platform admin
- [ ] Better Auth (login/logout/session)
- [ ] mustChangePassword + 2FA hooks (stub UI OK)
- [ ] Org context on session
- [ ] shadcn core components
- [ ] App / settings / admin layout shells
- [ ] i18n EN+BG minimal
- [ ] DataTable helper + sample Organizations API + page
- [ ] Uploads dir stub
- [ ] Mailpit wiring
- [ ] Inngest stub or Dev Server noted in README
- [ ] `ENABLE_LIVE_WEBHOOKS=false` + stripe-replay stub route
- [ ] Exit: E0.1–E0.6 from [01_MASTER_PLAN.md](01_MASTER_PLAN.md)

**Done when:** login works, Neon seeded, one paginated table, env documented.

---

## Вълна 1 — Users, RBAC, dashboard, settings

- [ ] User CRUD / invite within org (parity `UserController`)
- [ ] Role assign (`users.assign_roles`)
- [ ] Enforce permissions on API + UI gates
- [ ] Dashboard shell (`DashboardService` KPIs — може slim)
- [ ] Settings: profile, security (password/2FA), appearance
- [ ] Locale switch EN/BG
- [ ] Audit: login events
- [ ] Tests: authz happy/deny
- [ ] Acceptance vs Laravel users flows

**Admin parallel (може заедно с W1):**

- [ ] Admin organizations DataTable CRUD
- [ ] Admin org users
- [ ] Platform audit logs index (basic)

---

## Вълна 2 — Products core

- [ ] Prisma migrate product/requirements/controls/risks/versions/support/scope/classification
- [ ] Products index DataTable + create/edit
- [ ] Product versions CRUD
- [ ] Support periods
- [ ] Scope + classification fields/flows (slim OK; full wizard → W7)
- [ ] Requirements catalog (admin) + product requirements
- [ ] Controls + product controls
- [ ] Risks + pivots
- [ ] Product limit enforcement hooks (billing may still be free-only until W8)
- [ ] RBAC products.* / requirements.* / controls.* / risks.*
- [ ] Tests + acceptance

---

## Вълна 3 — Evidence chain

- [ ] Evidence upload/list/link + private download
- [ ] Tasks + submit/approve/reject
- [ ] SBOM import + components
- [ ] Vulnerabilities CRUD + basic reporting submission
- [ ] RBAC evidence/tasks/components/vulnerabilities
- [ ] Tests + acceptance

---

## Вълна 4 — Customers / deployments / campaigns

- [ ] Customers CRUD + CSV import if Laravel has it
- [ ] Product deployments
- [ ] Patch campaigns + targets + notification events
- [ ] Email notify via queue/Inngest (Mailpit)
- [ ] Tests + acceptance (Phase 2.2 closeout)

---

## Вълна 5 — Policies / auditor / audit UI

- [ ] Org policies library
- [ ] Auditor review packages + findings
- [ ] Guest token review page (no login)
- [ ] Share/export/email paths
- [ ] Tenant + admin audit log UI (filters)
- [ ] Tests + acceptance (Phase 2.3)

---

## Вълна 6 — Incidents / SDL / USI / tech docs

- [ ] Incidents + timeline/reports/comms/lessons links
- [ ] SDL runs/stages/exceptions/evidence links
- [ ] USI packages/sections/export
- [ ] Technical documentation packages/sections/generate/export/lifecycle
- [ ] Org-level indexes where Laravel has them
- [ ] Tests + acceptance (Phase 2.4–2.7 closeouts)

---

## Вълна 7 — Readiness / wizard / passport

- [ ] Readiness report service port
- [ ] Compliance wizard (see `Product_Compliance_Wizard.md`)
- [ ] Compliance passport view
- [ ] Tests + acceptance

---

## Вълна 8 — Billing / SSO

- [ ] Plan catalog + product/seat limits enforcement
- [ ] Public registration + org bootstrap (Free active / paid pending)
- [ ] Bank payment request + admin activate
- [ ] Billing documents (admin upload/send; tenant download when active)
- [ ] Stripe Checkout + webhook handlers (+ local replay)
- [ ] OIDC SSO settings + login redirect/callback + domain policy
- [ ] Audit: plan change, activate, doc send, SSO connect (no secrets)
- [ ] i18n billing/sso keys EN+BG
- [ ] Tests (port Pest ideas from `*Billing*`, `SsoOidc*`, `Phase2F*`)
- [ ] Acceptance A1–A8 from Phase 2_F

---

## Вълна 9 — VCS

- [ ] Org VCS connections (GitHub/GitLab)
- [ ] Product repositories + sync runs
- [ ] Webhooks (deploy/tunnel) + local fixtures
- [ ] Import suggestions
- [ ] Integration health basics
- [ ] Scheduled sync Inngest
- [ ] Acceptance Phase 2.1

---

## Вълна 10 — Integrations wave2

- [ ] Jira / Snyk / Azure / SARIF connections + product links
- [ ] Sync runs + import suggestions
- [ ] Scheduled sync
- [ ] Operator runbook notes in Next README
- [ ] Acceptance Phase 2.8

---

## Вълна 11 — AI

- [ ] Assistant UI + conversations/messages
- [ ] Provider stub + live flag
- [ ] Embeddings index job
- [ ] Queued analysis jobs
- [ ] RBAC
- [ ] Acceptance Phase 2.3/2_E LLM docs

---

## След всички вълни

- [ ] [10_ACCEPTANCE.md](10_ACCEPTANCE.md) master checklist
- [ ] Preview deploy with live webhooks smoke
- [ ] Decision memo: Laravel vs Next (out of scope of code)

---

## История

| Версия | Дата       | Промяна              |
| ------ | ---------- | -------------------- |
| 1.0    | 2026-07-29 | Full wave checklists |
