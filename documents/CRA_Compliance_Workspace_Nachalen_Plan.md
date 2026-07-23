# CRA Compliance Workspace

## Начален продуктов и технически план за разработка

**Версия на документа:** 1.6  
**Дата:** 23 юли 2026 г.  
**Статус:** MVP 0.1 exited → Phase 2.1–2.5 Closed → Phase 2.6 Active  
**Предназначение:** Работен план за проучване, проектиране и разработване на SaaS/self-hosted система за управление на продуктовата сигурност и подготовката за съответствие с Cyber Resilience Act (CRA).

> **Важно:** Системата не трябва да обещава автоматично или юридически гарантирано съответствие. Тя трябва да организира, автоматизира и документира процесите, решенията и доказателствата, необходими за CRA readiness и conformity assessment.

---

# 1. Продуктова концепция

## CRA Compliance Workspace

Централизирана система, която помага на малки и средни производители на софтуер да управляват:

- продуктовото си портфолио;
- версии и support periods;
- cybersecurity risk assessments;
- CRA изисквания и приложими controls;
- техническа документация;
- SBOM и софтуерни зависимости;
- уязвимости;
- security incidents;
- corrective actions;
- release evidence;
- user security instructions;
- conformity evidence;
- задачи, срокове и approvals;
- засегнати клиенти и deployments;
- регулаторни reporting workflows;
- пълна история на решенията и промените.

Основната цел е разпиляната информация да бъде превърната в проследима верига:

```text
Регулаторно изискване
    ↓
Риск
    ↓
Контрол
    ↓
Доказателство
    ↓
Продукт и версия
    ↓
Тест
    ↓
Одобрение
    ↓
Публикуване
    ↓
Поддръжка
    ↓
Уязвимост или инцидент
    ↓
Коригираща версия
```

Продуктът трябва да бъде не просто електронна папка, а **операционна система за сигурния жизнен цикъл на софтуерен продукт**.

---

# 2. Регулаторен контекст и времеви хоризонт

Cyber Resilience Act е Регламент (ЕС) 2024/2847 и обхваща хардуерни и софтуерни продукти с цифрови елементи, които се предоставят на пазара на Европейския съюз.

Основните изисквания започват да се прилагат от:

- **11 декември 2027 г.** – основната част от задълженията;
- **11 септември 2026 г.** – задълженията за докладване на активно експлоатирани уязвимости и тежки инциденти.

За reporting workflows трябва да се предвидят:

- early warning до 24 часа от awareness;
- по-пълно уведомление до 72 часа;
- финален доклад за активно експлоатирана уязвимост до 14 дни след налична коригираща мярка;
- финален доклад за тежък инцидент до един месец.

Точната приложимост за конкретен продукт трябва да бъде потвърждавана чрез правна или специализирана compliance оценка. Системата може да предлага структурирана предварителна оценка, но не и окончателно юридическо заключение.

---

# 3. Целеви клиенти

## Първоначален целеви сегмент

Малки европейски производители на комерсиален софтуер, например:

- разработчици на WooCommerce, PrestaShop, Magento и OpenCart модули;
- ERP и CRM производители;
- desktop приложения;
- middleware и API продукти;
- B2B SaaS с локални агенти или инсталационни компоненти;
- POS и складов софтуер;
- лабораторни и специализирани бизнес приложения;
- индустриални desktop решения;
- екипи от приблизително 2 до 30 разработчици;
- фирми без отделен product-security или compliance отдел.

## Защо този сегмент

Тези компании:

- имат реален продуктов и security риск;
- обикновено нямат enterprise GRC платформа;
- работят с множество версии и клиенти;
- пазят доказателствата в Git, Word, Excel, имейл и ticket системи;
- могат да имат добри практики, но трудно ги доказват систематично;
- имат нужда от практически инструмент, а не от консултантски каталог с 800 полета.

## Първи вътрешен клиент

Собствените продукти на разработчика са естествен първи портфолио за системата:

- платежни модули;
- installment модули;
- WooCommerce и PrestaShop разширения;
- desktop приложения;
- SaaS решения;
- версии за различни PHP и CMS поколения;
- shared libraries;
- release packages;
- документация;
- поддържани и неподдържани версии.

---

# 4. Граници на продукта

## Системата трябва да управлява

- продуктово портфолио;
- версии и release history;
- support periods;
- продуктова класификация;
- CRA requirements;
- controls;
- product cybersecurity risks;
- SBOM и dependency inventory;
- vulnerabilities;
- incidents;
- corrective actions;
- technical documentation;
- доказателства;
- approvals;
- customer deployments;
- reporting deadlines;
- audit trail.

## Системата не трябва да се опитва да бъде

- source-code repository;
- пълноценен issue tracker;
- vulnerability scanner;
- penetration-testing engine;
- SIEM;
- антивирусна система;
- юридически консултант;
- notified body;
- заместител на GitHub, GitLab, Jira или Azure DevOps.

Тя трябва да бъде **оркестратор и evidence layer** над съществуващите инструменти.

