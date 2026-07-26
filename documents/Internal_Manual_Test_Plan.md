# Internal Manual Test Plan — реални продукти (pre–Phase 2_F)

**Версия:** 1.12  
**Дата:** 26 юли 2026 г.  
**Статус:** Active — ръчни тестове с реални данни (блокира Candidate F)  
**Родителски документи:**

- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§11 MVP flow, §14–§16)
- [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md) (Must/Should/Could complete → вътрешно тестване)
- Phase 2.1–2.8 closeouts (модули Closed)

> **Цел:** структурирано **ръчно** обхождане на цялата система с **твои реални продукти** (максимално пълен набор от елементи), преди Phase 2_F (SSO / billing / onboarding).

> **Не е:** автоматизиран Pest/CI suite, Candidate F, optional 2.9 scanner depth, или замяна на feature tests.

> **Метод:** само човек в UI с реални данни. Находките се записват тук (или в linked backlog секции) — без „случайни кликове“.

---

## 1. Три цели (защо този план)

| #     | Цел                                                                       | Изход                                                         |
| ----- | ------------------------------------------------------------------------- | ------------------------------------------------------------- |
| **A** | Разбиране на **предпочитаната последователност** на модулите за help docs | Номерирана пътека (§4) + бележки „какво казваме на клиента“   |
| **B** | UI / i18n / UX / грешки / излишни или липсващи функции                    | Finding log (§8) с P0–P2; корекции в кода по приоритет        |
| **C** | Дизайн вход за бъдещ **Product Show / wizard** (блок-схема)               | Wizard design pack (§9) — блокове, редове, статуси, съобщения |

**Граница с F:** F започва **чак след** exit criteria (§11). Help docs и Show wizard **имплементацията** могат да са отделни вълни след този план (или част от polish backlog) — този документ ги **проектира** чрез тестовете.

---

## 2. Принципи

1. **Ръчно only** — ти (човек) в staging/prod-like инстанция; не „покриваме“ с Pest.
2. **Реални продукти** — поне **2** продукта с различни профили (напр. SaaS software + втори с network/remote processing или FOSS nuance), плюс **org-wide** елементи.
3. **Пълен coverage** — всеки product-scoped модул + всеки org/cross-product surface поне веднъж; задължителните пътеки (§5) end-to-end.
4. **Една предпочитана главна пътека** (§4) — опростяваме движенията; алтернативите са изрично „странични“ (§6).
5. **Записвай находки веднага** (§8 template) — иначе се губят за Goal B/C.
6. **Без silent auto-accept** — AI / import suggestions винаги human review.
7. **Disclaimer** — системата не дава юридическа CRA гаранция; готовността е operational readiness.

---

## 3. Подготовка (преди ден 1)

### 3.1 Среда

- [ ] Staging (или dedicated org) с `QUEUE_CONNECTION=database` (не `sync`)
- [ ] `schedule:run` + `queue:work` активни ([Phase2_E_Ops_Baseline.md](Phase2_E_Ops_Baseline.md); samples в `ops/samples/`)
- [ ] `php artisan ops:baseline-check` → exit 0
- [ ] `php artisan ops:ai-check` → exit 0 (stub OK; live provider optional за AI surfaces)
- [ ] EN + BG locale switch готови (тествай ключови екрани и на двата)
- [ ] Роли: поне **Owner** (ти) + **Viewer** акаунт за RBAC smoke

### 3.2 Реални данни (inventory checklist)

Подготви / въведи така, че да „удариш“ всички типове:

| Категория               | Минимум за „пълен“ тест                                              |
| ----------------------- | -------------------------------------------------------------------- |
| Продукти                | 2 реални                                                             |
| Версии                  | ≥1 released + ≥1 draft/dev; `release_date` + git_ref където има      |
| Support periods         | ≥1 активен период                                                    |
| Customers               | ≥2 клиенти (може още в етап **0**; formal gate = стъпка **15**)      |
| Deployments             | ≥2 инсталации (различни versions / support)                          |
| Patch campaign          | ≥1 (draft → active → notify path)                                    |
| Requirements + Controls | матрица + връзки; org controls library прегледана в етап **0**       |
| Risks                   | ≥2 с treatment                                                       |
| Components / SBOM       | Composer lock и/или CycloneDX                                        |
| Vulnerabilities         | ≥1 с reporting pack (24h/72h/final path)                             |
| Incidents               | ≥1 с timeline / authority или customer comms ако имаш данни          |
| SDL run                 | ≥1 през stages до release_approval                                   |
| Evidence                | файлове + link + freshness states                                    |
| Tasks                   | open + approval flow                                                 |
| USI                     | draft → published (EN и/или BG pair ако ползвате)                    |
| Tech doc                | draft → published + export                                           |
| Policies                | ≥1 org policy (чернови уместни в етап **0**; approve след продукт)   |
| VCS                     | GitHub и/или GitLab link + sync + merged-PR summary на version show  |
| Integrations            | Jira и/или ADO + Snyk (или SARIF upload) + Accept/Dismiss            |
| Auditor                 | ≥1 package + guest link smoke                                        |
| AI                      | triage draft + (optional) assistant / RAG след `ai:index-embeddings` |
| Audit log               | преглед след ключови действия                                        |

