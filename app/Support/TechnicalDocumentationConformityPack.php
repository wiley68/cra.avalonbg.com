<?php

namespace App\Support;

use App\Enums\TechnicalDocumentationSectionKey;

final class TechnicalDocumentationConformityPack
{
    public const CHECKLIST_KIND = 'conformity_assessment_checklist';

    public const DECLARATION_KIND = 'declaration_of_conformity_fields';

    /**
     * @return list<string>
     */
    public static function checklistItemKeys(): array
    {
        return [
            'product_class_decided',
            'procedure_selected',
            'self_assessment_vs_notified_body',
            'essential_requirements_mapped',
            'tech_doc_package_complete',
            'usi_published_linked',
            'sdl_run_referenced',
            'evidence_freshness_reviewed',
        ];
    }

    /**
     * @return list<string>
     */
    public static function declarationFieldKeys(): array
    {
        return [
            'manufacturer_name',
            'manufacturer_address',
            'product_name',
            'product_type_version',
            'applicable_legislation',
            'standards_applied',
            'conformity_assessment_procedure',
            'notified_body_name_number',
            'signatory_name_role',
            'place_date',
            'additional_info',
        ];
    }

    /**
     * @return list<string>
     */
    public static function requiredDeclarationFields(): array
    {
        return [
            'manufacturer_name',
            'product_name',
            'signatory_name_role',
            'place_date',
        ];
    }

