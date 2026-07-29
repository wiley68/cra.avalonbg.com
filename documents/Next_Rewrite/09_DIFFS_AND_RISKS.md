# Next Rewrite — Diffs & Risks

**Версия:** 1.0

---

## 1. Очаквани архитектурни разлики

| Тема            | Laravel                  | Next                        | Риск / митигация                                               |
| --------------- | ------------------------ | --------------------------- | -------------------------------------------------------------- |
| Rendering       | Inertia + Vue SPA props  | RSC + client islands        | Държи DataTable client-side fetch                              |
| Auth            | Fortify + session        | Better Auth                 | Map flags carefully (2FA, mustChangePassword)                  |
| Queues          | `database` queue workers | Inngest                     | Local Dev Server; document ops                                 |
| Horizon         | Skipped                  | N/A                         | Не добавяй ненужен Redis worker stack                          |
| Files           | `storage/app/private`    | `uploads/` or Blob          | Authz on every download                                        |
| Encrypted casts | Eloquent `encrypted`     | `lib/crypto.ts`             | Single `APP_ENCRYPTION_KEY`; never log plaintext               |
| Webhooks        | Public `/api/webhooks/*` | Same + replay               | Local without public URL                                       |
| Validation      | FormRequest              | Zod                         | Share schemas between client/server                            |
| Policies        | Laravel Policy classes   | `rbac.ts` helpers           | Centralize deny messages                                       |
| i18n            | `lang/*.json` huge       | Progressive keys            | Port per wave                                                  |
| Enums           | PHP enums                | Zod unions / Prisma enums   | Keep **string values** identical                               |
| Soft deletes    | Rare                     | Match Laravel               | Check each model                                               |
| Timezones       | App TZ                   | UTC store, display local    | ISO strings in API                                             |
| OS / paths      | Fixed server paths (old) | Windows 11 local git clones | `LARAVEL_REFERENCE_ROOT` per machine; no `/var/www` assumption |

---

## 2. Functional parity risks (high)

1. **Multi-tenant leaks** — every query must scope `organizationId` (except platform admin).
2. **RBAC drift** — seed from Laravel seeder; add tests for deny paths.
3. **Billing state machine** — pending_payment locks product create; past_due UX (Phase 2_F Should).
4. **SSO no-JIT + domain reject** — security-sensitive; port tests from `SsoOidcTest`.
5. **Webhook idempotency** — Stripe/GitHub retries; make handlers idempotent.
6. **File/PII** — evidence & billing docs must not be publicly listable.
7. **Guest auditor tokens** — expiry/revoke parity.

---

## 3. Local development limitations

| Limitation                 | Workaround                                            |
| -------------------------- | ----------------------------------------------------- |
| No public URL for webhooks | Fixtures + `/api/dev/*-replay`; Stripe CLI on preview |
| Neon cold starts           | Pooled URL; keep dev branch warm                      |
| Email                      | Mailpit                                               |
| Real Entra                 | Optional later; Http mock first                       |
| Long-running sync          | Inngest steps; show sync run status UI                |

---

## 4. What NOT to “improve” during port

- Do not redesign information architecture mid-port.
- Do not rename permission slugs.
- Do not change plan limit numbers without documenting deviation.
- Do not drop audit events that Phase 2_F Must 9 requires.

Deviations → log in Decision log ([01_MASTER_PLAN.md](01_MASTER_PLAN.md) §5) inside Next repo.

---

## 5. Rollback / comparison strategy

- Laravel stays production-capable reference.
- Next is experiment until explicit cutover decision.
- Prefer feature-flagged modules in Next rather than half-migrated UX.

---

## 6. История

| Версия | Дата       | Промяна             |
| ------ | ---------- | ------------------- |
| 1.0    | 2026-07-29 | Initial diffs/risks |