---

# 5. Основни модули

## 5.1 Organization Workspace

Основна структура:

```text
Organization
├── Business units
├── Teams
├── Users
├── Products
├── Components
├── Repositories
├── Suppliers
├── Customers
└── Policies
```

### Основни възможности

- multi-tenant организации;
- роли и права;
- продуктови екипи;
- security отговорници;
- compliance reviewers;
- release approvers;
- външни консултанти;
- auditor read-only достъп;
- immutable activity log.

### Примерни роли

- Organization Owner;
- Product Owner;
- Security Owner;
- Developer;
- Compliance Reviewer;
- Release Approver;
- Auditor;
- External Consultant;
- Read-only User.

---

## 5.2 Product Register

Всеки продукт трябва да съдържа:

- име;
- продуктова линия;
- описание;
- предназначение;
- производител;
- търговска марка;
- тип продукт;
- пазари и държави;
- канали за разпространение;
- модел на лицензиране;
- дали е платен, безплатен или monetised indirectly;
- remote data processing;
- network connectivity;
- езици и технологии;
- поддържани операционни системи;
- CMS или runtime зависимости;
- deployment model;
- support period;
- end-of-support policy;
- product owner;
- security contact;
- repositories;
- клиенти и installations;
- classification status.

### Onboarding wizard

1. Софтуерен или хардуерен продукт ли е?
2. Предоставя ли се в рамките на търговска дейност?
3. Има ли логическа или физическа връзка с устройство или мрежа?
4. Предлага ли се самостоятелно?
5. Продава ли се под името или марката на организацията?
6. Има ли remote processing, без което продуктът не функционира?
7. Попада ли под друга секторна регулация?
8. Компонент ли е на друг продукт?
9. Представлява ли free and open-source software?
10. Има ли съществена модификация на чужд продукт?
11. Кой носи ролята на manufacturer, importer или distributor?
12. Предлага ли се на пазара на Европейския съюз?

### Резултат от предварителната оценка

- Likely in scope;
- Potentially excluded;
- Further legal review required;
- Insufficient information;
- Out of scope based on current data.

Резултатът трябва да съдържа rationale, reviewer и дата.

---

## 5.3 Product Classification

Поддържани категории:

- General product;
- Important product, Class I;
- Important product, Class II;
- Critical product;
- Unclassified;
- Classification under review;
- Excluded;
- Subject to sector-specific legislation.

Към класификацията трябва да има:

- въпросник;
- rationale;
- използвана версия на regulatory content;
- supporting evidence;
- reviewer;
- approval;
- дата;
- следваща дата за преглед;
- change history.

Класификацията не трябва да бъде обикновен dropdown без обяснение.

---

## 5.4 Product Versions и Releases

Примерна структура:

```text
Product
├── 1.8.4
├── 1.9.0
├── 2.0.0
└── 2.1.0-beta
```

### Данни за версия

- version number;
- release date;
- support status;
- security support deadline;
- Git commit или tag;
- build identifier;
- artifact hash;
- changelog;
- dependencies;
- SBOM;
- known vulnerabilities;
- security tests;
- risk assessment revision;
- release approval;
- deployment package;
- user documentation;
- affected customers;
- previous version;
- superseding version.

### Release states

- Draft;
- Development;
- Security review;
- Release candidate;
- Approved;
- Released;
- Deprecated;
- End of support;
- Withdrawn.

### Release readiness gate

Преди release:

- risk assessment е актуализиран;
- SBOM е генериран;
- критичните dependencies са прегледани;
- known vulnerabilities са оценени;
- security tests са приложени;
- user instructions са актуализирани;
- support period е потвърден;
- changelog е генериран;
- release artifact има hash;
- release approver е одобрил версията.

---

## 5.5 CRA Requirements Matrix

Регулаторните изисквания трябва да бъдат структурирани записи, а не PDF отметки.

### Структура

```text
Requirement
├── Regulatory source
├── Article / Annex reference
├── Requirement text
├── Plain-language explanation
├── Applicability conditions
├── Suggested controls
├── Required evidence
├── Product applicability
├── Status
├── Owner
└── Review history
```

### Status модел

- Not assessed;
- Applicable;
- Not applicable;
- Partially implemented;
- Implemented;
- Verified;
- Non-conformity;
- Exception approved.

### Пример

```text
Requirement:
Продуктът не трябва да се предоставя с известни експлоатируеми уязвимости.

Control:
Dependencies се сканират преди production release.

Evidence:
- SBOM;
- dependency scan report;
- security review;
- accepted-risk decision.

Owner:
Product Security Owner.
```

---

## 5.6 Controls Library

Control-ът описва как организацията изпълнява дадено изискване.

### Примерни controls

- dependency scanning before release;
- mandatory peer review;
- secrets scanning;
- signed release packages;
- secure update mechanism;
- vulnerability disclosure channel;
- supported-version inventory;
- security regression testing;
- backup and restore testing;
- prohibition of default credentials;
- release approval workflow;
- incident escalation;
- cryptographic key rotation;
- secure logging;
- input validation;
- access-control review.