    public static function supports(TechnicalDocumentationSectionKey $key): bool
    {
        return in_array($key, [
            TechnicalDocumentationSectionKey::ConformityAssessmentPath,
            TechnicalDocumentationSectionKey::DeclarationInformation,
        ], true);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function defaultPayloadFor(TechnicalDocumentationSectionKey $key): ?array
    {
        return match ($key) {
            TechnicalDocumentationSectionKey::ConformityAssessmentPath => self::defaultChecklistPayload(),
            TechnicalDocumentationSectionKey::DeclarationInformation => self::defaultDeclarationPayload(),
            default => null,
        };
    }

    /**
     * @return array{
     *     kind: string,
     *     items: list<array{key: string, done: bool, notes: string}>,
     *     path_summary: string
     * }
     */
    public static function defaultChecklistPayload(): array
    {
        return [
            'kind' => self::CHECKLIST_KIND,
            'items' => array_map(
                fn(string $key): array => [
                    'key' => $key,
                    'done' => false,
                    'notes' => '',
                ],
                self::checklistItemKeys(),
            ),
            'path_summary' => '',
        ];
    }

    /**
     * @return array{
     *     kind: string,
     *     fields: array<string, string>,
     *     reviewed: bool
     * }
     */
    public static function defaultDeclarationPayload(): array
    {
        $fields = [];
        foreach (self::declarationFieldKeys() as $key) {
            $fields[$key] = $key === 'applicable_legislation'
                ? 'CRA (EU) 2024/2847'
                : '';
        }

        return [
            'kind' => self::DECLARATION_KIND,
            'fields' => $fields,
            'reviewed' => false,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array<string, mixed>
     */
    public static function normalize(
        TechnicalDocumentationSectionKey $key,
        ?array $payload,
    ): array {
        return match ($key) {
            TechnicalDocumentationSectionKey::ConformityAssessmentPath => self::normalizeChecklist($payload),
            TechnicalDocumentationSectionKey::DeclarationInformation => self::normalizeDeclaration($payload),
            default => $payload ?? [],
        };
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{
     *     kind: string,
     *     items: list<array{key: string, done: bool, notes: string}>,
     *     path_summary: string
     * }
     */
    public static function normalizeChecklist(?array $payload): array
    {
        $defaults = self::defaultChecklistPayload();
        $incomingItems = collect(is_array($payload['items'] ?? null) ? $payload['items'] : [])
            ->keyBy(fn($item) => is_array($item) ? (string) ($item['key'] ?? '') : '');

        $items = [];
        foreach (self::checklistItemKeys() as $key) {
            $item = $incomingItems->get($key);
            $items[] = [
                'key' => $key,
                'done' => is_array($item) ? (bool) ($item['done'] ?? false) : false,
                'notes' => is_array($item)
                    ? trim((string) ($item['notes'] ?? ''))
                    : '',
            ];
        }

        return [
            'kind' => self::CHECKLIST_KIND,
            'items' => $items,
            'path_summary' => trim((string) ($payload['path_summary'] ?? $defaults['path_summary'])),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return array{
     *     kind: string,
     *     fields: array<string, string>,
     *     reviewed: bool
     * }
     */
    public static function normalizeDeclaration(?array $payload): array
    {
        $defaults = self::defaultDeclarationPayload();
        $incoming = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
        $fields = [];

        foreach (self::declarationFieldKeys() as $key) {
            $fields[$key] = array_key_exists($key, $incoming)
                ? trim((string) $incoming[$key])
                : $defaults['fields'][$key];
        }

        return [
            'kind' => self::DECLARATION_KIND,
            'fields' => $fields,
            'reviewed' => (bool) ($payload['reviewed'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function toMarkdown(
        TechnicalDocumentationSectionKey $key,
        array $payload,
        ?string $locale = null,
    ): string {
        return match ($key) {
            TechnicalDocumentationSectionKey::ConformityAssessmentPath => self::checklistToMarkdown(
                self::normalizeChecklist($payload),
                $locale,
            ),
            TechnicalDocumentationSectionKey::DeclarationInformation => self::declarationToMarkdown(
                self::normalizeDeclaration($payload),
                $locale,
            ),
            default => '',
        };
    }

    /**
     * @param  array{
     *     kind: string,
     *     items: list<array{key: string, done: bool, notes: string}>,
     *     path_summary: string
     * }  $payload
     */
    public static function checklistToMarkdown(array $payload, ?string $locale = null): string
    {
        $lines = [
            '# ' . Translations::get('products.technical_documentation.conformity_checklist.title', [], $locale),
            '',
            '> ' . Translations::get('products.technical_documentation.conformity_checklist.disclaimer', [], $locale),
            '',
        ];

        if ($payload['path_summary'] !== '') {
            $lines[] = '## ' . Translations::get(
                'products.technical_documentation.conformity_checklist.path_summary',
                [],
                $locale,
            );
            $lines[] = '';
            $lines[] = $payload['path_summary'];
            $lines[] = '';
        }

        $lines[] = '## ' . Translations::get(
            'products.technical_documentation.conformity_checklist.items_heading',
            [],
            $locale,
        );
        $lines[] = '';

        foreach ($payload['items'] as $item) {
            $label = Translations::get(
                'products.technical_documentation.conformity_checklist.items.' . $item['key'],
                [],
                $locale,
            );
            $mark = $item['done'] ? 'x' : ' ';
            $lines[] = "- [{$mark}] {$label}";
            if ($item['notes'] !== '') {
                $lines[] = "  - {$item['notes']}";
            }
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{
     *     kind: string,
     *     fields: array<string, string>,
     *     reviewed: bool
     * }  $payload
     */
    public static function declarationToMarkdown(array $payload, ?string $locale = null): string
    {
        $lines = [
            '# ' . Translations::get('products.technical_documentation.declaration_fields.title', [], $locale),
            '',
            '> ' . Translations::get('products.technical_documentation.declaration_fields.disclaimer', [], $locale),
            '',
            '- **' . Translations::get('products.technical_documentation.declaration_fields.reviewed', [], $locale) . ':** '
            . ($payload['reviewed']
                ? Translations::get('common.yes', [], $locale)
                : Translations::get('common.no', [], $locale)),
            '',
        ];

        foreach ($payload['fields'] as $key => $value) {
            $label = Translations::get(
                'products.technical_documentation.declaration_fields.fields.' . $key,
                [],
                $locale,
            );
            $lines[] = '- **' . $label . ':** ' . ($value !== '' ? $value : '—');
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    public static function isComplete(TechnicalDocumentationSectionKey $key, ?array $payload): bool
    {
        return match ($key) {
            TechnicalDocumentationSectionKey::ConformityAssessmentPath => self::isChecklistComplete(
                self::normalizeChecklist($payload),
            ),
            TechnicalDocumentationSectionKey::DeclarationInformation => self::isDeclarationComplete(
                self::normalizeDeclaration($payload),
            ),
            default => false,
        };
    }

    /**
     * @param  array{
     *     items: list<array{key: string, done: bool, notes: string}>,
     *     path_summary: string
     * }  $payload
     */
    public static function isChecklistComplete(array $payload): bool
    {
        if (trim($payload['path_summary']) === '') {
            return false;
        }

        foreach ($payload['items'] as $item) {
            if (!$item['done']) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param  array{
     *     fields: array<string, string>,
     *     reviewed: bool
     * }  $payload
     */
    public static function isDeclarationComplete(array $payload): bool
    {
        if (!$payload['reviewed']) {
            return false;
        }

        foreach (self::requiredDeclarationFields() as $key) {
            if (trim((string) ($payload['fields'][$key] ?? '')) === '') {
                return false;
            }
        }

        return true;
    }
}
