<?php

namespace App\Support;

use App\Enums\UserSecurityInstructionSectionKey;

class UserSecurityInstructionTemplates
{
    /**
     * @return array{
     *     title: string,
     *     version_label: string,
     *     sections: list<array{section_key: string, body: string}>
     * }
     */
    public static function for(string $locale = 'en'): array
    {
        $locale = self::normalizeLocale($locale);

        return [
            'title' => $locale === 'bg'
                ? 'Инструкции за сигурност на потребителя'
                : 'User security instructions',
            'version_label' => '1.0',
            'sections' => array_map(
                fn(UserSecurityInstructionSectionKey $key) => [
                    'section_key' => $key->value,
                    'body' => self::sectionBody($key, $locale),
                ],
                UserSecurityInstructionSectionKey::ordered(),
            ),
        ];
    }

    public static function sectionBody(UserSecurityInstructionSectionKey $key, string $locale = 'en'): string
    {
        $locale = self::normalizeLocale($locale);

        return match ($key) {
            UserSecurityInstructionSectionKey::SecureInstallation => self::secureInstallation($locale),
            UserSecurityInstructionSectionKey::MinimumEnvironment => self::minimumEnvironment($locale),
            UserSecurityInstructionSectionKey::RequiredPermissions => self::requiredPermissions($locale),
            UserSecurityInstructionSectionKey::SecureConfiguration => self::secureConfiguration($locale),
            UserSecurityInstructionSectionKey::DefaultSettings => self::defaultSettings($locale),
            UserSecurityInstructionSectionKey::EncryptionRequirements => self::encryptionRequirements($locale),
            UserSecurityInstructionSectionKey::Backup => self::backup($locale),
            UserSecurityInstructionSectionKey::Logging => self::logging($locale),
            UserSecurityInstructionSectionKey::UpdateProcedure => self::updateProcedure($locale),
            UserSecurityInstructionSectionKey::SecurityContact => self::securityContact($locale),
            UserSecurityInstructionSectionKey::VulnerabilityReporting => self::vulnerabilityReporting($locale),
            UserSecurityInstructionSectionKey::SupportPeriod => self::supportPeriod($locale),
            UserSecurityInstructionSectionKey::EndOfSupportBehavior => self::endOfSupportBehavior($locale),
            UserSecurityInstructionSectionKey::KnownLimitations => self::knownLimitations($locale),
        };
    }

    private static function normalizeLocale(string $locale): string
    {
        return in_array($locale, ['en', 'bg'], true) ? $locale : 'en';
    }

    private static function secureInstallation(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Сигурна инсталация

Опишете стъпките за сигурна инсталация на продукта.

- Източник на дистрибуция (официален канал / checksum / подпис)
- Предварителни условия преди инсталация
- Стъпки за инсталация
- Проверка след инсталация (health check)

> Заменете placeholder текстовете с конкретни команди и артефакти за този продукт.
MD;
        }

        return <<<'MD'
## Secure installation

Describe how to install the product securely.

- Distribution source (official channel / checksum / signature)
- Preconditions before install
- Installation steps
- Post-install verification (health check)

> Replace placeholders with concrete commands and artefacts for this product.
MD;
    }

    private static function minimumEnvironment(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Минимална среда

- Поддържани ОС / runtime версии
- Минимален хардуер / ресурси
- Мрежови изисквания
- Зависимости от трети страни
MD;
        }

        return <<<'MD'
## Minimum environment

- Supported OS / runtime versions
- Minimum hardware / resources
- Network requirements
- Third-party dependencies
MD;
    }

    private static function requiredPermissions(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Изисквани права

Избройте нужните привилегии (OS, контейнер, cloud IAM) и защо са необходими.

- Privilege за инсталация
- Privilege за runtime
- Какво **не** е нужно (least privilege)
MD;
        }

        return <<<'MD'
## Required permissions

List required privileges (OS, container, cloud IAM) and why they are needed.

