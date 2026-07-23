# Phase 2.7 — Technical Documentation

**Версия:** 0.6  
**Дата:** 23 юли 2026 г.  
**Статус:** Active — Must 1–5 Done  
**Родителски документи:**

- [CRA_Compliance_Workspace_Nachalen_Plan.md](CRA_Compliance_Workspace_Nachalen_Plan.md) (§5.12 Technical Documentation Workspace, §5.13 Evidence, §5.17 USI)
- [Phase2_6_Release_Closeout.md](Phase2_6_Release_Closeout.md) (Closed — Phase 2.6 exited; §8 кандидат C)
- [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) (Closed — USI §5.17; customer-facing subset)
- [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) (Closed — light tech-doc delta flag на SDL)

> **Цел на вълната:** product-/version-scoped **Technical Documentation workspace** (§5.12) — структурирани секции, генерирани/свързани към съществуващи модулни данни, с **version delta** (наследяване, маркирани промени, stale evidence) — без да замества USI, SDL board или Passport readiness outline.

> **Ред на имплементация (предложен):** schema + section model → CRUD / editor UI → generate-from-modules hooks → export package → version delta → readiness/tests → Should/Could.

> **Граница с вече доставеното:** Readiness/Passport имат **thin outline** (identification / risks / SBOM / support / evidence / versions). USI (§5.17) покрива customer-facing instructions. SDL Could 18 има само „tech-doc delta reviewed“ flag. Phase 2.7 изгражда **workspace + delta**, не дублира USI секции 1:1.

---

## 1. Цел

Да може производителят да:

- поддържа **техническа документация** като структуриран пакет за продукт / версия;
- покрива секциите от §5.12 (description, architecture, risks, SBOM, vuln handling, support, USI pointer, conformity path…);
- **наследява** документация при нова версия и вижда **delta** (променени секции, нови рискове, stale evidence);
- експортира към **PDF / Markdown / release package** за conformity assessment подготовка;
- свързва секции с **evidence / controls / requirements / SDL / USI** където има смисъл;
- ползва AI само като **draft helper** с human review (Could).

---

## 2. Scope (in)

| Възможност            | Описание                                                                  |
| --------------------- | ------------------------------------------------------------------------- |
| Tech-doc package      | Product-scoped (optional version pin) document package                    |
| Section model         | Fixed section keys от §5.12 + body / generated snapshot / override        |
| Lifecycle             | `draft` → `under_review` → `published` → `retired` (уточнява се)          |
| Generate-from-modules | Pull facts от product, risks, SBOM, support, evidence, versions, SDL, USI |
| Version delta         | Inherit prior package; mark changed sections; stale evidence hints        |
| Export                | PDF / Markdown (+ optional ZIP release bundle)                            |
| Evidence / controls   | Link or reference existing records (не нов evidence store)                |
| Readiness / passport  | Deeper gap than thin outline                                              |
| Tasks / audit         | Review / publish tasks; create/update/publish/export audit                |
| UI                    | Product module + server-side DataTable                                    |

### Section keys (§5.12 — draft)

```text
product_description
intended_purpose
architecture
attack_surface
cybersecurity_risk_assessment
essential_requirements_matrix
design_development_controls
component_inventory
sbom
vulnerability_handling_process
update_mechanism
security_tests
support_period
user_security_instructions   # pointer / embed summary → published USI
conformity_assessment_path
declaration_information
product_identification
release_history
```

> Уточнение: някои секции са **generated** (SBOM, risks summary), други **authored** (architecture narrative). Schema трябва да отличава `source: generated | authored | linked`.

---

## 3. Scope (out) — изрично

- Заместване на USI workspace (§5.17) — USI остава отделен published artifact
- Заместване на SDL board (§5.14) — SDL остава operational lifecycle
- Автоматично правно „conformity certificate“ / DoC auto-sign
- Full GRC document management / DMS
- Real-time collaboration (Google Docs clone)
- Scanner / pen-test engine
- Integration wave 2 (Jira / Snyk / Trivy) — кандидат D
- Billing / SSO-специфични tech-doc tiers

---

## 4. Данни (чернова)

```text
technical_documentation_packages
  id, organization_id, product_id, product_version_id?
  title, status, locale, version_label
  supersedes_id?                 # prior package for delta
  published_at, published_by?
  notes, timestamps

technical_documentation_sections
  id, package_id, section_key, sort_order
  source (generated|authored|linked)
  body_markdown?
  generated_payload_json?        # snapshot of module facts
  is_applicable, override_reason?
  changed_since_parent?          # delta flag
  timestamps

technical_documentation_section_evidence (optional M2M)
technical_documentation_section_controls (optional M2M)
```

Tasks: `subject_type: technical_documentation_package` (или еквивалент).

---

## 5. UI / UX (чернова)

- Product module card → Tech docs index (DataTable)
- Create: title, locale, optional version pin, „inherit from previous published“
- Edit: section accordion / tabs; generated sections refreshable; authored Markdown
- Delta view: side-by-side or change list vs `supersedes`
- Export actions: Markdown / PDF (+ release ZIP Could)
- Links out: USI published, SDL approved run, passport, readiness