### Данни за control

- име;
- описание;
- owner;
- implementation guidance;
- automation level;
- frequency;
- linked requirements;
- linked products;
- required evidence;
- test procedure;
- effectiveness status;
- exceptions.

---

## 5.7 Product Cybersecurity Risk Assessment

### Обекти на оценка

- продукт;
- версия;
- компонент;
- feature;
- deployment model;
- integration;
- update mechanism;
- authentication mechanism;
- data processing function.

### Risk структура

```text
Asset
Threat
Vulnerability or weakness
Attack scenario
Likelihood
Impact
Initial risk
Existing controls
Residual risk
Treatment
Owner
Deadline
Evidence
Approval
```

### Примерни категории

- unauthorised access;
- privilege escalation;
- data exposure;
- insecure communication;
- broken authentication;
- injection;
- dependency compromise;
- update mechanism compromise;
- supply-chain attack;
- insufficient logging;
- denial of service;
- insecure defaults;
- secrets exposure;
- cryptographic weakness;
- tampering;
- insecure configuration;
- unsupported component.

### Връзки

Всеки риск може да бъде свързан с:

- CRA requirement;
- source-code repository;
- feature;
- release;
- dependency;
- vulnerability;
- test;
- corrective action;
- deployment;
- customer.

---

## 5.8 SBOM и Component Management

### Component inventory

```text
Product
├── First-party components
├── Third-party commercial components
├── Open-source packages
├── Operating-system dependencies
├── Runtime dependencies
├── Build dependencies
└── External services
```

### Полета за компонент

- име;
- supplier;
- package ecosystem;
- version;
- licence;
- source;
- hash;
- direct или transitive dependency;
- usage context;
- supported status;
- known vulnerabilities;
- fixed или replacement version;
- affected releases;
- evidence source.

### Формати и източници

Първоначално:

- CycloneDX JSON;
- SPDX JSON;
- Composer;
- npm;
- NuGet;
- Maven;
- Python requirements;
- container manifests.

Практични първи входове:

- `composer.lock`;
- `package-lock.json`;
- WordPress plugin ZIP;
- PrestaShop module ZIP;
- OpenCart extension ZIP;
- Magento `composer.json`;
- .NET NuGet packages;
- standalone desktop release package.

Не е необходимо системата сама да бъде scanner. Тя може да интегрира резултати от външни инструменти.

---

## 5.9 Vulnerability Management

### Lifecycle

```text
Reported
    ↓
Triage
    ↓
Confirmed / Rejected / Duplicate
    ↓
Affected products identified
    ↓
Severity assessed
    ↓
Exploitation status checked
    ↓
Fix planned
    ↓
Patch developed
    ↓
Patch verified
    ↓
Advisory prepared
    ↓
Customers notified
    ↓
Released
    ↓
Closed
```

### Основни полета

- internal ID;
- CVE ID;
- advisory URL;
- discovery source;
- discovery timestamp;
- awareness timestamp;
- reporter;
- affected component;
- affected products;
- affected versions;
- fixed versions;
- CVSS;
- business severity;
- exploitation status;
- exploit evidence;
- public/private status;
- workaround;
- corrective action;
- owner;
- reporting deadlines;
- notification history.

### Източници

- internal discovery;
- customer report;
- researcher report;
- dependency scanner;
- vendor advisory;
- penetration test;
- CVE feed;
- security mailing list;
- incident investigation.

### Deadline engine

Системата трябва автоматично да следи:

```text
Awareness timestamp
+ 24 часа
+ 72 часа
+ final report deadline
```

Задължителни функции:

- countdown;
- escalation;
- primary responsible person;
- substitute responsible person;
- mandatory field validation;
- approval workflow;
- audit history;
- submission evidence.

---

## 5.10 Security Incident Management

Vulnerability и incident трябва да бъдат отделни обекти.

### Incident данни

- product;
- affected versions;
- affected customers;
- actual start time;
- detection time;
- awareness time;
- classification time;
- severity;
- confidentiality impact;
- integrity impact;
- availability impact;
- attack vector;
- root cause;
- linked vulnerability;
- corrective measures;
- customer communications;
- authority reports;
- lessons learned;
- closure approval.

### Timeline

```text
09:10 First anomalous activity
09:32 Customer report
09:40 Security team awareness
10:05 Incident classified
11:20 Affected versions confirmed
13:15 Temporary mitigation available
17:45 Early warning submitted
```

Трябва да се пазят отделно:

- кога събитието е започнало;
- кога е открито;
- кога организацията е станала aware;
- кога е класифицирано;
- кога е докладвано.

---

## 5.11 Reporting Workspace

Първоначално системата не трябва да обещава автоматично подаване към ENISA Single Reporting Platform.

### MVP

- reporting readiness wizard;
- задължителни полета;
- deadline management;
- draft generation;
- internal approval;
- export;
- manual submission record;
- доказателство кой, кога и какво е подал.

