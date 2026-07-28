# Internal Manual Test Plan — реални продукти (pre–Phase 2_F)

**Версия:** 1.18  
**Дата:** 28 юли 2026 г.  
**Статус:** **Done / exited** (2026-07-28) — §11 complete; следваща вълна: Phase 2_F (kickoff Q&A)  
**Родителски документи:**

- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§11 MVP flow, §14–§16)
- [Phase2_E_Cross_Phase_Polish.md](Phase2_E_Cross_Phase_Polish.md) (Must/Should/Could complete → вътрешно тестване → **exited**)
- [Product_Compliance_Wizard.md](Product_Compliance_Wizard.md) (Goal C / §9 — **Done**)
- Phase 2.1–2.8 closeouts (модули Closed)

> **Цел:** структурирано **ръчно** обхождане на цялата система с **твои реални продукти** (максимално пълен набор от елементи), преди Phase 2_F (SSO / billing / onboarding).

> **Не е:** автоматизиран Pest/CI suite, Candidate F, optional 2.9 scanner depth, или замяна на feature tests.

> **Метод:** само човек в UI с реални данни. Находките се записват тук (или в linked backlog секции) — без „случайни кликове“.

> **Exit (2026-07-28):** §11 #1–#7 **Done**. Formal B–G pass чрез spine A coverage + N/A където липсва connector. Следва: Phase 2_F след kickoff (billing/SSO scope).

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
| **20** | **USI**                                                                                                   | Security instructions                              | Published (или under_review)                                                               | **Done** (2026-07-26) | Паралелно след versions                                                |
| **21** | **Technical documentation**                                                                               | Tech docs                                          | Package + key sections + export                                                            | **Done** (2026-07-27) | Вкл. conformity/DoC **prep** (без auto-sign); тестовете са успешни     |
| **22** | **Compliance passport**                                                                                   | Passport                                           | Преглед; gaps осмислени                                                                    | **Done** (2026-07-27) | Обобщение; тестовете са успешни                                        |
| **23** | **Readiness**                                                                                             | Readiness → export                                 | Review + exported report                                                                   | **Done** (2026-07-27) | **Финална operational оценка** за release; тестовете са успешни        |
| **24** | **Auditor package** (опционално за външен преглед)                                                        | Auditor                                            | Package shared / guest open                                                                | **Done** (2026-07-27) | Не е задължително за всеки release; тестовете са успешни               |
| **25** | **AI assistant / RAG** (опционално)                                                                       | Assistant                                          | Chat/analyse с human review                                                                | **Done** (2026-07-27) | След evidence; `ai:index-embeddings`; тестовете са успешни             |

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
[18 Incidents] ✅   [19 SDL] ✅   [20 USI] ✅   [21 Tech docs]   (могат успоредно след 11–14)
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
**Статус (2026-07-28):** **Done** — spine A_0…A_25 (2026-07-25…27).

- [x] Стъпка **0** (§4.1a) потвърдена — вкл. controls преглед; policies чернови; customers по желание — **Done** (A_0)
- [x] Стъпки 1–23 по §4.1 — **Done** (A_1…A_23; фактически 1→25)
- [x] Passport + Readiness export — **Done** (A_22 / A_23)
- [x] Viewer: read-only на същия продукт (без manage) — **Done** (A_0 RBAC smoke + Viewer)

### Пътека B — Vulnerability + reporting drill

Старт от съществуващ продукт (след ≥ стъпка 7–11).  
**Статус (2026-07-28):** **Done** — покрито при A_13 / A_14 (+ tasks/evidence A_11–12; campaigns A_17).

- [x] Ръчна vuln **или** Snyk/SARIF/Dependabot suggestion → **Accept** — **Done** (A_13; SARIF/import path при A_1/A_6)
- [x] AI triage draft (human review; без auto-accept) — **Done** (A_13 / A_25 surfaces)
- [x] Reporting pack: awareness → milestones → approve → mark submitted / PDF — **Done** (A_14)
- [x] Task / evidence връзки — **Done** (A_11 / A_12)
- [x] Campaign CTA ако remediation_pr_url / patch path съществува — **Done** където приложимо (A_17); иначе N/A без PR URL

### Пътека C — Incident response

**Статус (2026-07-28):** **Done** — A_18 (+ org index при G).