### 3.3 Работен журнал

Създай (локално или в края на този файл §12) сесия:

```text
Сесия: YYYY-MM-DD
Продукт: …
Locale: bg|en
Роля: owner|viewer
Пътека: A / B / C … (§5)
Находки: F-001 …
Wizard notes: W-…
```

---

## 4. Предпочитана последователност (главна пътека)

Това е **каноничният ред**, който искаме клиентът да следва. Номерацията е за help docs + бъдещия Product Show wizard.  
Модулите извън номерацията са **org-wide / успоредни** (§7) — без фиксиран ред спрямо продукта.

### 4.1 Product spine (numbered)

| Стъпка | Модул / действие                                                                                          | UI вход (ориентир)                                 | „Готово“ когато…                                                                           | Статус                | Бележка за клиента                                                     |
| ------ | --------------------------------------------------------------------------------------------------------- | -------------------------------------------------- | ------------------------------------------------------------------------------------------ | --------------------- | ---------------------------------------------------------------------- |
| **0**  | **Org prep:** settings, users/roles, **controls library**, **policies** (draft), **customers** (optional) | Settings / Users / Controls / Policies / Customers | Owner + Viewer; i18n OK; controls прегледани; ≥1 policy draft (по тип); клиенти по желание | **Done** (2026-07-25) | Преди продукти — виж §4.1a; help/wizard: „подготовка на организацията“ |
| **1**  | Създай / редактирай продукт                                                                               | Products → Create / Edit                           | Име, тип, licensing, connectivity полета                                                   | **Done** (2026-07-25) | Картотека; modules меню + цветове по готовност                         |
| **2**  | CRA **scope** assessment                                                                                  | Product Edit → scope wizard                        | Scope status + review                                                                      | **Done** (2026-07-25) | Не е правно заключение                                                 |
| **3**  | **Classification**                                                                                        | Product Edit → classification wizard               | Classification status + review                                                             | **Done** (2026-07-25) | След scope                                                             |
| **4**  | **Versions**                                                                                              | Product → Versions                                 | ≥1 version; release_date когато е release                                                  | **Done** (2026-07-25) | Котва за support / SBOM / SDL                                          |
| **5**  | **Support periods**                                                                                       | Support periods                                    | Период(и) вързани към версия/продукт                                                       | **Done** (2026-07-25) | Паралелно след versions OK                                             |
| **6**  | **VCS / Integrations** (по желание рано)                                                                  | Settings Integrations → Product Edit links         | Active connector + product link; Sync now                                                  | **Done** (2026-07-25) | Покрито при A_1: GitHub, Jira, SARIF upload                            |
| **7**  | **Components / SBOM**                                                                                     | Components → import                                | Inventory + import checksum/evidence                                                       | **Done** (2026-07-25) | Преди vulns по възможност                                              |
| **8**  | **Risks**                                                                                                 | Risks                                              | Рискове + treatment                                                                        | **Done** (2026-07-25) | Храни controls / readiness                                             |
| **9**  | **Requirements**                                                                                          | Requirements                                       | Релевантни CRA requirements                                                                | **Done** (2026-07-25) | Каталог → product matrix                                               |
| **10** | **Controls**                                                                                              | Controls (+ org library)                           | Controls + link към requirements                                                           | **Done** (2026-07-25) | Org library вече в **0**; тук product assign                           |
| **11** | **Evidence**                                                                                              | Evidence                                           | Доказателства към controls / risks / stages                                                | **Done** (2026-07-25) | Непрекъснато; първи batch тук                                          |
| **12** | **Tasks**                                                                                                 | Tasks                                              | Отворени / approval където трябва                                                          | **Done** (2026-07-25) | От gaps / imports                                                      |
| **13** | **Vulnerabilities**                                                                                       | Vulnerabilities (+ import Accept)                  | ≥1 в регистъра; triage                                                                     | **Done** (2026-07-26) | Import = human Accept                                                  |
| **14** | **Vulnerability reporting**                                                                               | Vuln → Reporting                                   | Draft → approve → submitted (според реалност)                                              | **Done** (2026-07-26) | 24h / 72h / final awareness                                            |
| **15** | **Customers**                                                                                             | Customers (org)                                    | Клиенти създадени                                                                          | **Done** (2026-07-26) | Може частично в **0**; тук gate преди deployments                      |
| **16** | **Deployments**                                                                                           | Deployments                                        | Инсталации към versions                                                                    | **Done** (2026-07-26) | Affected customers                                                     |
| **17** | **Patch campaigns**                                                                                       | Campaigns                                          | Кампания + notify/confirm path                                                             | **Done** (2026-07-26) | След vuln/deployments                                                  |
| **18** | **Incidents**                                                                                             | Incidents (product + org index)                    | Инцидент + timeline                                                                        | **Done** (2026-07-26) | Може ad-hoc; тук за пълен продукт                                      |
| **19** | **SDL**                                                                                                   | SDL                                                | Run през stages; release gate                                                              | **Done** (2026-07-26) | Вържи evidence / Git                                                   |
| **20** | **USI**                                                                                                   | Security instructions                              | Published (или under_review)                                                               | Open                  | Паралелно след versions                                                |
| **21** | **Technical documentation**                                                                               | Tech docs                                          | Package + key sections + export                                                            | Open                  | Вкл. conformity/DoC **prep** (без auto-sign)                           |
| **22** | **Compliance passport**                                                                                   | Passport                                           | Преглед; gaps осмислени                                                                    | Open                  | Обобщение                                                              |
| **23** | **Readiness**                                                                                             | Readiness → export                                 | Review + exported report                                                                   | Open                  | **Финална operational оценка** за release                              |
| **24** | **Auditor package** (опционално за външен преглед)                                                        | Auditor                                            | Package shared / guest open                                                                | Open                  | Не е задължително за всеки release                                     |
| **25** | **AI assistant / RAG** (опционално)                                                                       | Assistant                                          | Chat/analyse с human review                                                                | Open                  | След evidence; `ai:index-embeddings`                                   |

