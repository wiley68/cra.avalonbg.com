<?php

namespace App\Support;

use App\Enums\SdlStage;

class SdlStageNoteTemplates
{
    /**
     * Stages that receive secure coding / threat checklist templates.
     *
     * @return list<SdlStage>
     */
    public static function templatedStages(): array
    {
        return [
            SdlStage::Requirement,
            SdlStage::ThreatReview,
            SdlStage::Development,
            SdlStage::CodeReview,
            SdlStage::DependencyScan,
            SdlStage::SecurityTest,
        ];
    }

    public static function hasTemplate(SdlStage $stage): bool
    {
        return in_array($stage, self::templatedStages(), true);
    }

    public static function notesFor(SdlStage $stage, string $locale = 'en'): ?string
    {
        if (!self::hasTemplate($stage)) {
            return null;
        }

        $locale = self::normalizeLocale($locale);

        return match ($stage) {
            SdlStage::Requirement => self::requirement($locale),
            SdlStage::ThreatReview => self::threatReview($locale),
            SdlStage::Development => self::development($locale),
            SdlStage::CodeReview => self::codeReview($locale),
            SdlStage::DependencyScan => self::dependencyScan($locale),
            SdlStage::SecurityTest => self::securityTest($locale),
            default => null,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function payload(string $locale = 'en'): array
    {
        $locale = self::normalizeLocale($locale);
        $payload = [];

        foreach (self::templatedStages() as $stage) {
            $notes = self::notesFor($stage, $locale);

            if ($notes !== null) {
                $payload[$stage->value] = $notes;
            }
        }

        return $payload;
    }

    public static function normalizeLocale(string $locale): string
    {
        return in_array($locale, ['en', 'bg'], true) ? $locale : 'en';
    }

    private static function requirement(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Изисквания за сигурност

Отбележете приложимите изисквания и къде са записани.

- [ ] Сигурностни изисквания са идентифицирани за тази промяна / версия
- [ ] Свързани controls / политики са посочени (или N/A с обосновка)
- [ ] Данни в обхват и чувствителност са уточнени
- [ ] Очаквания за автентикация, оторизация и одит са ясни
- [ ] Критерии за приемане включват сигурностни проверки

> Заменете placeholders с конкретни референции за този продукт.
MD;
        }

        return <<<'MD'
## Security requirements

Mark applicable requirements and where they are recorded.

- [ ] Security requirements identified for this change / version
- [ ] Related controls / policies referenced (or N/A with rationale)
- [ ] In-scope data and sensitivity clarified
- [ ] AuthN / AuthZ / audit expectations are clear
- [ ] Acceptance criteria include security checks

> Replace placeholders with concrete references for this product.
MD;
    }

    private static function threatReview(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Преглед на заплахи

Кратки threat considerations за тази промяна.

- [ ] Активи / trust boundaries са описани
- [ ] Основни заплахи (spoofing, tampering, disclosure, DoS, elevation) са разгледани
- [ ] Abuse cases / misuse са обсъдени
- [ ] Митигации са записани или ескалирани
- [ ] Остатъчен риск е приет или отхвърлен с owner

> Фокусирайте се върху делтата спрямо предишната версия.
MD;
        }

        return <<<'MD'
## Threat considerations

Short threat review notes for this change.

- [ ] Assets / trust boundaries described
- [ ] Primary threats (spoofing, tampering, disclosure, DoS, elevation) considered
- [ ] Abuse / misuse cases discussed
- [ ] Mitigations recorded or escalated
- [ ] Residual risk accepted or rejected with an owner

> Focus on the delta versus the previous version.
MD;
    }

    private static function development(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Чеклист за сигурно кодиране

Проверете практиките по време на разработка.

- [ ] Входните данни се валидират и нормализират
- [ ] Изходът е кодиран според контекста (HTML/SQL/команда)
- [ ] Тайни не са в кода / логовете; ползват се secrets store
- [ ] Криптографията ползва одобрени библиотеки и параметри
- [ ] Грешките не разкриват чувствителни детайли
- [ ] Зависимостите са прегледани преди добавяне

> Отбележете отклонения като exception с owner.
MD;
        }

        return <<<'MD'
## Secure coding checklist

Confirm practices during development.

- [ ] Inputs are validated and normalized
- [ ] Outputs are encoded for context (HTML / SQL / command)
- [ ] Secrets are not in code / logs; secrets store is used
- [ ] Cryptography uses approved libraries and parameters
- [ ] Errors do not leak sensitive details
- [ ] Dependencies reviewed before adding

> Record deviations as an exception with an owner.
MD;
    }

    private static function codeReview(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Преглед на кода (сигурност)

Peer review фокус върху сигурността.

- [ ] Промените са прегледани от втори човек
- [ ] AuthZ / session / CSRF проверки са покрити
- [ ] Risky APIs (deserialization, eval, shell) са оправдани
- [ ] Тестовете покриват негативни / abuse сценарии
- [ ] Evidence (PR link) е свързан към етапа

> Запишете reviewer и ключови findings.
MD;
        }

        return <<<'MD'
## Secure code review

Peer review focused on security.

- [ ] Changes reviewed by a second person
- [ ] AuthZ / session / CSRF checks covered
- [ ] Risky APIs (deserialization, eval, shell) justified
- [ ] Tests cover negative / abuse scenarios
- [ ] Evidence (PR link) linked to this stage

> Record reviewer and key findings.
MD;
    }

    private static function dependencyScan(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Сканиране на зависимости

Проверка на third-party компоненти.

- [ ] SBOM / lockfile е актуален
- [ ] SCA / vulnerability scan е изпълнен
- [ ] Critical / high findings са адресирани или accepted с owner
- [ ] Лицензионните рискове са прегледани (ако е приложимо)
- [ ] Evidence (scan report) е свързан

> Не блокирайте release без записано решение за findings.
MD;
        }

        return <<<'MD'
## Dependency / SBOM scan

Third-party component checks.

- [ ] SBOM / lockfile is current
- [ ] SCA / vulnerability scan executed
- [ ] Critical / high findings remediated or accepted with owner
- [ ] License risks reviewed (if applicable)
- [ ] Evidence (scan report) linked

> Do not ship without a recorded decision on open findings.
MD;
    }

    private static function securityTest(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## План / резултати от тестове за сигурност

- [ ] Тестовият обхват е дефиниран (auth, input, session, API)
- [ ] Automated security tests / regression са пуснати
- [ ] Manual / exploratory проверки са документирани
- [ ] Findings са triaged (fix / accept / defer)
- [ ] Evidence (test report) е свързан към етапа

> При липса на тест — N/A с ясна обосновка.
MD;
        }

        return <<<'MD'
## Security test plan / results

- [ ] Test scope defined (auth, input, session, API)
- [ ] Automated security / regression tests executed
- [ ] Manual / exploratory checks documented
- [ ] Findings triaged (fix / accept / defer)
- [ ] Evidence (test report) linked to this stage

> If no test applies, mark N/A with clear rationale.
MD;
    }
}