---

## 6. API / routes (чернова)

```text
GET    /products/{product}/technical-documentation
POST   /products/{product}/technical-documentation
GET    /products/{product}/technical-documentation/{package}/edit
PUT    /products/{product}/technical-documentation/{package}
DELETE /products/{product}/technical-documentation/{package}
POST   /products/{product}/technical-documentation/{package}/refresh-generated
POST   /products/{product}/technical-documentation/{package}/submit-review
POST   /products/{product}/technical-documentation/{package}/publish
GET    /products/{product}/technical-documentation/{package}/export/{format}
GET    /products/{product}/technical-documentation/{package}/delta
GET    /internal-api/products/{product}/technical-documentation
```

---

## 7. Имплементационен ред (Must → Should → Could)

### Must

1. ~~Migrations + models + enums (package, sections, status, section keys)~~ **Done**
2. ~~CRUD + Index DataTable (product-scoped)~~ **Done**
3. ~~Section editor UI (authored + generated placeholders)~~ **Done** (2026-07-23)
4. ~~Generate-from-modules for core sections (identification, risks, SBOM, support, versions)~~ **Done** (2026-07-23)
5. ~~Publish lifecycle + audit~~ **Done** (2026-07-23)
6. i18n EN/BG + feature tests (CRUD + viewer forbidden manage)

### Should

7. Version-pinned packages + inherit from previous published
8. Delta UI (changed sections / stale evidence hints)
9. PDF/Markdown export
10. Link published USI + optional SDL run reference
11. Readiness gap upgrade (beyond thin outline)
12. Dedicated `technical_documentation.*` permissions + product nav card

### Could

13. Org-level cross-product tech-doc index
14. AI draft for authored sections (human review)
15. Release ZIP export (PDF + Markdown + linked USI README)
16. Auto-mark stale evidence when freshness expires
17. Dependency / SBOM compare between versions in delta report
18. Conformity assessment path checklist + DoC field pack (manual)

---

## 8. MVP slice за 2.7 (резюме)

**Must** — package + sections + generate-from-modules + publish + tests.

**Should** — version inherit/delta, export, USI/SDL links, readiness, RBAC.

**Could** — org index, AI drafts, ZIP, stale auto-mark, SBOM compare, DoC pack.

---

## 9. Acceptance criteria (Phase 2.7 done) — чернова

1. Owner създава tech-doc package за продукт (опционално version-pinned).
2. Core §5.12 секции са налични; generated секции се попълват от модулни данни.
3. Publish е одитируем; viewer вижда, но не manage-ва.
4. При нова версия може да се наследи предишен package и да се видят променени секции.
5. Export PDF/Markdown е наличен за release / assessment package.
6. USI и SDL **не** се заместват — само link / summary.
7. Няма автоматичен правен сертификат / DoC auto-sign в scope.

---

## 10. Рискове и mitigations

| Риск                       | Mitigation                                                                                |
| -------------------------- | ----------------------------------------------------------------------------------------- |
| Дублиране с USI / Passport | Clear labels: tech-doc package vs USI vs readiness outline                                |
| Generated content stale    | Explicit „Refresh generated“ + timestamps; no silent overwrite authored                   |
| Scope creep към full DMS   | Fixed section keys; no free-form document tree                                            |
| Delta engine твърде тежък  | Start with section-level change flags + evidence freshness; defer deep SBOM diff to Could |

---

## 11. Зависимости и ред

```text
Phase 2.6 Secure Development Lifecycle — Closed 2026-07-23
    ↓
Phase 2.7 Technical Documentation (този документ)
    ↓
(по-късно) Integration wave 2 — TBD
```

Reuse:

- Product / versions / risks / SBOM / support / evidence / controls / requirements;
- USI published packages (§5.17);
- SDL light docs flag + approved runs (§5.14);
- Passport / readiness outline patterns;
- Export (DomPDF) + AI draft patterns от 2.3–2.6;
- DataTable, AuditLogger, shadcn-vue conventions.

---

## 12. История

| Версия | Дата       | Промяна                                                                          |
| ------ | ---------- | -------------------------------------------------------------------------------- |
| 0.6    | 2026-07-23 | Must 5 Done — draft→review→publish→retire + review task + audit                  |
| 0.5    | 2026-07-23 | Must 4 Done — generate-from-modules + Refresh generated (payload envelope)       |
| 0.4    | 2026-07-23 | Must 3 Done — section editor (authored Markdown + generated/linked placeholders) |
| 0.3    | 2026-07-23 | Must 2 Done — product-scoped CRUD + Index DataTable + nav card                   |
| 0.2    | 2026-07-23 | Must 1 Done — packages/sections schema + enums + model tests                     |
| 0.1    | 2026-07-23 | Skeleton след Phase 2.6 closeout — §5.12 Technical Documentation (кандидат C)    |