### 4.1a Предварителен етап 0 — org prep (детайл)

Канонично това е **първата** клиентска стъпка преди Product spine. Help docs и бъдещата диаграма/wizard трябва да я описват като „подготовка на организацията“, не само users/settings.

**Задължително**

- [ ] Settings / Profile / Security / Appearance (лични + org където има)
- [ ] Users + roles — поне Owner; Viewer за RBAC smoke
- [ ] **Библиотека контроли** — преглед на стартовия каталог; донастройка само при нужда (refresh starter / custom). Обикновено без промени е ОК.

**Препоръчително (не блокира стъпка 1)**

- [ ] **Клиенти** — въведи реални клиенти, с които работиш (CRUD / CSV). Не е задължително да чакаш стъпка **15**; удобно е още тук. Стъпка 15 остава formal gate преди deployments.
- [ ] **Политики** — създай org политиките по 6-те типа (библиотека). Без продукти остават **чернови** (няма review task / approve към продукт) — това е ОК. Approve + publish-as-evidence → по-късно (§5 G), когато има продукт.

**За help / wizard / диаграма**

| Блок в етап 0    | Клиентски език (ориентир)                               | Задължителен? | Done сигнал                                 |
| ---------------- | ------------------------------------------------------- | ------------- | ------------------------------------------- |
| Users / roles    | Кой работи в организацията и с какви права              | Да            | Owner + поне една втора роля за проверка    |
| Settings         | Профил, сигурност, език                                 | Да            | Входът и locale работят                     |
| Controls library | Готови мерки за съответствие — прегледай преди продукти | Да (преглед)  | Каталогът е наличен; optional tweak записан |
| Policies         | Организационни правила (disclosure, support, SDL, …)    | Да (чернови)  | Поне ключовите типове като draft            |
| Customers        | Твоите клиенти в платформата                            | Не            | ≥1 клиент ако ще ползваш deployments скоро  |