### По-късно

- SRP API integration;
- status synchronisation;
- submission receipts;
- follow-up requests;
- updated report submission.

---

## 5.12 Technical Documentation Workspace

Документацията трябва да се генерира от структурирани данни.

### Възможни секции

- product description;
- intended purpose;
- architecture;
- attack surface;
- cybersecurity risk assessment;
- essential requirements matrix;
- design and development controls;
- component inventory;
- SBOM;
- vulnerability-handling process;
- update mechanism;
- security tests;
- support period;
- user security instructions;
- conformity assessment path;
- declaration information;
- product identification;
- release history.

### Version delta

При нова версия системата трябва да може да:

- наследи документацията;
- маркира променените секции;
- сравни dependencies;
- идентифицира нови рискове;
- прегледа старите evidence записи;
- маркира stale доказателства;
- генерира delta report.

---

## 5.13 Evidence Management

### Видове evidence

- документ;
- screenshot;
- test report;
- Git commit;
- pull request;
- CI result;
- SBOM;
- vulnerability scan;
- penetration-test report;
- architecture diagram;
- policy;
- training record;
- approval;
- release artifact;
- hash;
- customer notification;
- submission receipt.

### Полета

- тип;
- източник;
- собственик;
- продукт;
- версия;
- requirement;
- control;
- дата;
- validity period;
- review date;
- confidentiality classification;
- checksum;
- immutable original;
- superseding evidence;
- reviewer.

### Freshness status

- Current;
- Review due;
- Expired;
- Superseded;
- Invalid;
- Missing.

---

## 5.14 Secure Development Lifecycle

### Примерен workflow

```text
Requirement
→ Threat review
→ Design
→ Development
→ Code review
→ Dependency scan
→ Security test
→ Release approval
→ Publication
→ Monitoring
→ Vulnerability handling
```

### Минимални capabilities

- security requirements към feature;
- threat considerations;
- secure coding checklist;
- peer review evidence;
- automated scan results;
- test plans;
- release security approval;
- exception handling;
- post-release monitoring.

---

## 5.15 Customer и Deployment Register

### Deployment полета

- customer;
- product;
- version;
- environment;
- installation date;
- support contract;
- contact;
- criticality;
- internet exposure;
- update channel;
- last confirmed version;
- custom modifications;
- end-of-support exception.

### При vulnerability

Системата генерира:

- affected-customer list;
- communication draft;
- update status;
- acknowledgement;
- patch deployment tracking;
- unresolved exposure list.

---

## 5.16 Support Period Management

За всеки продукт:

- commercial support period;
- security support period;
- start date;
- end date;
- basis for determination;
- supported versions;
- extended support;
- customer exceptions;
- notification schedule;
- end-of-support communication.

### Автоматични известия

```text
180 дни до End of Support
90 дни
30 дни
End of Support reached
Unsupported deployments remain
```

---

## 5.17 User Security Instructions

Структурирани секции:

- secure installation;
- minimum environment requirements;
- required permissions;
- secure configuration;
- default settings;
- encryption requirements;
- backup;
- logging;
- update procedure;
- security contact;
- vulnerability reporting;
- support period;
- end-of-support behavior;
- known limitations.

Изходни формати:

- HTML;
- PDF;
- README;
- release package document;
- customer-specific installation guide.

---

# 6. AI функционалности

AI трябва да бъде помощник, а не автономен compliance орган.

## Подходящи приложения

### Regulatory assistant

Отговаря само върху:

- приложимите изисквания;
- текущите controls;
- наличните evidence записи;
- продукта;
- версията;
- одобрената regulatory content версия.

### Document analyser

При качване на:

- security policy;
- penetration-test report;
- technical manual;
- architecture document.

AI предлага:

- requirement mappings;
- potential evidence mappings;
- липсващи полета;
- stale версии;
- противоречия;
- необходим human review.

### Vulnerability triage assistant

Извлича:

- component;
- affected versions;
- severity;
- exploitability;
- recommended fixed version;
- вероятно засегнати продукти.

### Draft generator

- security advisory;
- customer notification;
- incident summary;
- risk description;
- technical documentation section;
- release security notes;
- conformity evidence summary.

## AI не трябва самостоятелно да

- определя окончателна CRA приложимост;
- потвърждава compliance;
- приема residual risk;
- подава report;
- затваря vulnerability;
- променя classification;
- взема юридическо решение.

Всички съществени действия трябва да имат human approval.

---

# 7. Интеграции

## Първа вълна

- GitHub;
- GitLab;
- generic Git metadata;
- Composer;
- npm;
- NuGet;
- CycloneDX;
- SPDX;
- email notifications;
- webhook API;
- CSV import/export.

## Втора вълна

- Jira;
- Azure DevOps;
- Dependabot;
- Renovate;
- Snyk;
- Trivy;
- OWASP Dependency-Check;
- SonarQube;
- container registries;
- vulnerability feeds;
- customer support systems.

## Принцип