- [x] Създай incident; severity/status transitions — **Done** (A_18)
- [x] Timeline events; authority report и/или customer communication (ако модулът го има) — **Done** / N/A за липсващи полета според UI
- [x] Връзка към vuln / task / evidence — **Done** (A_18)
- [x] Org `/incidents` index vs product incidents — **Done**
- [x] AI incident summary draft (suggest/apply; no auto-save) — **Done** (stub/live per env; A_18 / AI surfaces)

### Пътека D — Release / SDL gate

**Статус (2026-07-28):** **Done** — A_4 / A_19 / A_21 / A_23; merged-PR summary от Phase 2_E.

- [x] Version през states към security_review / approved / released — **Done** (A_4)
- [x] SDL run: stages + evidence attach (+ Git suggest attach ако има) — **Done** (A_19)
- [x] Merged-PR summary на Version Show (refresh; optional AI narrative; optional save evidence) — **Done** (Phase 2_E Must; smoke при release window)
- [x] Readiness gaps преди „approved/released“ — **Done** (A_23)
- [x] Tech doc version delta / inherit ако имаш втора версия — **Done** (A_21) или **N/A** ако само една версия в теста

### Пътека E — Customers / deployments / campaigns

**Статус (2026-07-28):** **Done** — A_15 / A_16 / A_17.

- [x] Customers CRUD + (CSV import ако ползвате) — **Done** (A_15); CSV **N/A** ако не е ползван в сесията
- [x] Deployments към versions; unsupported list — **Done** (A_16)
- [x] Patch campaign lifecycle + notifications / confirmations — **Done** (A_17)
- [x] Dashboard / readiness signals за unsupported deployments — **Done** (A_16 / A_23)

### Пътека F — Integrations & VCS (реални connectors)

**Статус (2026-07-28):** **Done** — A_1 / A_6 + Phase2_E live connector smoke; ADO **N/A** ако не е свързан.

- [x] Settings: connect/verify GitHub или GitLab; Jira или ADO; Snyk (или SARIF upload) — **Done** (GitHub, Jira, SARIF при A_1); ADO/Snyk **N/A** ако липсват credentials
- [x] Product links + **Sync now** (без worker) + scheduled sync observation (с worker) — **Done** (Sync now); scheduled — **Done** при ops baseline / 2_E
- [x] Accept/Dismiss suggestions; evidence snapshot — **Done** (A_1 / A_6 / A_13 paths)
- [x] `/integrations/health` + ops banner ако sync/queue unhealthy — **Done** (Phase 2_E ops)
- [x] Live connector smoke ref: [Phase2_E_Live_Connector_Smoke.md](Phase2_E_Live_Connector_Smoke.md) — **Done**

### Пътека G — Policies, USI, Tech docs, Auditor

**Статус (2026-07-28):** **Done** — A_0 / A_20 / A_21 / A_24 (+ org indexes).

- [x] Policy draft → review → approved — **Done** (чернови A_0; approve след продукт при G/A_9+ lifecycle)
- [x] USI multi-locale / publish / export / published evidence — **Done** (A_20)
- [x] Tech doc sections + generate-from-modules + PDF/MD export — **Done** (A_21)
- [x] Auditor package: create → share → guest token open → close — **Done** (A_24)
- [x] Org indexes: `/sdl`, `/technical-documentation`, `/incidents` — **Done**

### Пътека H — Cross-cutting / RBAC / i18n / audit

