<?php

namespace App\Support;

use App\Enums\ControlAutomationLevel;
use App\Enums\ControlFrequency;

/**
 * Curated starter control catalogue for new organizations (CRA software baseline).
 */
final class StarterControlCatalogue
{
    /**
     * @return list<array{
     *     code: string,
     *     name: string,
     *     name_bg: string,
     *     description: string,
     *     description_bg: string,
     *     implementation_guidance: string,
     *     implementation_guidance_bg: string,
     *     automation_level: ControlAutomationLevel,
     *     frequency: ControlFrequency,
     *     requirement_codes: list<string>
     * }>
     */
    public static function items(): array
    {
        return [
            [
                'code' => 'CTL-DEP-SCAN',
                'name' => 'Dependency scanning before release',
                'name_bg' => 'Сканиране на зависимости преди release',
                'description' => 'Scan product dependencies for known vulnerabilities before each production release.',
                'description_bg' => 'Сканирайте продуктовите зависимости за известни уязвимости преди всеки production release.',
                'implementation_guidance' => 'Run SCA in CI on every release candidate; block critical findings unless accepted-risk is recorded.',
                'implementation_guidance_bg' => 'Стартирайте SCA в CI за всеки release candidate; блокирайте критични находки, освен ако е записан приет риск.',
                'automation_level' => ControlAutomationLevel::Automated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-02', 'CRA-AI-15', 'CRA-AI-16'],
            ],
            [
                'code' => 'CTL-PEER-REVIEW',
                'name' => 'Mandatory peer review',
                'name_bg' => 'Задължителен peer review',
                'description' => 'Require peer review before merging security-relevant changes.',
                'description_bg' => 'Изисквайте peer review преди merge на промени, свързани със сигурността.',
                'implementation_guidance' => 'Enforce branch protection with at least one approving review on protected branches.',
                'implementation_guidance_bg' => 'Въведете branch protection с поне едно одобряващо ревю на защитените клонове.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-01', 'CRA-AI-10'],
            ],
            [
                'code' => 'CTL-SECRETS-SCAN',
                'name' => 'Secrets scanning',
                'name_bg' => 'Сканиране за секрети',
                'description' => 'Detect secrets in source, CI logs and artifacts.',
                'description_bg' => 'Откривайте секрети в кода, CI логовете и артефактите.',
                'implementation_guidance' => 'Enable secret scanning on push and in CI; rotate any exposed credentials immediately.',
                'implementation_guidance_bg' => 'Активирайте secret scanning при push и в CI; ротирайте незабавно всеки разкрит credential.',
                'automation_level' => ControlAutomationLevel::Automated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-05', 'CRA-AI-04'],
            ],
            [
                'code' => 'CTL-SIGNED-RELEASE',
                'name' => 'Signed release packages',
                'name_bg' => 'Подписани release пакети',
                'description' => 'Sign release artifacts and publish verifiable checksums.',
                'description_bg' => 'Подписвайте release артефактите и публикувайте проверими checksum-и.',
                'implementation_guidance' => 'Sign packages with a controlled signing key; publish hashes alongside downloads.',
                'implementation_guidance_bg' => 'Подписвайте пакетите с контролиран signing ключ; публикувайте хешовете заедно с downloads.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-06', 'CRA-AI-13'],
            ],
            [
                'code' => 'CTL-SECURE-UPDATE',
                'name' => 'Secure update mechanism',
                'name_bg' => 'Сигурен механизъм за обновяване',
                'description' => 'Deliver updates over a secure channel with integrity verification.',
                'description_bg' => 'Доставяйте обновления през сигурен канал с проверка на целостта.',
                'implementation_guidance' => 'Use HTTPS/signed update payloads; document rollback for failed updates.',
                'implementation_guidance_bg' => 'Използвайте HTTPS/подписани update payloads; документирайте rollback при неуспешни обновления.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-13', 'CRA-AI-19'],
            ],
            [
                'code' => 'CTL-VULN-DISCLOSURE',
                'name' => 'Vulnerability disclosure channel',
                'name_bg' => 'Канал за оповестяване на уязвимости',
                'description' => 'Public coordinated vulnerability disclosure contact and process.',
                'description_bg' => 'Публичен контакт и процес за координирано оповестяване на уязвимости.',
                'implementation_guidance' => 'Publish security@ contact and CVD policy; acknowledge reports within defined SLA.',
                'implementation_guidance_bg' => 'Публикувайте security@ контакт и CVD политика; потвърждавайте докладите в рамките на дефиниран SLA.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-12', 'CRA-AI-18'],
            ],
            [
                'code' => 'CTL-SUPPORTED-VERSIONS',
                'name' => 'Supported-version inventory',
                'name_bg' => 'Инвентар на поддържаните версии',
                'description' => 'Maintain inventory of supported product versions during the support period.',
                'description_bg' => 'Поддържайте инвентар на поддържаните продуктови версии през периода на поддръжка.',
                'implementation_guidance' => 'Keep a living list of supported versions and communicate EOS dates to customers.',
                'implementation_guidance_bg' => 'Поддържайте актуален списък на поддържаните версии и комуникирайте EOS датите към клиентите.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-19', 'CRA-AI-16'],
            ],
            [
                'code' => 'CTL-SEC-REGRESSION',
                'name' => 'Security regression testing',
                'name_bg' => 'Security regression тестове',
                'description' => 'Run security-focused regression tests before release.',
                'description_bg' => 'Изпълнявайте security-фокусирани regression тестове преди release.',
                'implementation_guidance' => 'Include auth, input validation and update-path tests in the release gate.',
                'implementation_guidance_bg' => 'Включете тестове за auth, input validation и update пътя в release gate-а.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-01', 'CRA-AI-04', 'CRA-AI-08'],
            ],
            [
                'code' => 'CTL-BACKUP-RESTORE',
                'name' => 'Backup and restore testing',
                'name_bg' => 'Тестване на backup и restore',
                'description' => 'Periodically test backup and restore for critical product data/services.',
                'description_bg' => 'Периодично тествайте backup и restore за критични продуктови данни/услуги.',
                'implementation_guidance' => 'Schedule restore drills; record RTO/RPO outcomes.',
                'implementation_guidance_bg' => 'Планирайте restore упражнения; записвайте RTO/RPO резултатите.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-08'],
            ],
            [
                'code' => 'CTL-NO-DEFAULT-CREDS',
                'name' => 'Prohibition of default credentials',
                'name_bg' => 'Забрана на credentials по подразбиране',
                'description' => 'Do not ship default or shared credentials.',
                'description_bg' => 'Не доставяйте credentials по подразбиране или споделени credentials.',
                'implementation_guidance' => 'Force unique credentials or first-login setup; include check in release checklist.',
                'implementation_guidance_bg' => 'Изисквайте уникални credentials или setup при първи вход; включете проверка в release checklist.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-03', 'CRA-AI-04'],
            ],
            [
                'code' => 'CTL-RELEASE-APPROVAL',
                'name' => 'Release approval workflow',
                'name_bg' => 'Работен процес за одобрение на release',
                'description' => 'Require documented approval before production release.',
                'description_bg' => 'Изисквайте документирано одобрение преди production release.',
                'implementation_guidance' => 'Capture approver, checklist results and security gate outcomes in the release record.',
                'implementation_guidance_bg' => 'Записвайте одобряващия, резултатите от checklist и security gate в release записа.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::PerRelease,
                'requirement_codes' => ['CRA-AI-02', 'CRA-AI-16'],
            ],
            [
                'code' => 'CTL-INCIDENT-ESC',
                'name' => 'Incident escalation',
                'name_bg' => 'Ескалация на инциденти',
                'description' => 'Defined escalation path for security incidents affecting the product.',
                'description_bg' => 'Дефиниран път за ескалация при security инциденти, засягащи продукта.',
                'implementation_guidance' => 'Document severity levels, contacts and notification steps; rehearse annually.',
                'implementation_guidance_bg' => 'Документирайте нива на тежест, контакти и стъпки за уведомяване; упражнявайте годишно.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::OnDemand,
                'requirement_codes' => ['CRA-AI-11', 'CRA-AI-12'],
            ],
            [
                'code' => 'CTL-KEY-ROTATION',
                'name' => 'Cryptographic key rotation',
                'name_bg' => 'Ротация на криптографски ключове',
                'description' => 'Rotate signing and encryption keys on a defined schedule and after incidents.',
                'description_bg' => 'Ротирайте signing и encryption ключовете по дефиниран график и след инциденти.',
                'implementation_guidance' => 'Inventory keys; set rotation cadence; record custody and rotation events.',
                'implementation_guidance_bg' => 'Инвентаризирайте ключовете; задайте ритъм на ротация; записвайте custody и ротациите.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-05', 'CRA-AI-06', 'CRA-AI-13'],
            ],
            [
                'code' => 'CTL-SECURE-LOGGING',
                'name' => 'Secure logging',
                'name_bg' => 'Сигурно логване',
                'description' => 'Log security-relevant events without exposing sensitive data.',
                'description_bg' => 'Логвайте security-релевантни събития без да разкривате чувствителни данни.',
                'implementation_guidance' => 'Define event catalogue, retention and masking rules; verify in staging.',
                'implementation_guidance_bg' => 'Дефинирайте каталог на събитията, retention и правила за маскиране; проверете в staging.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-20', 'CRA-AI-05'],
            ],
            [
                'code' => 'CTL-INPUT-VALIDATION',
                'name' => 'Input validation',
                'name_bg' => 'Валидация на входни данни',
                'description' => 'Validate and sanitise untrusted input at trust boundaries.',
                'description_bg' => 'Валидирайте и санитизирайте недоверени входни данни на trust boundaries.',
                'implementation_guidance' => 'Centralise validation helpers; cover APIs and forms with negative tests.',
                'implementation_guidance_bg' => 'Централизирайте validation helpers; покрийте API и форми с negative тестове.',
                'automation_level' => ControlAutomationLevel::SemiAutomated,
                'frequency' => ControlFrequency::Continuous,
                'requirement_codes' => ['CRA-AI-06', 'CRA-AI-10'],
            ],
            [
                'code' => 'CTL-ACCESS-REVIEW',
                'name' => 'Access-control review',
                'name_bg' => 'Преглед на контрола на достъпа',
                'description' => 'Periodically review product and admin access rights.',
                'description_bg' => 'Периодично преглеждайте правата за достъп до продукта и административните права.',
                'implementation_guidance' => 'Review roles and privileged accounts on a schedule; remove unused access.',
                'implementation_guidance_bg' => 'Преглеждайте роли и привилегировани акаунти по график; премахвайте неизползван достъп.',
                'automation_level' => ControlAutomationLevel::Manual,
                'frequency' => ControlFrequency::Periodic,
                'requirement_codes' => ['CRA-AI-04'],
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allLinkedRequirementCodes(): array
    {
        return collect(self::items())
            ->flatMap(fn(array $item) => $item['requirement_codes'])
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Resolve catalogue item content for a single organization locale.
     *
     * @param  array{
     *     code: string,
     *     name: string,
     *     name_bg: string,
     *     description: string,
     *     description_bg: string,
     *     implementation_guidance: string,
     *     implementation_guidance_bg: string,
     *     automation_level: ControlAutomationLevel,
     *     frequency: ControlFrequency,
     *     requirement_codes: list<string>
     * }  $item
     * @return array{
     *     code: string,
     *     name: string,
     *     description: string,
     *     implementation_guidance: string,
     *     automation_level: ControlAutomationLevel,
     *     frequency: ControlFrequency,
     *     requirement_codes: list<string>
     * }
     */
    public static function localizedItem(array $item, string $locale): array
    {
        $useBg = $locale === 'bg';

        return [
            'code' => $item['code'],
            'name' => $useBg ? $item['name_bg'] : $item['name'],
            'description' => $useBg ? $item['description_bg'] : $item['description'],
            'implementation_guidance' => $useBg
                ? $item['implementation_guidance_bg']
                : $item['implementation_guidance'],
            'automation_level' => $item['automation_level'],
            'frequency' => $item['frequency'],
            'requirement_codes' => $item['requirement_codes'],
        ];
    }
}