Да се импортират само compliance-relevant данни:

- repository;
- commit;
- tag;
- pull request;
- reviewer;
- CI result;
- scan result;
- release artifact;
- timestamp;
- immutable reference.

---

# 8. Предложена архитектура

## Примерна структура

```text
Web Application
├── Tenant and Identity
├── Product Registry
├── Requirements Engine
├── Risk Engine
├── Vulnerability Engine
├── Incident Engine
├── Evidence Store
├── Workflow Engine
├── Reporting Engine
├── Integration Workers
└── Audit Service
```

## Подходящ технологичен стек

- Laravel;
- PostgreSQL;
- Vue 3 + Inertia или отделен REST/GraphQL frontend;
- Redis;
- queue workers;
- S3-compatible object storage;
- scheduler;
- notification service;
- isolated document-processing service;
- webhook ingestion API;
- OpenSearch само при реална необходимост.

## Данни с пълна история

- approvals;
- risk acceptance;
- release decisions;
- report submissions;
- awareness timestamps;
- incident timelines;
- evidence hashes;
- regulatory mappings;
- classification decisions.

Не е необходим blockchain. Достатъчни са:

- append-only audit records;
- immutable object versions;
- checksums;
- version history;
- digital signatures за определени exports.

---

# 9. Начален data model

```text
organizations
users
organization_users
roles

products
product_versions
product_classifications
product_support_periods
product_deployments

components
component_versions
product_components
sboms
sbom_entries

regulations
requirements
requirement_versions
product_requirements
controls
control_requirements
product_controls

risks
risk_treatments
risk_controls
risk_approvals

evidence
evidence_links
evidence_reviews

vulnerabilities
vulnerability_products
vulnerability_versions
vulnerability_components
vulnerability_actions
vulnerability_reports

incidents
incident_products
incident_timelines
incident_reports

releases
release_checks
release_approvals
release_artifacts

customers
customer_contacts
customer_notifications

tasks
comments
attachments
approvals
audit_events
```

## Versioned regulatory content

```text
requirement
└── requirement_versions
    ├── version 1
    ├── version 2
    └── version 3
```

Всяка product assessment трябва да пази връзка към точната версия на requirement content, използвана при оценката.

---

# 10. Dashboard

## Organization dashboard

- продукти без класификация;
- продукти без support period;
- липсващи risk assessments;
- critical vulnerabilities;
- reporting deadlines;
- releases без security approval;
- expired evidence;
- unsupported deployments;
- незавършени corrective actions;
- readiness trend.

## Product dashboard

```text
Product: BORICA Payments for WooCommerce

Classification: General
Current version: 4.8.2
Support period: Active
Open vulnerabilities: 1
Critical vulnerabilities: 0
Requirements implemented: 71%
Evidence verified: 54%
Last risk review: 42 days ago
Unsupported deployments: 3
Next review: 18 days
```

Dashboard-ът трябва да показва действия, не декоративни графики.

---

# 11. MVP

## MVP цел

Един малък софтуерен производител да може да:

1. регистрира продукт;
2. направи предварителна CRA scope и classification оценка;
3. управлява версии;
4. зададе support period;
5. импортира components и SBOM;
6. направи risk assessment;
7. свърже CRA requirements с controls;
8. приложи evidence;
9. регистрира vulnerability;
10. следи reporting deadlines;
11. одобри release;
12. генерира readiness report.

## Задължителни MVP модули

> **Статус (2026-07-23):** MVP 0.1 **Done / exited**. Phase 2.1–2.5 **Closed**. Активен план: [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md).

1. Organizations и users.
2. Product register.
3. Scope/classification questionnaire.
4. Product versions.
5. Support periods.
6. Requirements matrix.
7. Controls.
8. Risk register.
9. Component inventory.
10. SBOM import.
11. Vulnerability register.
12. Evidence repository.
13. Tasks and approvals.
14. Audit log.
15. Readiness report.
16. Reporting-deadline workflow.

## Да не влизат в първата версия

- автоматично SRP submission;
- пълна двупосочна GitHub синхронизация;
- сложен AI assistant;
- notified-body portal;
- customer self-service portal;
- penetration-testing engine;
- собствен vulnerability scanner;
- full incident orchestration;
- сложна billing система;
- white-labeling;
- mobile application.

---

# 13. План за първите 12 седмици

## Седмици 1–2: Regulatory и domain model

- структуриране на CRA;
- requirements catalogue;
- glossary;
- scope questionnaire;
- classification model;
- evidence model;
- risk model;
- vulnerability lifecycle;
- reporting deadlines;
- разграничаване на legal text, guidance и product interpretation.

### Резултат

- domain model;
- ER diagram;
- regulatory dataset v1;
- onboarding flow;
- MVP specification.

## Седмици 3–4: Product Registry

- organizations;
- users;
- roles;
- products;
- versions;
- support periods;
- repositories;
- components;
- deployments;
- audit-log foundation.

### Резултат

Собствените продукти могат да бъдат описани в системата.

