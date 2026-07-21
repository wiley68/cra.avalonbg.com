<?php

namespace App\Support;

use App\Enums\PolicyType;

class PolicyTemplates
{
    /**
     * @return array{title: string, body: string, version_label: string}
     */
    public static function for(PolicyType $type, string $locale = 'en'): array
    {
        $locale = in_array($locale, ['en', 'bg'], true) ? $locale : 'en';

        return match ($type) {
            PolicyType::VulnerabilityDisclosure => [
                'title' => $locale === 'bg'
                    ? 'Политика за разкриване на уязвимости'
                    : 'Vulnerability disclosure policy',
                'version_label' => '1.0',
                'body' => self::vulnerabilityDisclosure($locale),
            ],
            PolicyType::SecureDevelopment => [
                'title' => $locale === 'bg'
                    ? 'Политика за сигурна разработка'
                    : 'Secure development policy',
                'version_label' => '1.0',
                'body' => self::secureDevelopment($locale),
            ],
            PolicyType::Support => [
                'title' => $locale === 'bg'
                    ? 'Политика за поддръжка'
                    : 'Support policy',
                'version_label' => '1.0',
                'body' => self::support($locale),
            ],
            PolicyType::Update => [
                'title' => $locale === 'bg'
                    ? 'Политика за обновявания'
                    : 'Update policy',
                'version_label' => '1.0',
                'body' => self::update($locale),
            ],
            PolicyType::IncidentResponse => [
                'title' => $locale === 'bg'
                    ? 'Процедура за реагиране при инциденти'
                    : 'Incident response procedure',
                'version_label' => '1.0',
                'body' => self::incidentResponse($locale),
            ],
            PolicyType::ThirdPartyComponents => [
                'title' => $locale === 'bg'
                    ? 'Политика за компоненти от трети страни'
                    : 'Third-party component policy',
                'version_label' => '1.0',
                'body' => self::thirdParty($locale),
            ],
        };
    }

    private static function vulnerabilityDisclosure(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
# Политика за разкриване на уязвимости

## Цел
Описва как организацията приема, обработва и комуникира доклади за уязвимости в продуктите си.

## Контакт
- Security contact: security@example.com
- Очаквано потвърждение: в рамките на 5 работни дни

## Обхват
Покрива всички продукти и версии, които организацията поддържа.

## Процес
1. Прием на доклада
2. Триаж и оценка на въздействието
3. Remediation и координирано разкриване
4. Публично уведомление, когато е приложимо

## Забранени действия
Не се допуска експлоатация, която нарушава наличността или поверителността на клиентски данни.
MD;
        }

        return <<<'MD'
# Vulnerability disclosure policy

## Purpose
Describes how the organization receives, handles, and communicates vulnerability reports for its products.

## Contact
- Security contact: security@example.com
- Acknowledgement target: within 5 business days

## Scope
Covers all products and versions currently supported by the organization.

## Process
1. Intake of the report
2. Triage and impact assessment
3. Remediation and coordinated disclosure
4. Public notification when applicable

## Out of scope
Do not perform testing that disrupts availability or exposes customer data.
MD;
    }

    private static function secureDevelopment(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
# Политика за сигурна разработка

## Цел
Определя минималните практики за сигурност в жизнения цикъл на разработка.

## Изисквания
- Преглед на промени с security impact
- Управление на зависимости и SBOM
- Тестване преди release
- Разделяне на роли за критични approvals

## Отговорности
Product Owner и Security Owner потвърждават готовността преди release.
MD;
        }

        return <<<'MD'
# Secure development policy

## Purpose
Defines minimum security practices across the software development lifecycle.

## Requirements
- Review changes with security impact
- Dependency management and SBOM
- Testing before release
- Separation of duties for critical approvals

## Responsibilities
Product Owner and Security Owner confirm readiness before release.
MD;
    }

    private static function support(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
# Политика за поддръжка

## Цел
Описва търговската и security поддръжка за продуктови версии.

## Периоди
- Commercial support: според договор
- Security support: според публикуваните support periods

## Изключения
Клиентски изключения се документират отделно.
MD;
        }

        return <<<'MD'
# Support policy

## Purpose
Describes commercial and security support for product versions.

## Periods
- Commercial support: per contract
- Security support: per published support periods

## Exceptions
Customer-specific exceptions are documented separately.
MD;
    }

    private static function update(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
# Политика за обновявания

## Цел
Описва как се предоставят security и функционални обновявания.

## Канали
- Release notes
- Customer notifications / patch campaigns
- Artifact integrity (hash)

## Очаквания към клиентите
Клиентите трябва да прилагат критични security updates в разумен срок.
MD;
        }

        return <<<'MD'
# Update policy

## Purpose
Describes how security and functional updates are delivered.

## Channels
- Release notes
- Customer notifications / patch campaigns
- Artifact integrity (hash)

## Customer expectations
Customers should apply critical security updates within a reasonable timeframe.
MD;
    }

    private static function incidentResponse(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
# Процедура за реагиране при инциденти

## Цел
Определя стъпките при security инцидент, засягащ продуктите.

## Фази
1. Откриване и класификация
2. Containment
3. Eradication и recovery
4. Lessons learned

## Комуникация
Internal escalation и външни уведомления следват правните изисквания и reporting workflow.
MD;
        }

        return <<<'MD'
# Incident response procedure

## Purpose
Defines steps for security incidents affecting products.

## Phases
1. Detection and classification
2. Containment
3. Eradication and recovery
4. Lessons learned

## Communication
Internal escalation and external notifications follow legal requirements and the reporting workflow.
MD;
    }

    private static function thirdParty(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
# Политика за компоненти от трети страни

## Цел
Управлява приемането и наблюдението на third-party / open-source компоненти.

## Изисквания
- Inventory / SBOM
- Лицензионен преглед
- Следене на известни уязвимости
- План за замяна на unsupported компоненти
MD;
        }

        return <<<'MD'
# Third-party component policy

## Purpose
Governs acceptance and monitoring of third-party / open-source components.

## Requirements
- Inventory / SBOM
- License review
- Tracking known vulnerabilities
- Replacement plan for unsupported components
MD;
    }
}
