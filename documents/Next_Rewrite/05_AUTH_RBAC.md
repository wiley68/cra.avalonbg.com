# Next Rewrite — Auth & RBAC

**Версия:** 1.0  
**Laravel refs:** `app/Enums/RoleSlug.php`, `PermissionSlug.php`, `database/seeders/RolePermissionSeeder.php`, `app/Policies/*`, Fortify + middleware

---

## 1. Auth mapping (Fortify → Better Auth)

| Laravel                                        | Next                                                                              |
| ---------------------------------------------- | --------------------------------------------------------------------------------- |
| Email/password login                           | Better Auth email/password                                                        |
| Registration + org bootstrap                   | Wave 1 stub / Wave 8 full (`OrganizationRegistrationService`)                     |
| Email verification                             | Better Auth verification + Mailpit                                                |
| Password reset                                 | Better Auth forgot-password                                                       |
| Force password change (`must_change_password`) | Custom flag + middleware/layout gate                                              |
| 2FA TOTP (`two_factor_*`)                      | Better Auth 2FA plugin                                                            |
| Session cookie                                 | Better Auth session                                                               |
| `is_platform_admin`                            | Boolean on user **или** global role — запази boolean за паритет                   |
| OIDC SSO                                       | Wave 8 — Better Auth generic OIDC **или** custom OIDC (Laravel `OidcClient` port) |
| SSO domain policy / no JIT                     | Запази: само съществуващ org user by email                                        |

---

## 2. Roles (`RoleSlug`)

| Slug                  | Бележка                                                  |
| --------------------- | -------------------------------------------------------- |
| `platform_admin`      | Обикновено чрез `is_platform_admin`; role slug за labels |
| `organization_owner`  | Пълен tenant manage                                      |
| `product_owner`       |                                                          |
| `security_owner`      |                                                          |
| `developer`           |                                                          |
| `compliance_reviewer` |                                                          |
| `release_approver`    |                                                          |
| `auditor`             |                                                          |
| `external_consultant` |                                                          |
| `read_only`           |                                                          |

Membership: `organization_user.role_id` → една роля на user **per org**.

---

## 3. Permissions (`PermissionSlug`)

| Slug                                                                                   |
| -------------------------------------------------------------------------------------- |
| `platform.admin`                                                                       |
| `users.view` / `users.create` / `users.update` / `users.delete` / `users.assign_roles` |
| `organizations.view` / `organizations.manage`                                          |
| `products.view` / `products.manage`                                                    |
| `requirements.view` / `requirements.manage`                                            |
| `controls.view` / `controls.manage`                                                    |
| `risks.view` / `risks.manage`                                                          |
| `components.view` / `components.manage`                                                |
| `releases.view` / `releases.approve`                                                   |
| `vulnerabilities.view` / `vulnerabilities.manage`                                      |
| `incidents.view` / `incidents.manage`                                                  |
| `sdl.view` / `sdl.manage`                                                              |
| `technical_documentation.view` / `technical_documentation.manage`                      |
| `evidence.view` / `evidence.manage`                                                    |
| `tasks.view` / `tasks.manage` / `tasks.approve`                                        |
| `audit.view`                                                                           |

Seed матрицата роля→permissions от Laravel `RolePermissionSeeder` — не изобретявай нова матрица.

---

## 4. Guards / middleware parity

| Laravel                         | Next                                                    |
| ------------------------------- | ------------------------------------------------------- |
| `auth`                          | Better Auth session required                            |
| `verified`                      | emailVerified check                                     |
| `password.changed`              | `!mustChangePassword`                                   |
| `two-factor.enabled`            | 2FA confirmed when org/policy requires                  |
| `can:platform.admin`            | `(admin)` layout + `platform.admin` / `isPlatformAdmin` |
| Policy `update` on Organization | `organizations.manage` in current org                   |

Shared session props (Laravel `HandleInertiaRequests`) → Next: `getSessionContext()` returning:

```ts
{
  user: { id, name, email, isPlatformAdmin, role, permissions, flags... },
  organization: { id, name, slug, locale, subscriptionPlan, canUseSso, ... }
}
```

---

## 5. Implementation sketch

```text
src/lib/auth.ts          # betterAuth({ database: prismaAdapter, plugins: [twoFactor(), ...] })
src/lib/rbac.ts          # hasPermission(user, org, slug), assertPermission(...)
src/lib/org-context.ts   # resolveCurrentOrganization(session)
src/middleware.ts        # protect (app) + (admin); redirect force-password / 2fa setup
```

Audit: login success/fail, SSO connect/login, plan change — port `AuditLogger` event type strings от `App\Enums\AuditEventType`.

---

## 6. История

| Версия | Дата       | Промяна                         |
| ------ | ---------- | ------------------------------- |
| 1.0    | 2026-07-29 | Roles/permissions + Fortify map |
