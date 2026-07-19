<?php

namespace Database\Seeders;

use App\Models\Regulation;
use App\Models\Requirement;
use App\Models\RequirementVersion;
use Illuminate\Database\Seeder;

class RequirementCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        $regulation = Regulation::query()->updateOrCreate(
            ['code' => 'CRA-2024-2847'],
            [
                'title' => 'Cyber Resilience Act — Regulation (EU) 2024/2847',
                'jurisdiction' => 'EU',
            ],
        );

        $items = $this->starterRequirements();

        foreach ($items as $index => $item) {
            $requirement = Requirement::query()->updateOrCreate(
                [
                    'regulation_id' => $regulation->id,
                    'code' => $item['code'],
                ],
                [
                    'article_ref' => $item['article_ref'],
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ],
            );

            $content = $this->versionContent($item);

            $existingCurrent = RequirementVersion::query()
                ->where('requirement_id', $requirement->id)
                ->where('is_current', true)
                ->first();

            if ($existingCurrent !== null) {
                $existingCurrent->update($content);

                continue;
            }

            RequirementVersion::query()->create([
                'requirement_id' => $requirement->id,
                'version' => 1,
                ...$content,
                'published_at' => now(),
                'is_current' => true,
            ]);
        }
    }

    /**
     * @param  array<string, string>  $item
     * @return array<string, string|null>
     */
    private function versionContent(array $item): array
    {
        return [
            'requirement_text' => $item['requirement_text'],
            'requirement_text_bg' => $item['requirement_text_bg'],
            'plain_language' => $item['plain_language'],
            'plain_language_bg' => $item['plain_language_bg'],
            'applicability_notes' => $item['applicability_notes'],
            'applicability_notes_bg' => $item['applicability_notes_bg'],
            'suggested_controls_text' => $item['suggested_controls_text'],
            'suggested_controls_text_bg' => $item['suggested_controls_text_bg'],
            'required_evidence_text' => $item['required_evidence_text'],
            'required_evidence_text_bg' => $item['required_evidence_text_bg'],
        ];
    }

    /**
     * Curated starter set (not a full legal corpus).
     *
     * @return list<array<string, string>>
     */
    private function starterRequirements(): array
    {
        return [
            [
                'code' => 'CRA-AI-01',
                'article_ref' => 'Annex I Part I (1)',
                'requirement_text' => 'Products with digital elements shall be designed, developed and produced in such a way that they ensure an appropriate level of cybersecurity based on the risks.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да бъдат проектирани, разработени и произведени така, че да осигуряват подходящо ниво на киберсигурност въз основа на рисковете.',
                'plain_language' => 'Design and build the product with cybersecurity proportionate to its risks.',
                'plain_language_bg' => 'Проектирайте и изградете продукта с киберсигурност, съобразена с рисковете му.',
                'applicability_notes' => 'Applies to products with digital elements placed on the EU market.',
                'applicability_notes_bg' => 'Прилага се за продукти с цифрови елементи, пуснати на пазара на ЕС.',
                'suggested_controls_text' => "Secure design reviews\nThreat modelling\nSecurity requirements in backlog",
                'suggested_controls_text_bg' => "Прегледи на сигурния дизайн\nМоделиране на заплахи\nИзисквания за сигурност в backlog",
                'required_evidence_text' => "Threat model\nSecurity design review records\nRisk assessment summary",
                'required_evidence_text_bg' => "Модел на заплахите\nЗаписи от преглед на сигурния дизайн\nОбобщение на оценката на риска",
            ],
            [
                'code' => 'CRA-AI-02',
                'article_ref' => 'Annex I Part I (2)',
                'requirement_text' => 'Products with digital elements shall be delivered without any known exploitable vulnerabilities.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да се доставят без известни експлоатабилни уязвимости.',
                'plain_language' => 'Do not ship with known exploitable vulnerabilities.',
                'plain_language_bg' => 'Не пускайте продукт с известни експлоатабилни уязвимости.',
                'applicability_notes' => 'Applies at the time of placing on the market / making available.',
                'applicability_notes_bg' => 'Прилага се към момента на пускане на пазара / предоставяне.',
                'suggested_controls_text' => "Dependency scanning before release\nVulnerability triage gate\nAccepted-risk decision workflow",
                'suggested_controls_text_bg' => "Сканиране на зависимости преди release\nГейт за triage на уязвимости\nРаботен процес за приети рискове",
                'required_evidence_text' => "SBOM\nDependency scan report\nSecurity review / accepted-risk decision",
                'required_evidence_text_bg' => "SBOM\nДоклад от сканиране на зависимости\nSecurity преглед / решение за приет риск",
            ],
            [
                'code' => 'CRA-AI-03',
                'article_ref' => 'Annex I Part I (2)(a)',
                'requirement_text' => 'Products with digital elements shall be based on an appropriate level of security by default configuration.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да се основават на подходящо ниво на сигурност чрез конфигурация по подразбиране.',
                'plain_language' => 'Ship secure defaults; avoid insecure out-of-the-box settings.',
                'plain_language_bg' => 'Доставяйте сигурни настройки по подразбиране; избягвайте несигурни out-of-the-box настройки.',
                'applicability_notes' => 'Especially relevant for installable software and appliances.',
                'applicability_notes_bg' => 'Особено релевантно за инсталируем софтуер и appliances.',
                'suggested_controls_text' => "Secure default configuration checklist\nProhibit default credentials\nHardening guide for operators",
                'suggested_controls_text_bg' => "Чеклист за сигурна конфигурация по подразбиране\nЗабрана на default credentials\nРъководство за hardening за оператори",
                'required_evidence_text' => "Default config baseline\nHardening documentation\nRelease checklist",
                'required_evidence_text_bg' => "Базова конфигурация по подразбиране\nДокументация за hardening\nRelease чеклист",
            ],
            [
                'code' => 'CRA-AI-04',
                'article_ref' => 'Annex I Part I (2)(b)',
                'requirement_text' => 'Products with digital elements shall ensure protection from unauthorised access by appropriate control mechanisms.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да осигуряват защита от неоторизиран достъп чрез подходящи контролни механизми.',
                'plain_language' => 'Protect against unauthorised access with suitable authentication and access controls.',
                'plain_language_bg' => 'Защитете срещу неоторизиран достъп с подходяща автентикация и контрол на достъпа.',
                'applicability_notes' => 'Applies where the product exposes interfaces or accounts.',
                'applicability_notes_bg' => 'Прилага се, когато продуктът предоставя интерфейси или акаунти.',
                'suggested_controls_text' => "Authentication controls\nAccess-control reviews\nLeast-privilege defaults",
                'suggested_controls_text_bg' => "Контроли за автентикация\nПрегледи на контрола на достъпа\nLeast-privilege по подразбиране",
                'required_evidence_text' => "Access-control design\nAuth test results\nRole matrix",
                'required_evidence_text_bg' => "Дизайн на контрола на достъпа\nРезултати от auth тестове\nМатрица на ролите",
            ],
            [
                'code' => 'CRA-AI-05',
                'article_ref' => 'Annex I Part I (2)(c)',
                'requirement_text' => 'Products with digital elements shall protect the confidentiality of stored, transmitted or otherwise processed data.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да защитават поверителността на съхраняваните, предаваните или обработваните по друг начин данни.',
                'plain_language' => 'Protect confidentiality of data at rest and in transit.',
                'plain_language_bg' => 'Защитете поверителността на данните в покой и при пренос.',
                'applicability_notes' => 'Applies where personal or sensitive product data is processed.',
                'applicability_notes_bg' => 'Прилага се, когато се обработват лични или чувствителни продуктови данни.',
                'suggested_controls_text' => "TLS for network traffic\nEncryption at rest where needed\nSecrets management",
                'suggested_controls_text_bg' => "TLS за мрежовия трафик\nКриптиране в покой при нужда\nУправление на секрети",
                'required_evidence_text' => "Crypto inventory\nTLS configuration evidence\nSecrets handling policy",
                'required_evidence_text_bg' => "Крипто инвентар\nДоказателство за TLS конфигурация\nПолитика за работа със секрети",
            ],
            [
                'code' => 'CRA-AI-06',
                'article_ref' => 'Annex I Part I (2)(d)',
                'requirement_text' => 'Products with digital elements shall protect the integrity of stored, transmitted or otherwise processed data.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да защитават целостта на съхраняваните, предаваните или обработваните по друг начин данни.',
                'plain_language' => 'Protect integrity of data and software packages.',
                'plain_language_bg' => 'Защитете целостта на данните и софтуерните пакети.',
                'applicability_notes' => 'Applies to configuration, updates and processed data.',
                'applicability_notes_bg' => 'Прилага се за конфигурация, обновления и обработвани данни.',
                'suggested_controls_text' => "Signed release packages\nChecksum verification\nInput validation",
                'suggested_controls_text_bg' => "Подписани release пакети\nПроверка на checksum\nВалидация на входни данни",
                'required_evidence_text' => "Signing process docs\nArtifact hashes\nIntegrity test records",
                'required_evidence_text_bg' => "Документация на процеса по подписване\nХешове на артефакти\nЗаписи от тестове за цялост",
            ],
            [
                'code' => 'CRA-AI-07',
                'article_ref' => 'Annex I Part I (2)(e)',
                'requirement_text' => 'Products with digital elements shall process only data that are adequate, relevant and limited to what is necessary.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да обработват само данни, които са адекватни, релевантни и ограничени до необходимото.',
                'plain_language' => 'Minimise data collection and processing to what the product needs.',
                'plain_language_bg' => 'Минимизирайте събирането и обработката на данни до нужното за продукта.',
                'applicability_notes' => 'Aligns with data minimisation good practice.',
                'applicability_notes_bg' => 'В съответствие с добрите практики за минимизиране на данни.',
                'suggested_controls_text' => "Data inventory\nPurpose limitation review\nRetention limits",
                'suggested_controls_text_bg' => "Инвентар на данните\nПреглед за ограничение по цел\nЛимити за съхранение",
                'required_evidence_text' => "Data flow diagram\nRetention policy\nPrivacy review notes",
                'required_evidence_text_bg' => "Диаграма на потоците от данни\nПолитика за съхранение\nБележки от privacy преглед",
            ],
            [
                'code' => 'CRA-AI-08',
                'article_ref' => 'Annex I Part I (2)(f)',
                'requirement_text' => 'Products with digital elements shall protect the availability of essential functions.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да защитават наличността на съществените функции.',
                'plain_language' => 'Keep essential functions available under expected conditions and attacks.',
                'plain_language_bg' => 'Поддържайте съществените функции налични при очаквани условия и атаки.',
                'applicability_notes' => 'Critical for products supporting essential operations.',
                'applicability_notes_bg' => 'Критично за продукти, поддържащи съществени операции.',
                'suggested_controls_text' => "Availability/resilience testing\nRate limiting\nBackup and restore testing",
                'suggested_controls_text_bg' => "Тестове за наличност/устойчивост\nRate limiting\nТестове за backup и възстановяване",
                'required_evidence_text' => "Availability test reports\nRestore drill records\nIncident response runbooks",
                'required_evidence_text_bg' => "Доклади от тестове за наличност\nЗаписи от restore упражнения\nRunbooks за реакция при инциденти",
            ],
            [
                'code' => 'CRA-AI-09',
                'article_ref' => 'Annex I Part I (2)(g)',
                'requirement_text' => 'Products with digital elements shall minimise their negative impact on the availability of services provided by other devices or networks.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да минимизират отрицателното си въздействие върху наличността на услуги, предоставяни от други устройства или мрежи.',
                'plain_language' => 'Avoid designs that harm availability of other devices or networks.',
                'plain_language_bg' => 'Избягвайте дизайни, които влошават наличността на други устройства или мрежи.',
                'applicability_notes' => 'Relevant for networked products and plugins.',
                'applicability_notes_bg' => 'Релевантно за мрежови продукти и плъгини.',
                'suggested_controls_text' => "Network behaviour review\nResource usage limits\nCompatibility testing",
                'suggested_controls_text_bg' => "Преглед на мрежовото поведение\nЛимити за използване на ресурси\nТестове за съвместимост",
                'required_evidence_text' => "Network impact assessment\nLoad test notes",
                'required_evidence_text_bg' => "Оценка на мрежовото въздействие\nБележки от load тестове",
            ],
            [
                'code' => 'CRA-AI-10',
                'article_ref' => 'Annex I Part I (2)(h)',
                'requirement_text' => 'Products with digital elements shall be designed, developed and produced to limit attack surfaces.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да бъдат проектирани, разработени и произведени така, че да ограничават повърхността за атаки.',
                'plain_language' => 'Reduce attack surface: disable unused interfaces and features by default.',
                'plain_language_bg' => 'Намалете повърхността за атаки: деактивирайте неизползвани интерфейси и функции по подразбиране.',
                'applicability_notes' => 'Applies broadly to software and devices with interfaces.',
                'applicability_notes_bg' => 'Прилага се широко за софтуер и устройства с интерфейси.',
                'suggested_controls_text' => "Attack-surface inventory\nFeature flags / disable unused services\nPort exposure review",
                'suggested_controls_text_bg' => "Инвентар на повърхността за атаки\nFeature flags / деактивиране на неизползвани услуги\nПреглед на отворените портове",
                'required_evidence_text' => "Attack-surface inventory\nHardening checklist",
                'required_evidence_text_bg' => "Инвентар на повърхността за атаки\nЧеклист за hardening",
            ],
            [
                'code' => 'CRA-AI-11',
                'article_ref' => 'Annex I Part I (2)(i)',
                'requirement_text' => 'Products with digital elements shall be designed to reduce the impact of an incident through appropriate exploitation mitigation mechanisms.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да бъдат проектирани така, че да намаляват въздействието на инцидент чрез подходящи механизми за смекчаване на експлоатацията.',
                'plain_language' => 'Include mitigations that limit damage if a vulnerability is exploited.',
                'plain_language_bg' => 'Включете мерки, които ограничават щетите при експлоатация на уязвимост.',
                'applicability_notes' => 'E.g. sandboxing, privilege separation, fail-safe behaviour.',
                'applicability_notes_bg' => 'Напр. sandboxing, разделяне на привилегии, fail-safe поведение.',
                'suggested_controls_text' => "Privilege separation\nSandboxing / process isolation\nFail-safe defaults",
                'suggested_controls_text_bg' => "Разделяне на привилегии\nSandboxing / изолация на процеси\nFail-safe настройки по подразбиране",
                'required_evidence_text' => "Architecture decision records\nMitigation test results",
                'required_evidence_text_bg' => "Architecture decision records\nРезултати от тестове на mitigations",
            ],
            [
                'code' => 'CRA-AI-12',
                'article_ref' => 'Annex I Part I (2)(j)',
                'requirement_text' => 'Products with digital elements shall provide security related information through exploitation of vulnerabilities and coordinated vulnerability disclosure.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да осигуряват информация, свързана със сигурността, чрез експлоатация на уязвимости и координирано оповестяване на уязвимости.',
                'plain_language' => 'Support coordinated vulnerability disclosure and share security-relevant information.',
                'plain_language_bg' => 'Поддържайте координирано оповестяване на уязвимости и споделяйте релевантна за сигурността информация.',
                'applicability_notes' => 'Manufacturer disclosure channel and process.',
                'applicability_notes_bg' => 'Канал и процес за оповестяване от производителя.',
                'suggested_controls_text' => "Public vulnerability disclosure policy\nSecurity contact\nTriage SLA",
                'suggested_controls_text_bg' => "Публична политика за оповестяване на уязвимости\nSecurity контакт\nSLA за triage",
                'required_evidence_text' => "Vulnerability disclosure policy\nSecurity contact page\nTriage records",
                'required_evidence_text_bg' => "Политика за оповестяване на уязвимости\nСтраница със security контакт\nЗаписи от triage",
            ],
            [
                'code' => 'CRA-AI-13',
                'article_ref' => 'Annex I Part I (2)(k)',
                'requirement_text' => 'Products with digital elements shall provide for secure and, where relevant, automatic updates and secure delivery of updates.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да осигуряват сигурни и, където е уместно, автоматични обновления, както и сигурна доставка на обновленията.',
                'plain_language' => 'Provide a secure update mechanism for the support period.',
                'plain_language_bg' => 'Осигурете сигурен механизъм за обновления през периода на поддръжка.',
                'applicability_notes' => 'Applies where the product can receive updates.',
                'applicability_notes_bg' => 'Прилага се, когато продуктът може да получава обновления.',
                'suggested_controls_text' => "Signed updates\nSecure update channel\nUpdate rollback strategy",
                'suggested_controls_text_bg' => "Подписани обновления\nСигурен канал за обновления\nСтратегия за rollback на обновления",
                'required_evidence_text' => "Update architecture\nSigning keys custody\nUpdate test evidence",
                'required_evidence_text_bg' => "Архитектура на обновленията\nСъхранение на ключовете за подписване\nДоказателства от тестове на обновления",
            ],
            [
                'code' => 'CRA-AI-14',
                'article_ref' => 'Annex I Part I (2)(l)',
                'requirement_text' => 'Products with digital elements shall be designed, developed and produced to securely reset to factory default state while preserving security settings where appropriate.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва да бъдат проектирани, разработени и произведени така, че сигурно да се връщат към заводски настройки, като запазват настройките за сигурност, където е уместно.',
                'plain_language' => 'Support secure factory reset without leaving the product insecure.',
                'plain_language_bg' => 'Поддържайте сигурен factory reset, без продуктът да остава несигурен.',
                'applicability_notes' => 'More relevant for devices/appliances; assess for software products case by case.',
                'applicability_notes_bg' => 'По-релевантно за устройства/appliances; за софтуерни продукти оценявайте случай по случай.',
                'suggested_controls_text' => "Documented reset procedure\nPreserve essential security settings\nPost-reset hardening",
                'suggested_controls_text_bg' => "Документирана процедура за reset\nЗапазване на съществени настройки за сигурност\nHardening след reset",
                'required_evidence_text' => "Reset procedure docs\nTest of reset behaviour",
                'required_evidence_text_bg' => "Документация на процедурата за reset\nТест на поведението при reset",
            ],
            [
                'code' => 'CRA-AI-15',
                'article_ref' => 'Annex I Part II (1)',
                'requirement_text' => 'Manufacturers shall identify and document vulnerabilities and components contained in the product, including by drawing up an SBOM.',
                'requirement_text_bg' => 'Производителите трябва да идентифицират и документират уязвимостите и компонентите, съдържащи се в продукта, включително чрез изготвяне на SBOM.',
                'plain_language' => 'Maintain an SBOM and track vulnerabilities in product components.',
                'plain_language_bg' => 'Поддържайте SBOM и проследявайте уязвимостите в компонентите на продукта.',
                'applicability_notes' => 'Core manufacturer obligation for products with digital elements.',
                'applicability_notes_bg' => 'Основно задължение на производителя за продукти с цифрови елементи.',
                'suggested_controls_text' => "SBOM generation in CI\nComponent inventory\nVulnerability matching workflow",
                'suggested_controls_text_bg' => "Генериране на SBOM в CI\nИнвентар на компонентите\nРаботен процес за съпоставяне на уязвимости",
                'required_evidence_text' => "SBOM (CycloneDX/SPDX)\nComponent inventory\nVulnerability register entries",
                'required_evidence_text_bg' => "SBOM (CycloneDX/SPDX)\nИнвентар на компонентите\nЗаписи в регистъра на уязвимостите",
            ],
            [
                'code' => 'CRA-AI-16',
                'article_ref' => 'Annex I Part II (2)',
                'requirement_text' => 'Manufacturers shall address and remediate vulnerabilities without delay, including by providing security updates.',
                'requirement_text_bg' => 'Производителите трябва да адресират и отстраняват уязвимостите без забавяне, включително чрез предоставяне на security обновления.',
                'plain_language' => 'Remediate vulnerabilities promptly and ship security updates.',
                'plain_language_bg' => 'Отстранявайте уязвимостите своевременно и издавайте security обновления.',
                'applicability_notes' => 'Tied to support period and severity.',
                'applicability_notes_bg' => 'Свързано с периода на поддръжка и тежестта.',
                'suggested_controls_text' => "Vulnerability SLA\nPatch release process\nSecurity-only hotfix path",
                'suggested_controls_text_bg' => "SLA за уязвимости\nПроцес за patch release\nПът за security-only hotfix",
                'required_evidence_text' => "Remediation tickets\nSecurity release notes\nSLA metrics",
                'required_evidence_text_bg' => "Тикети за отстраняване\nSecurity release notes\nSLA метрики",
            ],
            [
                'code' => 'CRA-AI-17',
                'article_ref' => 'Annex I Part II (5)',
                'requirement_text' => 'Manufacturers shall provide information relating to cybersecurity of the product and how to securely install, configure and operate it.',
                'requirement_text_bg' => 'Производителите трябва да предоставят информация относно киберсигурността на продукта и как да се инсталира, конфигурира и експлоатира сигурно.',
                'plain_language' => 'Publish clear security installation, configuration and operation guidance.',
                'plain_language_bg' => 'Публикувайте ясни указания за сигурна инсталация, конфигурация и експлоатация.',
                'applicability_notes' => 'User documentation obligation.',
                'applicability_notes_bg' => 'Задължение за потребителска документация.',
                'suggested_controls_text' => "Security hardening guide\nInstall/config docs review\nRelease notes for security settings",
                'suggested_controls_text_bg' => "Ръководство за security hardening\nПреглед на install/config документацията\nRelease notes за настройки за сигурност",
                'required_evidence_text' => "User security documentation\nDoc review checklist",
                'required_evidence_text_bg' => "Потребителска документация за сигурност\nЧеклист за преглед на документацията",
            ],
            [
                'code' => 'CRA-AI-18',
                'article_ref' => 'Annex I Part II (6)',
                'requirement_text' => 'Manufacturers shall provide for coordinated vulnerability disclosure policies and processes.',
                'requirement_text_bg' => 'Производителите трябва да осигурят политики и процеси за координирано оповестяване на уязвимости.',
                'plain_language' => 'Have a documented coordinated vulnerability disclosure process.',
                'plain_language_bg' => 'Имайте документиран процес за координирано оповестяване на уязвимости.',
                'applicability_notes' => 'Complements Annex I Part I (2)(j).',
                'applicability_notes_bg' => 'Допълва Annex I Part I (2)(j).',
                'suggested_controls_text' => "CVD policy published\nIntake mailbox / form\nAcknowledgement timelines",
                'suggested_controls_text_bg' => "Публикувана CVD политика\nMailbox / форма за прием\nСрокове за потвърждение",
                'required_evidence_text' => "Published CVD policy\nIntake logs\nProcess SOP",
                'required_evidence_text_bg' => "Публикувана CVD политика\nЛогове за прием\nSOP на процеса",
            ],
            [
                'code' => 'CRA-AI-19',
                'article_ref' => 'Art. 13 / support period',
                'requirement_text' => 'Manufacturers shall ensure that vulnerabilities are handled effectively and that security updates are available during the support period.',
                'requirement_text_bg' => 'Производителите трябва да гарантират, че уязвимостите се обработват ефективно и че security обновленията са налични през периода на поддръжка.',
                'plain_language' => 'Define and honour a support period with security updates.',
                'plain_language_bg' => 'Определете и спазвайте период на поддръжка със security обновления.',
                'applicability_notes' => 'Link to product support period policy.',
                'applicability_notes_bg' => 'Свързано с политиката за период на поддръжка на продукта.',
                'suggested_controls_text' => "Documented support period\nSupported-version inventory\nEOS communication plan",
                'suggested_controls_text_bg' => "Документиран период на поддръжка\nИнвентар на поддържаните версии\nПлан за EOS комуникация",
                'required_evidence_text' => "Support period statement\nSupported versions list\nCustomer notifications",
                'required_evidence_text_bg' => "Декларация за период на поддръжка\nСписък с поддържани версии\nИзвестия към клиенти",
            ],
            [
                'code' => 'CRA-AI-20',
                'article_ref' => 'Annex I Part II (logging)',
                'requirement_text' => 'Products with digital elements shall, where appropriate, record and monitor relevant internal activity and enable security event logging.',
                'requirement_text_bg' => 'Продуктите с цифрови елементи трябва, когато е уместно, да записват и наблюдават релевантна вътрешна активност и да осигуряват логване на security събития.',
                'plain_language' => 'Provide appropriate security logging and monitoring capabilities.',
                'plain_language_bg' => 'Осигурете подходящи възможности за security логване и мониторинг.',
                'applicability_notes' => 'Assess appropriateness based on product type and risk.',
                'applicability_notes_bg' => 'Оценете уместността според типа продукт и риска.',
                'suggested_controls_text' => "Security event logging\nLog integrity / retention guidance\nAlerting for critical events",
                'suggested_controls_text_bg' => "Логване на security събития\nУказания за цялост / съхранение на логове\nАлертиране за критични събития",
                'required_evidence_text' => "Logging design\nSample log events\nRetention policy",
                'required_evidence_text_bg' => "Дизайн на логването\nПримерни log събития\nПолитика за съхранение",
            ],
        ];
    }
}