### 4.2 Диаграма (опростена)

```text
[0 Org prep] ✅
  · users / roles / settings
  · controls library (преглед)
  · policies (чернови OK)
  · customers (опционално)
      ↓
[1 Product] ✅ → [2 Scope] ✅ → [3 Classification] ✅
      ↓
[4 Versions] ✅ → [5 Support] ✅
      ↓
[6 VCS/Integrations] ✅ ──┐
      ↓                   │
[7 SBOM/Components] ✅ ←──┘
      ↓
[8 Risks] ✅ → [9 Requirements] ✅ → [10 Controls assign] ✅ → [11 Evidence] ✅ → [12 Tasks] ✅
      ↓
[13 Vulns] ✅ → [14 Reporting] ✅
      ↓
[15 Customers gate] ✅ → [16 Deployments] ✅ → [17 Campaigns] ✅
      ↓
[18 Incidents] ✅   [19 SDL] ✅   [20 USI]   [21 Tech docs]   (могат успоредно след 11–14)
      ↓
[22 Passport] → [23 Readiness] → ([24 Auditor]) → ([25 AI])
```

> **Бележка:** „Customers gate“ (15) = потвърди/допълни клиентите преди deployments. Ако вече си ги въвел в **0**, стъпка 15 е кратка проверка, не повторно въвеждане от нула. „Controls assign“ (10) = връзка към продукт/requirements; org library е в **0**.

### 4.3 Какво **не** е в spine (умишлено)

| Елемент                                  | Защо извън номерацията                                |
| ---------------------------------------- | ----------------------------------------------------- |
| Dashboard                                | Обзор; ползвай когато трябва action                   |
| Audit log                                | Непрекъснат контроль; преглеждай след ключови стъпки  |
| Integration health                       | Ops; при sync проблеми                                |
| Profile / Appearance / Security settings | Лични; покрити в стъпка **0** (§4.1a)                 |
| Admin requirements catalogue (platform)  | Platform admin only                                   |
| Policy **approve** / publish-as-evidence | След продукти; черновите са в **0**, lifecycle в §5 G |

---

## 5. Вградени пътеки (сценарии)

Изпълнявай като **отделни сесии**. Всяка използва spine-а, но фокусира различен „сюжет“.

### Пътека A — Greenfield продукт (пълен happy path)

**Цел Goal A:** еднократно преминаване 1→23 с реален продукт (след завършен етап **0** / §4.1a).  
**Цел Goal B/C:** записвай UI и wizard notes на **всяка** стъпка.

- [ ] Стъпка **0** (§4.1a) потвърдена — вкл. controls преглед; policies чернови; customers по желание
- [ ] Стъпки 1–23 по §4.1
- [ ] Passport + Readiness export запазени като артефакт на теста
- [ ] Viewer: read-only на същия продукт (без manage)

### Пътека B — Vulnerability + reporting drill

Старт от съществуващ продукт (след ≥ стъпка 7–11).

- [ ] Ръчна vuln **или** Snyk/SARIF/Dependabot suggestion → **Accept**
- [ ] AI triage draft (human review; без auto-accept)
- [ ] Reporting pack: awareness → milestones → approve → mark submitted / PDF
- [ ] Task / evidence връзки
- [ ] Campaign CTA ако remediation_pr_url / patch path съществува

### Пътека C — Incident response

- [ ] Създай incident; severity/status transitions
- [ ] Timeline events; authority report и/или customer communication (ако модулът го има)
- [ ] Връзка към vuln / task / evidence
- [ ] Org `/incidents` index vs product incidents
- [ ] AI incident summary draft (suggest/apply; no auto-save)

### Пъпка D — Release / SDL gate

- [ ] Version през states към security_review / approved / released
- [ ] SDL run: stages + evidence attach (+ Git suggest attach ако има)
- [ ] Merged-PR summary на Version Show (refresh; optional AI narrative; optional save evidence)
- [ ] Readiness gaps преди „approved/released“
- [ ] Tech doc version delta / inherit ако имаш втора версия

### Пътека E — Customers / deployments / campaigns

- [ ] Customers CRUD + (CSV import ако ползвате)
- [ ] Deployments към versions; unsupported list
- [ ] Patch campaign lifecycle + notifications / confirmations
- [ ] Dashboard / readiness signals за unsupported deployments

### Пътека F — Integrations & VCS (реални connectors)