## Седмици 5–6: Requirements и Risk

- requirements library;
- applicability decisions;
- controls;
- risk assessment;
- treatments;
- approvals;
- evidence linking.

### Резултат

Може да се извърши първоначална readiness оценка.

## Седмици 7–8: SBOM и Vulnerabilities

- CycloneDX import;
- SPDX import;
- component matching;
- vulnerability records;
- affected versions;
- corrective actions;
- deadlines;
- advisory drafts.

### Резултат

Може да се обработи vulnerability от откриване до patch.

## Седмици 9–10: Evidence и Reporting

- document upload;
- hashes;
- evidence versions;
- validity;
- reviews;
- technical documentation outline;
- readiness report;
- reporting package export.

### Резултат

Може да се генерира одитируема продуктова папка.

## Седмици 11–12: MVP closeout и release readiness

- валидация по §20 „Преди MVP release“ — **Done**;
- два реални продукта end-to-end — **Done**;
- симулирана vulnerability + reporting deadlines — **Done**;
- release през readiness gate — **Done**;
- copy/disclaimer review (без юридическа compliance гаранция) — **Done**;
- затваряне на P0 closeout backlog — **Done**;
- P1 polish — **Done**: support buckets 180/90/30, org dashboard actions, thin technical documentation outline;
- формален MVP 0.1 exit (2026-07-20) — **Done**.

### Резултат

MVP 0.1 е **exited** и готов за вътрешна употреба. Втора фаза (§14): Phase 2.1–2.5 са **Closed**. Следва [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) (§5.14).

> Closeout (Closed): [MVP_Release_Closeout.md](MVP_Release_Closeout.md).

---

# 14. Втора фаза

> MVP 0.1 е затворен. Phase 2.1–2.5 са **Closed**: [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md), [Phase2_2_Release_Closeout.md](Phase2_2_Release_Closeout.md), [Phase2_3_Release_Closeout.md](Phase2_3_Release_Closeout.md), [Phase2_4_Release_Closeout.md](Phase2_4_Release_Closeout.md), [Phase2_5_Release_Closeout.md](Phase2_5_Release_Closeout.md). Активен: [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md).

## GitHub/GitLab integration

- repositories;
- tags;
- releases;
- pull requests;
- CI status;
- Dependabot alerts;
- evidence snapshots.

> **Статус:** Closed (2026-07-21) — Must + Should + Could Done. Merged-PR summary остава deferred polish.

## Customer deployments

- affected customers;
- patch campaigns;
- notification history;
- update confirmation.

> **Статус:** Closed — [Phase2_2_Customer_Deployments.md](Phase2_2_Customer_Deployments.md) + [Phase2_2_Release_Closeout.md](Phase2_2_Release_Closeout.md).

## AI assistant

- document mapping;
- evidence gap analysis;
- vulnerability summarisation;
- draft generation.

> **Статус:** Closed (2026-07-22) — 2.3C Must + Should + Could Done — [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) §2.3C + [Phase2_3_Release_Closeout.md](Phase2_3_Release_Closeout.md).

## Policy library

- vulnerability disclosure policy;
- secure-development policy;
- support policy;
- update policy;
- incident-response procedure;
- third-party component policy.

> **Статус:** Closed (2026-07-22) — 2.3A Must + Should + Could Done — [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) §2.3A.

## Auditor portal

- read-only access;
- selected evidence package;
- findings;
- comments;
- remediation;
- export.

> **Статус:** Closed (2026-07-22) — 2.3B Must + Should + Could Done — [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) §2.3B.

## User Security Instructions

- structured product security documentation (§5.17);
- section templates;
- HTML / PDF / README exports;
- optional evidence + readiness hooks;
- customer guide export, AI section drafts, supersede diff, review tasks, EN/BG pairs.

> **Статус:** Closed (2026-07-23) — Must + Should + Could Done — [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) + [Phase2_4_Release_Closeout.md](Phase2_4_Release_Closeout.md).

## Security Incident Management

- separate incident object from vulnerability (§5.10);
- timeline (start / detection / awareness / classification);
- linked vulnerability, tasks, evidence;
- no SRP auto-submit / full SOAR in this wave.

> **Статус:** Closed (2026-07-23) — Must + Should + Could Done — [Phase2_5_Security_Incident_Management.md](Phase2_5_Security_Incident_Management.md) + [Phase2_5_Release_Closeout.md](Phase2_5_Release_Closeout.md).

## Secure Development Lifecycle

- product-scoped SDL runs / stages (§5.14);
- evidence + Git artifact links;
- release security approval gate;
- exceptions; no full ALM / SIEM in this wave.

> **Статус:** Active — skeleton — [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md).

---

# 15. Бизнес модел

## Вариант 1: SaaS по брой продукти

### Solo

- до 3 продукта;
- 1–2 потребители;
- basic evidence;
- basic vulnerability management.

### Small Team

- до 15 продукта;
- integrations;
- approvals;
- reporting;
- customer deployments.

### Company

