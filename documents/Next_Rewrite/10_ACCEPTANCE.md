# Next Rewrite — Acceptance

**Версия:** 1.0  
**Цел:** Функционален acceptance при сравнение с Laravel.  
**Оракул:** Laravel app + phase closeouts + Pest suites (behavioral).

---

## 1. Master gate

Rewrite се счита за **сравним** когато:

1. Вълни 1–8 Done (core tenant + billing/SSO) **или** изрично изключени модули са documented.
2. Multi-tenant + RBAC deny tests green.
3. DataTable indexes server-side for all major lists.
4. No secrets in audit logs / client bundles.
5. Preview deploy smoke for Stripe (and optionally GitHub) webhooks.

Waves 9–11 могат да останат “partial” за първото A/B решение, ако е документирано.

---

## 2. Cross-cutting acceptance

| ID  | Критерий            | Verify                                                      |
| --- | ------------------- | ----------------------------------------------------------- |
| X1  | Org isolation       | User A never sees User B org data                           |
| X2  | Platform admin gate | Non-admin 403 on `/admin`                                   |
| X3  | Permission deny     | Missing permission → 403 + UI hide                          |
| X4  | i18n                | Critical flows EN + BG                                      |
| X5  | Audit               | Login, plan change, billing activate, doc send, SSO connect |
| X6  | Files               | Unauthorized download 403                                   |
| X7  | Session gates       | Unverified / mustChangePassword / 2FA redirect              |

---

## 3. By wave (summary)

### W1

- [ ] Owner can manage users; read_only cannot
- [ ] Dashboard loads for member
- [ ] Settings profile/security/appearance persist

### W2

- [ ] Free plan product limit enforced (1) when billing active
- [ ] Versions/support/scope/classification/requirements/controls/risks CRUD paths work
- [ ] Cross-org product 404/403

### W3

- [ ] Evidence upload/download
- [ ] Task approve path
- [ ] Component import + vulnerability create

### W4

- [ ] Customer + deployment + campaign notify (mail captured)

### W5

- [ ] Policy CRUD
- [ ] Auditor package guest link view
- [ ] Audit log searchable

### W6

- [ ] Incident close workflow essentials
- [ ] SDL run stage update
- [ ] USI + tech doc create/export smoke

### W7

- [ ] Readiness page renders with signals
- [ ] Wizard can complete/dismiss per Laravel rules
- [ ] Passport view

### W8 (Phase 2_F A1–A8)

- [ ] A1 Free max 1 product
- [ ] A2 Small/Standard/Enterprise 3/10/unlimited
- [ ] A3 Signup Free usable
- [ ] A4 Bank pending → admin activate; docs admin/tenant rules
- [ ] A5 Stripe test checkout → webhook/replay → active; cancel/past_due
- [ ] A6 OIDC happy path + domain reject
- [ ] A7 Admin can still create org
- [ ] A8 No secrets in audit; secrets encrypted

### W9–W11

- [ ] VCS connect + sync run recorded
- [ ] One wave2 provider sync or SARIF import
- [ ] AI stub chat returns deterministic response

---

## 4. Laravel docs to tick against

| Doc             | Path                                                                                     |
| --------------- | ---------------------------------------------------------------------------------------- |
| Internal manual | [Internal_Manual_Test_Plan.md](../Internal_Manual_Test_Plan.md)                          |
| Phase 2_F       | [Phase2_F_Platform_Billing_SSO.md](../Phase2_F_Platform_Billing_SSO.md)                  |
| Closeouts       | `../Phase2_*_Release_Closeout.md`, [MVP_Release_Closeout.md](../MVP_Release_Closeout.md) |
| Wizard          | [Product_Compliance_Wizard.md](../Product_Compliance_Wizard.md)                          |
| Ops             | [Phase2_E_Ops_Baseline.md](../Phase2_E_Ops_Baseline.md)                                  |

Pest suites (ideas to re-express in Vitest):  
`SubscriptionPlanProductLimitTest`, `BankPaymentActivationTest`, `StripeBillingTest`, `SsoOidcTest`, `BillingDocumentsTest`, `Phase2FAuditTest`, `Phase2FI18nTest`, plus domain Feature tests per phase.

---

## 5. Decision template (след тестовете)

```markdown
## Stack decision — YYYY-MM-DD

- Winner: Laravel | Next
- Reasons: ...
- Gaps accepted in loser: ...
- Cutover plan: ...
```

---

## 6. История

| Версия | Дата       | Промяна          |
| ------ | ---------- | ---------------- |
| 1.0    | 2026-07-29 | Acceptance gates |
