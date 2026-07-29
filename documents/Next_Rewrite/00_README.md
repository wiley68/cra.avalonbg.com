# Next Rewrite — README

**Версия:** 1.1  
**Дата:** 2026-07-29  
**Статус:** Ready for Etap 0  
**Цел:** Пресъздаване на функционалността на Laravel CRA Compliance Workspace върху **Next.js + Neon + Prisma + shadcn/ui**, за сравнителни тестове. Дизайнът не е 1:1 — функционалният паритет е приоритет.

---

## 1. Двата проекта (git + локални клонинги)

| Проект                         | Как се държи                                                    | Роля                                                              |
| ------------------------------ | --------------------------------------------------------------- | ----------------------------------------------------------------- |
| **Laravel CRA (референс)**     | Отделен git repo / клонинг на всяка машина                      | Read-only source of truth за поведение, schema, RBAC, фазови docs |
| **Next rewrite (експеримент)** | **Отделен git repo**, клониран локално на всяка работна станция | Новата имплементация; тук се изпълнява този пакет                 |

**Важно за екипа (2 разработчика, Windows 11):**

- Next проектът **не** е вързан към фиксиран сървърен път като `/var/www/cra-next.avalonbg.com`.
- Всеки клонира repo-то там, където му е удобно, напр. `C:\dev\cra-next`, `D:\work\cra-next`, WSL path и т.н. — пътищата **могат да се различават**.
- Всички инструкции в пакета ползват **относителни пътища в repo** (`documents/…`, `src/…`) или env променливи — не абсолютни machine paths.
- Laravel референсът също е локален клонинг; пътят му на всяка машина се задава веднъж в Cursor rules / env (виж §2).

Laravel **не се спира и не се чупи** заради rewrite-а, докато не приключи сравнението.

---

## 2. Как да пренесеш пакета в Next repo

### Препоръчан поток (git-first)

1. Създай празен Next app локално (`create-next-app`) **или** клонирай вече създадения remote.
2. Копирай този пакет в Next repo:

```bash
# Git Bash / PowerShell — замени пътищата с ТВОИТЕ локални клонинги
# Пример Windows (Git Bash):
mkdir -p documents
cp -a /c/path/to/cra-laravel/documents/Next_Rewrite ./documents/
```

PowerShell вариант:

```powershell
New-Item -ItemType Directory -Force -Path .\documents
Copy-Item -Recurse -Force `
  C:\path\to\cra-laravel\documents\Next_Rewrite `
  .\documents\Next_Rewrite
```

3. Commit-ни `documents/Next_Rewrite/` в **Next** git repo (така и двамата разработчика го имат след `git pull`).
4. В Next repo добави локален (не commit-ван) pointer към Laravel клонинга:

`.env.local` (gitignore-нат):

```bash
# Абсолютен път само на ТАЗИ машина — не се комитва
LARAVEL_REFERENCE_ROOT=C:\path\to\cra-laravel
```

Cursor rule (в Next repo): „Laravel reference = `LARAVEL_REFERENCE_ROOT` или ръчно зададен local path на разработчика.“

Опционално копиране на phase docs (ако искаш offline четене без втори repo отворен):

```powershell
Copy-Item -Recurse C:\path\to\cra-laravel\documents\* .\documents\laravel-reference\
# или git submodule — по желание; не е задължително за Etap 0
```

Отвори **Next** проекта в Cursor и кажи:

> Изпълни Етап 0 от `documents/Next_Rewrite/01_MASTER_PLAN.md` и чеклиста в `08_WAVE_CHECKLISTS.md`.

---

## 3. Ред на файловете в пакета