- [ ] Settings: connect/verify GitHub или GitLab; Jira или ADO; Snyk (или SARIF upload)
- [ ] Product links + **Sync now** (без worker) + scheduled sync observation (с worker)
- [ ] Accept/Dismiss suggestions; evidence snapshot
- [ ] `/integrations/health` + ops banner ако sync/queue unhealthy
- [ ] Live connector smoke ref: [Phase2_E_Live_Connector_Smoke.md](Phase2_E_Live_Connector_Smoke.md)

### Пътека G — Policies, USI, Tech docs, Auditor

- [ ] Policy draft → review → approved
- [ ] USI multi-locale / publish / export / published evidence
- [ ] Tech doc sections + generate-from-modules + PDF/MD export
- [ ] Auditor package: create → share → guest token open → close
- [ ] Org indexes: `/sdl`, `/technical-documentation`, `/incidents`

### Пътека H — Cross-cutting / RBAC / i18n / audit

- [ ] Превключи BG ↔ EN на spine екрани; липсващи ключове → finding
- [ ] Viewer forbidden на manage actions (sync, accept, AI draft, refresh merged-PR, save evidence)
- [ ] Audit log: ключови event types без secrets (tokens/keys)
- [ ] AI surfaces error UX (timeout/failed) ако тестваш live provider
- [ ] RAG: `ai:index-embeddings` + assistant passages (optional)

---

## 6. Алтернативни / странични движения (ограничаваме ги)

За Goal C (wizard) — **разрешени** отклонения от spine:

| От           | Към                           | Кога                   |
| ------------ | ----------------------------- | ---------------------- |
| 4 Versions   | 19 SDL / 6 VCS                | Release в ход          |
| 7 Components | 13 Vulns                      | Нов finding веднага    |
| 11 Evidence  | всеки модул                   | Непрекъснато прилагане |
| 13 Vulns     | 17 Campaigns / 16 Deployments | Patch rollout          |
| Всеки        | 18 Incidents                  | Реален инцидент        |
| 22–23        | 24 Auditor                    | Външен преглед         |

**Избягвай да „рекламираш“ като нормални:** скачане Scope↔TechDoc преди versions; Readiness преди risks/controls/evidence; Accept на import без triage.

---

## 7. Org-wide / без фиксирана последователност

Обходи поне веднъж (може между пътеките):

| Surface                                                   | Какво да провериш                                                |
| --------------------------------------------------------- | ---------------------------------------------------------------- |
| Dashboard                                                 | Actions, не само графики; pending integrations / support buckets |
| Users                                                     | Invite/role (ако ползвате) — **Done** със стъпка 0 (2026-07-25)  |
| Customers                                                 | (§5 E)                                                           |
| Policies                                                  | (§5 G)                                                           |
| Auditor                                                   | (§5 G)                                                           |
| Org Incidents / SDL / Tech docs indexes                   | Навигация + deep link към product                                |
| Org Controls library                                      | Reuse в product controls                                         |
| Audit log                                                 | Filter/search след сесия                                         |
| Settings Integrations + Health                            | (§5 F)                                                           |
| Admin (ако platform): orgs, requirements catalogue, audit | Само ако си platform admin                                       |

---

## 8. Finding log (Goal B)

### 8.1 Severity

| Sev    | Значение                                                         | Действие                        |
| ------ | ---------------------------------------------------------------- | ------------------------------- |
| **P0** | Блокира пътека / data loss / security / грешен compliance signal | Фикс преди exit (§11)           |
| **P1** | Объркващ UI, липсващ превод, лоша връзка бутон→страница          | Фикс в polish вълна или преди F |
| **P2** | Nice-to-have, copy нюанс, бъдеща функция                         | Backlog (help / wizard / F+)    |

### 8.2 Типове

`i18n` · `ui` · `ux-flow` · `bug` · `perf` · `a11y` · `add-feature` · `remove-feature` · `copy` · `rbac` · `docs-note`

### 8.3 Template

```text
ID: F-001
Sev: P0|P1|P2
Type: …
Пътека / стъпка: A / 14
URL / екран: …
Locale / роля: …
Очаквано: …
Наблюдавано: …
Предложение: …
Wizard impact: (да/не + бележка)
Статус: open|fixed|wontfix|deferred
```

### 8.4 Работен backlog (попълвай по време на тестовете)

