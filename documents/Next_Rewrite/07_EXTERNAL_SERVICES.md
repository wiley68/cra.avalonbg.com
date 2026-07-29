# Next Rewrite — External Services

**Версия:** 1.0  
**Свързани:** [01_MASTER_PLAN.md](01_MASTER_PLAN.md), [09_DIFFS_AND_RISKS.md](09_DIFFS_AND_RISKS.md)

---

## 1. Матрица local vs deploy

| Услуга                     | Local (Etap 0–N)                                                      | Preview / staging deploy                      |
| -------------------------- | --------------------------------------------------------------------- | --------------------------------------------- |
| **Neon**                   | Dev branch                                                            | Same or prod-like branch                      |
| **Mail**                   | Mailpit / log                                                         | Resend / SMTP                                 |
| **Stripe Checkout**        | Test keys; create session OK                                          | Test keys                                     |
| **Stripe webhooks**        | `ENABLE_LIVE_WEBHOOKS=false` + `POST /api/dev/stripe-replay` fixtures | Stripe CLI forward **или** Dashboard endpoint |
| **OIDC SSO**               | Fake discovery via MSW/nock **или** real Entra test tenant            | Real IdP                                      |
| **GitHub/GitLab webhooks** | Replay fixtures                                                       | Public URL + secret                           |
| **GitHub/GitLab API**      | Optional real PAT in `.env`                                           | Same                                          |
| **Jira / Snyk / Azure**    | Mock HTTP                                                             | Optional live connectors                      |
| **SARIF**                  | File upload only                                                      | Same                                          |
| **OpenAI / Anthropic**     | `CRA_AI_PROVIDER=stub`                                                | Live keys (Wave 11)                           |
| **Inngest**                | Inngest Dev Server                                                    | Inngest cloud                                 |

---

## 2. Stripe (Wave 8)

Laravel: `StripeBillingService`, `StripeCheckoutGateway`, `Api/StripeWebhookController`, `config/billing.php`.

Next:

- Official `stripe` Node SDK (или fetch) в `src/server/services/stripe-billing.ts`
- Checkout Session create от settings billing
- Webhook route `POST /api/webhooks/stripe` — verify signature when `ENABLE_LIVE_WEBHOOKS=true`
- Dev replay: accept JSON event body без signature (guarded by `NODE_ENV=development` + flag)
- Events parity: `checkout.session.completed`, `customer.subscription.updated|deleted`, `invoice.paid|payment_failed`

Fixtures: копирай payload patterns от `tests/Feature/StripeBillingTest.php`.

---

## 3. OIDC SSO (Wave 8)

Laravel: `OidcClient`, `OrganizationSsoService`, `SsoAuthController`.

Правила за запазване:

- Enterprise always; Standard via `sso_enabled`
- Allowed email domains; reject unknown domain
- No JIT provisioning — user must already be org member
- `client_secret` encrypted at rest (`APP_ENCRYPTION_KEY`)
- Never log secrets (audit details)

Local: Http fake за discovery/token/userinfo (като Pest `Http::fake`).

---

## 4. VCS (Wave 9)

| Provider         | Laravel                              | Next                                        |
| ---------------- | ------------------------------------ | ------------------------------------------- |
| GitHub PAT / App | `Services/Vcs/*`, webhook controller | Port providers; `POST /api/webhooks/github` |
| GitLab PAT       | GitLab provider                      | Same                                        |

Sync runs + import suggestions + health UI.

Local webhooks: fixture replay endpoint.

---

## 5. Integrations wave2 (Wave 10)

Jira Cloud, Snyk, Azure DevOps, SARIF upload — Laravel `Phase2_8_*` + `OrganizationIntegration` models.

Scheduled sync → Inngest cron.

Operator notes: [Phase2_8_Integrations_Operator_Runbook.md](../Phase2_8_Integrations_Operator_Runbook.md).

---

## 6. AI (Wave 11)

| Laravel                              | Next                     |
| ------------------------------------ | ------------------------ |
| `config/ai.php`, stub/live providers | `CRA_AI_PROVIDER=stub    | openai | anthropic` |
| Queued analysis / embeddings         | Inngest functions        |
| RAG chunks                           | `AiEmbeddingChunk` table |

Не блокирай по-ранни вълни на live LLM.

---

## 7. Email

| Use case                  | Laravel Mail          | Next                 |
| ------------------------- | --------------------- | -------------------- |
| Patch campaign notify     | Mailable + queue      | Inngest + Nodemailer |
| Auditor share             | Mailable              | Same                 |
| Billing document send     | `BillingDocumentMail` | Same                 |
| Auth verification / reset | Fortify               | Better Auth hooks    |

---

## 8. Secrets checklist

- [ ] Neon URLs not committed
- [ ] `BETTER_AUTH_SECRET`, `APP_ENCRYPTION_KEY`
- [ ] Stripe test keys only in local
- [ ] VCS tokens encrypted in DB
- [ ] SSO client_secret encrypted
- [ ] Webhook secrets separate per provider

---

## 9. История

| Версия | Дата       | Промяна                  |
| ------ | ---------- | ------------------------ |
| 1.0    | 2026-07-29 | External services matrix |