| #   | Файл                                                       | Кога                            |
| --- | ---------------------------------------------------------- | ------------------------------- |
| 00  | [00_README.md](00_README.md)                               | Старт (този файл)               |
| 01  | [01_MASTER_PLAN.md](01_MASTER_PLAN.md)                     | Етап 0 + вълни 1–11             |
| 02  | [02_STACK_AND_CONVENTIONS.md](02_STACK_AND_CONVENTIONS.md) | Технически конвенции            |
| 03  | [03_DOMAIN_INVENTORY.md](03_DOMAIN_INVENTORY.md)           | Домейни и Laravel → Next карта  |
| 04  | [04_SCHEMA_MAP.md](04_SCHEMA_MAP.md)                       | Таблици / Prisma групи по вълни |
| 05  | [05_AUTH_RBAC.md](05_AUTH_RBAC.md)                         | Auth, роли, permissions         |
| 06  | [06_API_AND_DATATABLE.md](06_API_AND_DATATABLE.md)         | API + server DataTable          |
| 07  | [07_EXTERNAL_SERVICES.md](07_EXTERNAL_SERVICES.md)         | Stripe, OIDC, VCS, AI, mail     |
| 08  | [08_WAVE_CHECKLISTS.md](08_WAVE_CHECKLISTS.md)             | Checkbox чеклисти               |
| 09  | [09_DIFFS_AND_RISKS.md](09_DIFFS_AND_RISKS.md)             | Разлики и рискове               |
| 10  | [10_ACCEPTANCE.md](10_ACCEPTANCE.md)                       | Acceptance / closeout           |

---

## 4. Defaults (фиксирани)

- **Auth:** Better Auth + Prisma adapter
- **DB:** Neon (Postgres) + Prisma Migrate — **общ remote DB** за екипа (или отделни Neon branches per developer; документирай в Next README)
- **UI:** shadcn/ui (функция > пикселен паритет)
- **Jobs:** Inngest (local stub + deploy)
- **Webhooks:** local = fixtures / replay endpoints; live = preview deploy
- **Package manager:** `pnpm` (ако липсва — `npm`; документирай в README на Next проекта)
- **OS:** Windows 11 OK (Node LTS, Git, Cursor); WSL2 по желание, не е задължителен

---

## 5. Laravel source-of-truth docs

Докато пакетът още живее в Laravel repo, относителните линкове са:

- [CRA_Compliance_Workspace_Nachalen_Plan.md](../CRA_Compliance_Workspace_Nachalen_Plan.md)
- [Phase2_F_Platform_Billing_SSO.md](../Phase2_F_Platform_Billing_SSO.md)
- [Phase2_1_GitHub_GitLab_Integration.md](../Phase2_1_GitHub_GitLab_Integration.md) … [Phase2_8](../Phase2_8_Integration_Wave2.md)
- [Phase2_E_Cross_Phase_Polish.md](../Phase2_E_Cross_Phase_Polish.md), [Phase2_E_Ops_Baseline.md](../Phase2_E_Ops_Baseline.md)
- [Product_Compliance_Wizard.md](../Product_Compliance_Wizard.md)
- [Internal_Manual_Test_Plan.md](../Internal_Manual_Test_Plan.md)
- Closeout файлове `Phase2_*_Release_Closeout.md`, [MVP_Release_Closeout.md](../MVP_Release_Closeout.md)

В Next клонинг: същите файлове през отворен Laravel workspace **или** `documents/laravel-reference/`.

Код референс (в Laravel клонинг): `app/Models/`, `app/Services/`, `routes/web.php`, `routes/settings.php`, `resources/js/pages/`.

---

## 6. Работен ритъм (2 разработчика)

1. Всеки: `git clone` Next repo → свой локален folder → Cursor на този folder.
2. `.env.local` / Neon credentials — **лични или споделен secret store**; никога в git.
3. Изпълни **Етап 0** (веднъж координирано: schema/seed/conventions).
4. Клонове + PR-и за вълни; споделяйте progress чрез `08_WAVE_CHECKLISTS.md`.
5. При съмнение — сравни с Laravel клонинг (път от `LARAVEL_REFERENCE_ROOT`).
6. Не сливай rewrite промени обратно в Laravel до решението „кой стак остава“.

---

## 7. История на пакета

| Версия | Дата       | Промяна                                                      |
| ------ | ---------- | ------------------------------------------------------------ |
| 1.1    | 2026-07-29 | Git + Windows локални клонинги; без фиксиран `/var/www` path |
| 1.0    | 2026-07-29 | Първи преносим пакет (Etap 0 + вълни 1–11)                   |