- Install-time privileges
- Runtime privileges
- What is **not** required (least privilege)
MD;
    }

    private static function secureConfiguration(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Сигурна конфигурация

- Задължителни настройки за сигурност
- Препоръчителни hardening стъпки
- Конфигурационни файлове / ключове / secrets management
MD;
        }

        return <<<'MD'
## Secure configuration

- Mandatory security settings
- Recommended hardening steps
- Configuration files / keys / secrets management
MD;
    }

    private static function defaultSettings(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Настройки по подразбиране

Документирайте factory defaults и кои трябва да бъдат променени преди production.

| Настройка | Default | Препоръка за production |
| --------- | ------- | ----------------------- |
| Пример    | off     | on                      |
MD;
        }

        return <<<'MD'
## Default settings

Document factory defaults and which ones must change before production.

| Setting | Default | Production recommendation |
| ------- | ------- | ------------------------- |
| Example | off     | on                        |
MD;
    }

    private static function encryptionRequirements(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Изисквания за криптиране

- TLS версии / cipher suites
- Криптиране на данни в покой
- Управление на ключове
MD;
        }

        return <<<'MD'
## Encryption requirements

- TLS versions / cipher suites
- Encryption at rest
- Key management
MD;
    }

    private static function backup(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Резервни копия

- Какво трябва да се архивира
- Честота и задържане
- Процедура за възстановяване
MD;
        }

        return <<<'MD'
## Backup

- What must be backed up
- Frequency and retention
- Restore procedure
MD;
    }

    private static function logging(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Журналиране

- Какви събития се логват
- Къде се съхраняват логовете
- Retention и защита на логове
MD;
        }

        return <<<'MD'
## Logging

- Which events are logged
- Where logs are stored
- Retention and log protection
MD;
    }

    private static function updateProcedure(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Процедура за обновяване

1. Проверка за налични обновявания
2. Предварителен backup / staging
3. Прилагане на patch / release
4. Валидация след обновяване
MD;
        }

        return <<<'MD'
## Update procedure

1. Check for available updates
2. Pre-update backup / staging
3. Apply patch / release
4. Post-update validation
MD;
    }

    private static function securityContact(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Контакт за сигурност

- Email: security@example.com
- Работно време / SLA за отговор
- PGP ключ (ако е приложимо)
MD;
        }

        return <<<'MD'
## Security contact

- Email: security@example.com
- Response hours / SLA
- PGP key (if applicable)
MD;
    }

    private static function vulnerabilityReporting(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Докладване на уязвимости

Опишете как клиенти и изследователи докладват уязвимости.

- Канал за докладване
- Каква информация да включат
- Очаквано потвърждение и процес
MD;
        }

        return <<<'MD'
## Vulnerability reporting

Describe how customers and researchers should report vulnerabilities.

- Reporting channel
- Information to include
- Expected acknowledgement and process
MD;
    }

    private static function supportPeriod(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Период на поддръжка

- Продължителност на security support
- Къде се публикува support timeline
- Как се уведомяват клиентите при промяна
MD;
        }

        return <<<'MD'
## Support period

- Duration of security support
- Where the support timeline is published
- How customers are notified of changes
MD;
    }

    private static function endOfSupportBehavior(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Поведение след край на поддръжката

- Какво спира (patching, updates, cloud features)
- Препоръки за миграция
- Рискове при продължаване на употреба
MD;
        }

        return <<<'MD'
## End-of-support behaviour

- What stops (patching, updates, cloud features)
- Migration recommendations
- Risks of continued use
MD;
    }

    private static function knownLimitations(string $locale): string
    {
        if ($locale === 'bg') {
            return <<<'MD'
## Известни ограничения

Документирайте известни security limitations и компенсиращи контроли.

- Ограничение 1 — mitigation
- Ограничение 2 — mitigation
MD;
        }

        return <<<'MD'
## Known limitations

Document known security limitations and compensating controls.

- Limitation 1 — mitigation
- Limitation 2 — mitigation
MD;
    }
}