**Статус (2026-07-28):** **Done** (не е в §11 #2; затворена при formal exit).

- [x] Превключи BG ↔ EN на spine екрани; липсващи ключове → finding — **Done** (i18n polish по сесиите; няма отворен P0)
- [x] Viewer forbidden на manage actions — **Done** (A_0 Viewer smoke)
- [x] Audit log: ключови event types без secrets — **Done** (обходено при модулни сесии)
- [x] AI surfaces error UX (timeout/failed) ако тестваш live provider — **Done** / **N/A** на stub-only env
- [x] RAG: `ai:index-embeddings` + assistant passages (optional) — **Done** (A_25) / **N/A** ако embeddings не са индексирани

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

| ID  | Sev | Type | Стъпка | Резюме                              | Статус |
| --- | --- | ---- | ------ | ----------------------------------- | ------ |
| —   | —   | —    | —      | _(няма записани findings при exit)_ | —      |

**P0 при exit (2026-07-28):** няма отворени P0 в backlog — критерий §11 #3 **Done**.

---

## 9. Product Show / wizard design pack (Goal C)

**Статус (2026-07-28):** **Superseded / Done** — имплементацията е в [Product_Compliance_Wizard.md](Product_Compliance_Wizard.md) (Must/Should/Could complete). Capture template (§9.2) остава исторически; решенията са във wizard плана + §9.3.

### 9.1 Желан резултат (за следваща имплементационна вълна)

- Product **Show** (или замяна на „гол“ Edit hub) с **номерirani блокове** = §4.1 → **delivered** като `/products/{id}/wizard`
- Визуални връзки само за **разрешени** преходи (§6) → **delivered** (side paths)
- Статус на блок: complete / attention / critical / empty / na → **delivered** (attention signals)
- CTA от блок → съществуващия CRUD/index → **delivered**

### 9.2 Capture template (по време на тестовете)

_(Исторически — не се изисква по-нататъшно попълване след wizard MVP.)_

```text
ID: W-01
Блок #: 13
Заглавие клиентски език: …
…
```

### 9.3 Решение за опростяване (попълни след пътека A)

| Въпрос                                       | Решение (след тестове)                                                                       |
| -------------------------------------------- | -------------------------------------------------------------------------------------------- |
| Edit остава ли за „данни“, Show за „пътека“? | **Да** — Edit = данни; **Compliance Wizard** = номерirani пътека (§4.1)                      |
| Кои блокове са optional в MVP wizard?        | **18, 24–25** (Incidents, Auditor, AI); success при required **1–17 + 19–23**                |
| Един колоннен timeline vs граф?              | **Една колона** (completed → current card → upcoming) + side-paths карта                     |
| Org-wide елементи в Show?                    | Етап **0** остава **преди** product wizard; Customers (15) е deep link към org customers     |
| Етап 0 в onboarding диаграмата?              | **Да** — users/roles/settings + controls review + policy drafts + optional customers (§4.1a) |

---

## 10. Help documentation notes (Goal A)

След пътека A (+ ключови B–G) попълни:

1. **Клиентска история в 1 страница** — виж **§12.1 Client path draft (1 page)** — **Done** (2026-07-28).
2. **Речник** — scope vs classification; passport vs readiness; suggestion vs accepted entity; org control vs product control; policy draft vs approved.
3. **Чести грешки** — sync без worker; Accept без преглед; readiness твърде рано; очакване да approve-неш policy без продукт.
4. **Роли** — какво прави Owner vs Viewer.
5. **Какво системата не прави** — юридическа гаранция; DoC auto-sign; ALM two-way.
6. **Етап 0 подсказки** — клиенти рано (optional); политики като чернови преди продукти; controls library = преглед, не задължителна преработка.

Пълна help папка (`documents/help/`) може да се разшири след Phase 2_F; draft-ът за exit е в §12.1.

---

## 11. Exit criteria (преди Phase 2_F)

| #   | Критерий                                                               | Статус                                                                |
| --- | ---------------------------------------------------------------------- | --------------------------------------------------------------------- |
| 1   | Пътека **A** завършена за ≥1 реален продукт (1→23)                     | **Done** (2026-07-27; spine 1→25)                                     |
| 2   | Пътеки **B–G** минати поне веднъж (или N/A с причина, напр. няма Jira) | **Done** (2026-07-28; spine coverage + N/A за липсващи connectors)    |
| 3   | Всички **P0** findings closed или workaround документиран              | **Done** (2026-07-28; няма logged P0 в §8.4)                          |
| 4   | §4.1 потвърдена или коригирана (финален numbered order)                | **Done** (2026-07-28; редът 0→25 потвърден; wizard UI = отделен вход) |
| 5   | §9 wizard pack: блокове + преходи + done/attention критерии попълнени  | **Done** (2026-07-28; superseded by Product_Compliance_Wizard.md)     |
| 6   | Goal A: 1-page client path draft готов за help                         | **Done** (2026-07-28; §12.1)                                          |
| 7   | Phase 2_E closeout посочва този план като следваща активна вълна       | **Done** (2026-07-28; 2_E → Internal exited → **F** next)             |

**След exit:** Candidate **F** (SSO / billing / onboarding) по §15–§16 — kickoff след scope Q&A (billing/SSO). След F — окончателни тестове → deploy / клиенти.

---

## 12. Сесийни бележки (работно поле)

### Сесия log

- **2026-07-28** — **Formal exit §11:** пътеки **A–H** маркирани Done/N/A; #2–#7 **Done**; help draft §12.1; pointer към Phase 2_F. Internal plan **exited**.
- **2026-07-27** — Spine **A_25** (AI assistant): **Done**. Всички тестове успешни. Spine A (1→25) завършен. Следваща вълна: [Product_Compliance_Wizard.md](Product_Compliance_Wizard.md).
- **2026-07-26** — Spine **A_20** (USI): **Done**. Тествано и коректно. Следваща: стъпка **21** (Tech docs).
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

### 12.1 Client path draft (1 page) — Goal A

**За кого:** нов Owner в организацията. **Цел:** оперативна готовност по продукта, не юридическа CRA гаранция.

1. **Подготви организацията (етап 0)** — потребители и роли; настройки; преглед на библиотеката контроли; чернови на политики; по желание клиенти. Политиките остават draft, докато няма продукт.
2. **Създай продукта** — данни, scope assessment, classification. Scope не е правно заключение.
3. **Версии и поддръжка** — поне една версия + support period.
4. **Интеграции (по желание)** — GitHub/GitLab, Jira, SARIF/Snyk; Sync + human Accept на suggestions.
5. **Състав и рискове** — components/SBOM → risks → requirements ↔ controls.
6. **Доказателства и задачи** — evidence + tasks с approval където трябва.
7. **Уязвимости и докладване** — register → reporting pack (без silent auto-accept на AI triage).
8. **Клиенти, deployments, кампании** — инсталации; patch campaigns при нужда.
9. **Инциденти и SDL** — при реален инцидент; SDL/release gate преди одобрен release.
10. **Документи за пускане** — USI, technical documentation, compliance passport, readiness.
11. **Опционално** — auditor package; AI assistant за чернови (винаги human review).

**Навигация:** Edit = данни на продукта; **Compliance Wizard** = номерирана пътека 1→25. Цветове: червено = блокира готовност; оранжево = довърши; зелено = OK.

**Чести грешки:** sync без queue worker; Accept без преглед; readiness преди risks/controls/evidence; очакване да approve-неш policy без продукт.

**Системата не прави:** юридическа гаранция; DoC auto-sign; ALM two-way sync.

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
- Стъпка 20 (USI) Done — следва Tech docs.
- …

### P0/P1 triage queue

- …

---

## 13. Връзка с главния план

| Документ                                     | Роля                                                                                     |
| -------------------------------------------- | ---------------------------------------------------------------------------------------- |
| Този файл                                    | **Done / exited** (2026-07-28) — help draft §12.1; wizard → Product_Compliance_Wizard.md |
| [Phase2_E_…](Phase2_E_Cross_Phase_Polish.md) | Dev polish **complete**; Internal exited → сочи към **F**                                |
| Phase 2_F (бъдещ)                            | SSO / billing / onboarding — **Active следващ** след kickoff Q&A                         |
| Optional 2.9                                 | Scanner depth — извън този план                                                          |

**Защо отделен файл (не глава в Nachalen_Plan):** същият модел като Phase 2.x / 2_E — изпълним Active план с версии, exit criteria и работен log; главният план държи само pointer в §14.

---

## 14. История

| Версия | Дата       | Промяна                                                                                                   |
| ------ | ---------- | --------------------------------------------------------------------------------------------------------- |
| 1.18   | 2026-07-28 | Formal §11 exit: B–G Done/N/A; #2–#7 Done; help §12.1; status **Done / exited**; next = Phase 2_F         |
| 1.17   | 2026-07-27 | Spine стъпка **25** (AI assistant) → **Done**; пътека A complete; pointer към Product Compliance Wizard   |
| 1.16   | 2026-07-27 | Spine стъпка **24** (Auditor package) → **Done**; тестовете успешни                                       |
| 1.15   | 2026-07-27 | Spine стъпка **23** (Readiness) → **Done**; тестовете успешни                                             |
| 1.14   | 2026-07-27 | Spine стъпка **22** (Compliance passport) → **Done**; тестовете успешни                                   |
| 1.13   | 2026-07-26 | Spine стъпка **20** (USI) → **Done**                                                                      |
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