| ID  | Sev | Type | Стъпка | Резюме               | Статус |
| --- | --- | ---- | ------ | -------------------- | ------ |
| —   | —   | —    | —      | _(празно при старт)_ | —      |

---

## 9. Product Show / wizard design pack (Goal C)

**Не имплементираме Show wizard в този план** — събираме спецификация от реалното обхождане.

### 9.1 Желан резултат (за следваща имплементационна вълна)

- Product **Show** (или замяна на „гол“ Edit hub) с **номерirani блокове** = §4.1
- Визуални връзки само за **разрешени** преходи (§6)
- Статус на блок: `not_started` / `in_progress` / `done` / `attention` (gaps, expired evidence, pending suggestions)
- Системни сигнали (умерено): pending imports, failed sync, readiness blockers, reporting deadlines, support expiry
- CTA от блок → съществуващия CRUD/index (не дублиране на целите форми в wizard-а)

### 9.2 Capture template (по време на тестовете)

```text
ID: W-01
Блок #: 13
Заглавие клиентски език: …
Задължителен преди Readiness?: да/не
Входове (от кои блокове): …
Изходи (към кои): …
Done критерий (1 изречение): …
Attention сигнали: …
UI идея (карта / стъпка / timeline): …
Анти-pattern (какво да не показваме): …
```

### 9.3 Решение за опростяване (попълни след пътека A)

| Въпрос                                       | Решение (след тестове)                                                                                                   |
| -------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------ |
| Edit остава ли за „данни“, Show за „пътека“? | _TBD_                                                                                                                    |
| Кои блокове са optional в MVP wizard?        | _TBD (кандидат: 24–25)_                                                                                                  |
| Един колоннен timeline vs граф?              | _TBD_                                                                                                                    |
| Org-wide елементи в Show?                    | _TBD — етап **0** (org prep) е **преди** Product Show; Show сочи deep links към Customers / Policies / Controls library_ |
| Етап 0 в onboarding диаграмата?              | **Да** — users/roles/settings + controls review + policy drafts + optional customers (§4.1a)                             |

---

## 10. Help documentation notes (Goal A)

След пътека A (+ ключови B–G) попълни:

1. **Клиентска история в 1 страница** — етап **0** (org prep) + стъпки 1–23 на човешки език (без route names).
2. **Речник** — scope vs classification; passport vs readiness; suggestion vs accepted entity; org control vs product control; policy draft vs approved.
3. **Чести грешки** — sync без worker; Accept без преглед; readiness твърде рано; очакване да approve-неш policy без продукт.
4. **Роли** — какво прави Owner vs Viewer.
5. **Какво системата не прави** — юридическа гаранция; DoC auto-sign; ALM two-way.
6. **Етап 0 подсказки** — клиенти рано (optional); политики като чернови преди продукти; controls library = преглед, не задължителна преработка.

Черновата може да живее в `documents/help/` по-късно; тук дръж bullet notes в §12.

---

## 11. Exit criteria (преди Phase 2_F)

| #   | Критерий                                                               | Статус |
| --- | ---------------------------------------------------------------------- | ------ |
| 1   | Пътека **A** завършена за ≥1 реален продукт (1→23)                     | Open   |
| 2   | Пътеки **B–G** минати поне веднъж (или N/A с причина, напр. няма Jira) | Open   |
| 3   | Всички **P0** findings closed или workaround документиран              | Open   |
| 4   | §4.1 потвърдена или коригирана (финален numbered order)                | Open   |
| 5   | §9 wizard pack: блокове + преходи + done/attention критерии попълнени  | Open   |
| 6   | Goal A: 1-page client path draft готов за help                         | Open   |
| 7   | Phase 2_E closeout посочва този план като следваща активна вълна       | Open*  |

\*Може да се затвори заедно с формален Phase2_E closeout документ.

**След exit:** Candidate **F** (SSO / billing / onboarding) по §15–§16. След F — окончателни тестове → deploy / клиенти.

---

## 12. Сесийни бележки (работно поле)

### Сесия log

