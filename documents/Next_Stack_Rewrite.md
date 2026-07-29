# Next.js stack rewrite — pointer

**Дата:** 2026-07-29 (обновено: git / Windows локални клонинги)  
**Статус:** Docs pack ready (Etap 0 в Next git repo на всяка работна станция)

Този Laravel проект остава **жива референс**. Портът към Next.js / Neon / Prisma / shadcn е описан в преносим пакет:

→ **[Next_Rewrite/00_README.md](Next_Rewrite/00_README.md)**

### Бързи връзки

| Файл                                                                     | Съдържание          |
| ------------------------------------------------------------------------ | ------------------- |
| [Next_Rewrite/01_MASTER_PLAN.md](Next_Rewrite/01_MASTER_PLAN.md)         | Етап 0 + вълни 1–11 |
| [Next_Rewrite/08_WAVE_CHECKLISTS.md](Next_Rewrite/08_WAVE_CHECKLISTS.md) | Checkbox изпълнение |
| [Next_Rewrite/04_SCHEMA_MAP.md](Next_Rewrite/04_SCHEMA_MAP.md)           | Миграции по вълни   |
| [Next_Rewrite/10_ACCEPTANCE.md](Next_Rewrite/10_ACCEPTANCE.md)           | Acceptance          |

### Source of truth (Laravel phase docs)

- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md)
- [Phase2_F_Platform_Billing_SSO.md](Phase2_F_Platform_Billing_SSO.md) (Complete)
- [Phase2_1](Phase2_1_GitHub_GitLab_Integration.md) … [Phase2_8](Phase2_8_Integration_Wave2.md)
- [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md)
- [Internal_Manual_Test_Plan.md](Internal_Manual_Test_Plan.md)
- [Product_Compliance_Wizard.md](Product_Compliance_Wizard.md)

### Следваща стъпка за хората (2× Windows 11)

1. Създай / клонирай **Next** git repo локално (пътят на диска е свободен — не е `/var/www/...`).
2. Копирай и commit-ни `documents/Next_Rewrite/` в Next repo (виж `00_README.md`).
3. Във `.env.local` задай `LARAVEL_REFERENCE_ROOT` към своя Laravel клонинг.
4. Отвори Next в Cursor → изпълни **Етап 0**.