- повече продукти;
- advanced permissions;
- auditor portal;
- API;
- SSO;
- policy library.

## Вариант 2: SaaS + onboarding услуга

- product inventory;
- readiness workshop;
- import на продуктите;
- настройка на workflows;
- evidence mapping;
- обучение;
- годишен review.

Този модел вероятно ще бъде най-удачен в началото.

## Вариант 3: Self-hosted

Подходящ за:

- банки;
- embedded производители;
- security-sensitive организации;
- компании, които не желаят техническата им документация да бъде в чужд SaaS.

---

# 16. Основни продуктови рискове

## Риск 1: Продуктът става checklist

Решение:

Всеки requirement се свързва с:

- control;
- owner;
- evidence;
- продукт;
- версия;
- review;
- approval.

## Риск 2: Обещава юридическа сигурност

Решение:

- ясно разграничение между software assistance и legal assessment;
- versioned regulatory content;
- възможност за external reviewer;
- никакъв автоматичен „Certified“ badge.

## Риск 3: Regulatory content се променя

Решение:

- effective dates;
- content versions;
- mapping history;
- update mechanism;
- delta reports.

## Риск 4: Прекалено широк пазар

Решение:

Начален фокус:

> Малки компании, разработващи комерсиални software products за европейския пазар.

Да не се започва едновременно с IoT, automotive, medical devices, industrial hardware и operating systems.

## Риск 5: Твърде много ръчно въвеждане

Решение:

- templates;
- product cloning;
- inherited controls;
- evidence reuse;
- repository import;
- SBOM import;
- document generation;
- sensible defaults.

---

# 17. Проверка на пазарния интерес

## Подходящи интервюирани

- plugin developers;
- ERP компании;
- desktop software vendors;
- embedded software firms;
- SaaS компании с local agents;
- IT асоциации;
- cybersecurity consultants;
- conformity consultants.

## Въпроси

1. Кой във фирмата отговаря за CRA?
2. Имате ли регистър на всички продукти и поддържани версии?
3. Знаете ли кои клиенти използват засегната версия?
4. Можете ли да генерирате SBOM за всяка release версия?
5. Как управлявате security vulnerabilities?
6. Как определяте support period?
7. Къде се пазят доказателствата за security testing?
8. Можете ли за един ден да сглобите technical documentation за конкретна версия?
9. Как ще следите 24- и 72-часовите reporting срокове?
10. Колко човека и дни ще са необходими за подготовката на един продукт?
11. Кои части в момента поддържате чрез Excel, Word или имейл?
12. Кой бюджет е по-лесен за одобрение: SaaS, onboarding услуга или self-hosted лиценз?

Да не се пита само „Бихте ли купили такава система?“.

---

# 18. Първа реална версия

## CRA Workspace 0.1

> Product security and compliance workspace for small software manufacturers.

### Основен flow

```text
Create organization
→ Add product
→ Determine likely scope
→ Add versions
→ Set support period
→ Import components/SBOM
→ Perform risk assessment
→ Map CRA requirements
→ Attach evidence
→ Register vulnerabilities
→ Prepare release
→ Generate readiness report
```

## Отличителна функция: Product Compliance Passport

Една страница за всеки продукт:

- identification;
- manufacturer;
- classification;
- versions;
- support period;
- CRA applicability;
- risk status;
- requirements coverage;
- SBOM status;
- vulnerabilities;
- release status;
- technical documentation;
- evidence completeness;
- responsible persons;
- reporting readiness.

---

# 19. Стратегическа посока

Добрата продуктова формулировка е:

> **Product Security Lifecycle Workspace, моделиран първоначално върху CRA.**

CRA трябва да бъде първият regulatory framework, но вътрешният модел може по-късно да се разшири към:

- NIS2 supplier requirements;
- ISO 27001 controls;
- secure SDLC frameworks;
- customer security questionnaires;
- internal policies;
- sector-specific requirements;
- vulnerability disclosure и incident workflows.

Така продуктът няма да бъде еднократен инструмент за първоначална CRA подготовка, а ежедневна система за product security management.

---

# 20. Препоръчителна последователност на работа

## Преди започване на разработката

- [ ] Прочит на официалния CRA текст и официалните guidance материали.
- [ ] Създаване на glossary.
- [ ] Дефиниране на първия целеви клиент.
- [ ] Избор на два собствени продукта за първоначално въвеждане.
- [ ] Събиране на реални документи, release packages и security evidence.
- [ ] Описание на vulnerability workflow.
- [ ] Описание на release workflow.
- [ ] Определяне на минималните роли.
- [ ] Решение кои данни трябва да бъдат immutable.
- [ ] Разграничаване на SaaS и self-hosted изискванията.

## Преди MVP release

> Оперативен статус и closeout backlog: [MVP_Release_Closeout.md](MVP_Release_Closeout.md).