- **2026-07-26** — Spine **A_19** (SDL): **Done**. Тествано и коректно. Следваща: стъпка **20** (USI).
- **2026-07-26** — Spine **A_18** (Incidents): **Done**. Тествано и коректно. Следваща: стъпка **19** (SDL).
- **2026-07-26** — Spine **A_17** (Patch campaigns): **Done**. Тествано и коректно. Следваща: стъпка **18** (Incidents).
- **2026-07-26** — Spine **A_16** (Deployments): **Done**. Тествано и коректно. Следваща: стъпка **17** (Patch campaigns).
- **2026-07-26** — Spine **A_15** (Customers): **Done**. Тествано и коректно. Следваща: стъпка **16** (Deployments).
- **2026-07-26** — Spine **A_14** (Vulnerability reporting): **Done**. Тестове преминати успешно. Следваща: стъпка **15** (Customers).
- **2026-07-26** — Spine **A_13** (Vulnerabilities): **Done**. Тестове преминати успешно. Следваща: стъпка **14** (Vulnerability reporting).
- **2026-07-25** — Spine **A_12** (Tasks): **Done**. Тествано; корекция i18n за subject type `technical_documentation_package`. Следваща: стъпка **13** (Vulnerabilities).
- **2026-07-25** — Spine **A_11** (Evidence): **Done**. Тествано успешно. Следваща: стъпка **12** (Tasks).
- **2026-07-25** — Spine **A_10** (Controls): **Done**. Тествано и работоспособно. Следваща: стъпка **11** (Evidence).
- **2026-07-25** — Spine **A_9** (Requirements): **Done**. Тествано и коригирано (вкл. UI polish на свързани политики). Следваща: стъпка **10** (Controls).
- **2026-07-25** — Spine **A_8** (Risks): **Done**. Тестове преминати; работи коректно. Следваща: стъпка **9** (Requirements).
- **2026-07-25** — Spine **A_7** (Components / SBOM): **Done**. Тестове преминати успешно. Следваща: стъпка **8** (Risks).
- **2026-07-25** — Spine **A_6** (VCS / Integrations): **Done**. Покрито още при **A_1** (Product Create/Edit): GitHub, Jira, SARIF upload — connectors + product links + sync/upload. Следваща: стъпка **7** (Components / SBOM).
- **2026-07-25** — Spine **A_5** (Support periods): **Done**. Тестове преминати; всичко работи добре. Следваща: стъпка **6** (VCS / Integrations).
- **2026-07-25** — Spine **A_4** (Versions): **Done**. Тестове преминати; UX корекция на confirm за „Запиши като доказателство“ (Запиши / default). Следваща: стъпка **5** (Support periods).
- **2026-07-25** — Spine **A_3** (Classification): **Done**. Classification wizard тестван; промените са отразени.
- **2026-07-25** — Spine **A_2** (CRA scope assessment): **Done**. Scope wizard тестван; работи коректно.
- **2026-07-25** — Spine **A_1** (Product Create/Edit): **Done**. UI polish: section-heading стил; меню **Модули**; интеграции само при активна org-връзка; цветово кодиране на модули (critical/attention/complete/empty) в карти + меню + заглавие на продукта.
- **2026-07-25** — Spine **A_0** разширен и **Done**: users/roles/settings + **controls library** (преглед) + **policies** (чернови без продукти) + **customers** (опционално, реални клиенти). Help/wizard: етап 0 = org prep (§4.1a).

### Help draft bullets

- Етап 0: подготовка на организацията преди продукт — роли, настройки, преглед на библиотека контроли, чернови на политики, по желание клиенти.
- Политиките остават draft докато няма продукт за review/approve — това е очаквано.
- Библиотеката контроли обикновено е готова от стартовия каталог; клиентът само преглежда.
- Product cards / Модули: червено = блокира готовност; оранжево = препоръчително да се довърши; зелено = попълнено OK; неутрално = опционално празно. Заглавието на продукта следва най-тежкия модулен статус.
- Scope assessment не е правно заключение — само operational suggestion + review.

### Wizard decisions

- Org prep (стъпка 0) е отделен блок **преди** Product Show timeline; в диаграмата включва controls / policies / optional customers (§4.1a таблица).
- Стъпка 1 (Product) Done — цветовата легенда на модулите е част от help за Products index.
- Стъпка 2 (Scope) Done — следва Classification.
- Стъпка 3 (Classification) Done — следва Versions.
- Стъпка 4 (Versions) Done — следва Support periods.
- Стъпка 5 (Support periods) Done — следва VCS / Integrations.
- Стъпка 6 (VCS / Integrations) Done — покрито при A_1 (GitHub, Jira, SARIF); следва Components / SBOM.
- Стъпка 7 (Components / SBOM) Done — следва Risks.
- Стъпка 8 (Risks) Done — следва Requirements.
- Стъпка 9 (Requirements) Done — следва Controls.
- Стъпка 10 (Controls) Done — следва Evidence.
- Стъпка 11 (Evidence) Done — следва Tasks.
- Стъпка 12 (Tasks) Done — следва Vulnerabilities.
- Стъпка 13 (Vulnerabilities) Done — следва Vulnerability reporting.
- Стъпка 14 (Vulnerability reporting) Done — следва Customers.
- Стъпка 15 (Customers) Done — следва Deployments.
- Стъпка 16 (Deployments) Done — следва Patch campaigns.
- Стъпка 17 (Patch campaigns) Done — следва Incidents.
- Стъпка 18 (Incidents) Done — следва SDL.
- Стъпка 19 (SDL) Done — следва USI.
- …

### P0/P1 triage queue

- …

---

## 13. Връзка с главния план

| Документ                                     | Роля                                                                    |
| -------------------------------------------- | ----------------------------------------------------------------------- |
| Този файл                                    | **Активен** план за вътрешни ръчни тестове + вход за help + Show wizard |
| [Phase2_E_…](Phase2_E_Cross_Phase_Polish.md) | Dev polish **complete**; сочи към вътрешно тестване                     |
| Phase 2_F (бъдещ)                            | SSO / billing / onboarding — **след** §11 exit                          |
| Optional 2.9                                 | Scanner depth — извън този план                                         |

**Защо отделен файл (не глава в Nachalen_Plan):** същият модел като Phase 2.x / 2_E — изпълним Active план с версии, exit criteria и работен log; главният план държи само pointer в §14.

---

## 14. История

| Версия | Дата       | Промяна                                                                                                   |
| ------ | ---------- | --------------------------------------------------------------------------------------------------------- |
| 1.12   | 2026-07-26 | Spine стъпка **19** (SDL) → **Done**                                                                      |
| 1.11   | 2026-07-26 | Spine стъпка **18** (Incidents) → **Done**                                                                |
| 1.10   | 2026-07-26 | Spine стъпка **17** (Patch campaigns) → **Done**                                                          |
| 1.9    | 2026-07-26 | Spine стъпка **16** (Deployments) → **Done**                                                              |
| 1.8    | 2026-07-26 | Spine стъпка **15** (Customers) → **Done**                                                                |
| 1.7    | 2026-07-26 | Spine стъпка **14** (Vulnerability reporting) → **Done**                                                  |
| 1.6    | 2026-07-26 | Spine стъпка **13** (Vulnerabilities) → **Done**                                                          |
| 1.5    | 2026-07-25 | Spine стъпка **12** (Tasks) → **Done**                                                                    |
| 1.4    | 2026-07-25 | Spine стъпка **11** (Evidence) → **Done**                                                                 |
| 1.3    | 2026-07-25 | Spine стъпка **10** (Controls) → **Done**                                                                 |
| 1.2    | 2026-07-25 | Spine стъпка **9** (Requirements) → **Done**                                                              |
| 1.1    | 2026-07-25 | Spine стъпка **8** (Risks) → **Done**                                                                     |
| 1.0    | 2026-07-25 | Spine стъпка **7** (Components / SBOM) → **Done**                                                         |
| 0.9    | 2026-07-25 | Spine стъпка **6** (VCS / Integrations) → **Done** (покрито при A_1: GitHub, Jira, SARIF)                 |
| 0.8    | 2026-07-25 | Spine стъпка **5** (Support periods) → **Done**                                                           |
| 0.7    | 2026-07-25 | Spine стъпка **4** (Versions) → **Done**                                                                  |
| 0.6    | 2026-07-25 | Spine стъпка **3** (Classification) → **Done**                                                            |
| 0.5    | 2026-07-25 | Spine стъпка **2** (CRA scope assessment) → **Done**                                                      |
| 0.4    | 2026-07-25 | Spine стъпка **1** (Product Create/Edit) → **Done**; module color coding + UI polish в сесия log          |
| 0.3    | 2026-07-25 | Етап **0** разширен: Customers (optional), Policies (draft), Controls library (review) — §4.1a + диаграма |
| 0.2    | 2026-07-25 | Spine стъпка **0** (Org/users/roles/settings) → **Done**; Status колона в §4.1; сесия log                 |
| 0.1    | 2026-07-24 | Skeleton Active — цели A/B/C, numbered spine, пътеки A–H, findings + wizard capture, exit before F        |