- [x] Поне два реални продукта са въведени.
- [x] Поне една vulnerability е симулирана.
- [x] Поне един incident е симулиран. _(Минимална симулация: task + vulnerability/evidence; пълен Incident module остава извън MVP. Виж closeout плана.)_
- [x] Поне един release е преминал през readiness gate.
- [x] SBOM import работи за Composer.
- [x] Regulatory content е versioned.
- [x] Evidence има hash и history.
- [x] Reporting deadlines се изчисляват от awareness timestamp.
- [x] Ролите и approvals работят.
- [x] Readiness report може да бъде експортиран.
- [x] Няма маркетингово обещание за автоматична юридическа compliance гаранция.

---

# 21. Официални източници и полезни документи

## Европейска комисия

### Cyber Resilience Act – основна страница

https://digital-strategy.ec.europa.eu/en/policies/cyber-resilience-act

### CRA – обобщение на законодателния текст

https://digital-strategy.ec.europa.eu/en/policies/cra-summary

### CRA – информация за производители

https://digital-strategy.ec.europa.eu/en/policies/cra-manufacturers

### CRA – reporting obligations

https://digital-strategy.ec.europa.eu/en/policies/cra-reporting

### CRA – conformity assessment

https://digital-strategy.ec.europa.eu/en/policies/cra-conformity-assessment

### CRA – implementation timeline и материали

https://digital-strategy.ec.europa.eu/en/factpages/cyber-resilience-act-implementation

### CRA – implementation FAQ

https://digital-strategy.ec.europa.eu/en/library/cyber-resilience-act-implementation-frequently-asked-questions

### CRA – open-source guidance

https://digital-strategy.ec.europa.eu/en/policies/cra-open-source

## ENISA

### Single Reporting Platform

https://www.enisa.europa.eu/topics/product-security-and-certification/single-reporting-platform-srp

### Product Security and Certification

https://www.enisa.europa.eu/topics/product-security-and-certification

### Vulnerability Disclosure

https://www.enisa.europa.eu/topics/vulnerability-disclosure

### European Vulnerability Database

https://euvd.enisa.europa.eu/

### CRA Requirements Standards Mapping

https://www.enisa.europa.eu/sites/default/files/2024-11/Cyber%20Resilience%20Act%20Requirements%20Standards%20Mapping%20-%20final_with_identifiers_0.pdf

### SME CRA Survey Report

https://www.enisa.europa.eu/publications/sme-cra-survey-report

## Правен текст

### EUR-Lex – Regulation (EU) 2024/2847

https://eur-lex.europa.eu/eli/reg/2024/2847/oj

---

# 22. Бележка за поддръжката на този документ

Тъй като guidance, harmonised standards, implementing acts и техническите възможности на Single Reporting Platform могат да се развиват, този план трябва да се преглежда периодично.

Препоръчителен цикъл:

- на всеки 3 месеца по време на първоначалната разработка;
- преди всяка основна продуктова версия;
- при публикуване на ново official guidance;
- при промяна на reporting или conformity assessment процедурите;
- при добавяне на нов regulatory framework.

Свързани работни планове:

- [MVP_Release_Closeout.md](MVP_Release_Closeout.md) — Closed: MVP 0.1 exited (2026-07-20);
- [Phase2_1_GitHub_GitLab_Integration.md](Phase2_1_GitHub_GitLab_Integration.md) — Closed: Phase 2.1 exited (2026-07-21);
- [Phase2_2_Customer_Deployments.md](Phase2_2_Customer_Deployments.md) — Closed: Customer deployments ([Phase2_2_Release_Closeout.md](Phase2_2_Release_Closeout.md));
- [Phase2_3_Policy_Auditor_AI.md](Phase2_3_Policy_Auditor_AI.md) — Closed: Policy / Auditor / AI ([Phase2_3_Release_Closeout.md](Phase2_3_Release_Closeout.md));
- [Phase2_4_User_Security_Instructions.md](Phase2_4_User_Security_Instructions.md) — Closed: User Security Instructions ([Phase2_4_Release_Closeout.md](Phase2_4_Release_Closeout.md));
- [Phase2_5_Security_Incident_Management.md](Phase2_5_Security_Incident_Management.md) — Closed: Security Incident Management ([Phase2_5_Release_Closeout.md](Phase2_5_Release_Closeout.md));
- [Phase2_6_Secure_Development_Lifecycle.md](Phase2_6_Secure_Development_Lifecycle.md) — Active: Secure Development Lifecycle skeleton (§5.14).

---

# Заключение

Най-силната начална версия не е „универсална compliance платформа“, а:

> **Практичен Product Security Lifecycle Workspace за малки производители на софтуер, с CRA като първа регулаторна рамка.**

Началният продукт трябва да решава пет конкретни задачи:

1. Да показва какви продукти и версии съществуват.
2. Да свързва изисквания, рискове, controls и evidence.
3. Да управлява SBOM, vulnerabilities и corrective releases.
4. Да следи засегнатите клиенти и reporting deadlines.
5. Да генерира проследима продуктова документация и readiness report.

Това създава продукт, който има стойност не само преди одит, а през целия жизнен цикъл на софтуера.
