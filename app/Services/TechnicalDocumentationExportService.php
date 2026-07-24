<?php

namespace App\Services;

use App\Enums\TechnicalDocumentationSectionSource;
use App\Models\Organization;
use App\Models\Product;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TechnicalDocumentationExportService
{
    public const FORMATS = ['markdown', 'pdf'];

    public function export(
        TechnicalDocumentationPackage $package,
        Product $product,
        Organization $organization,
        string $format,
        User $actor,
    ): Response {
        $format = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => Translations::get('products.technical_documentation.export.invalid_format'),
            ]);
        }

        $viewPayload = $this->viewPayload($package, $product, $organization);
        $filenameBase = $this->filenameBase($package, $product);

        AuditLogger::logTechnicalDocumentationExported($package, $actor, $format);

        return match ($format) {
            'pdf' => Pdf::loadView('pdf.technical-documentation', $viewPayload)
                ->setPaper('a4')
                ->stream($filenameBase . '.pdf'),
            'markdown' => response($this->toMarkdown($viewPayload), 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.md"',
            ]),
        };
    }

    /**
     * @return array{
     *     organization: array{name: string},
     *     product: array{name: string, slug: string},
     *     package: array{
     *         id: int,
     *         title: string,
     *         status: string,
     *         status_label: string,
     *         version_label: string,
     *         locale: string,
     *         locale_label: string,
     *         product_version_number: string|null,
     *         published_at: string|null,
     *         published_by_name: string|null,
     *         notes: string|null,
     *         sections: list<array{
     *             section_key: string,
     *             title: string,
     *             source: string,
     *             source_label: string,
     *             is_applicable: bool,
     *             body_markdown: string,
     *             body_html: string
     *         }>
     *     },
     *     generated_at: string
     * }
     */
    public function viewPayload(
        TechnicalDocumentationPackage $package,
        Product $product,
        Organization $organization,
    ): array {
        $package->loadMissing([
            'productVersion:id,version_number',
            'publisher:id,name',
            'sections',
        ]);

        $sections = $package->sections
            ->sortBy('sort_order')
            ->values()
            ->map(function (TechnicalDocumentationSection $section) {
                $bodyMarkdown = $this->resolveSectionMarkdown($section);
                $bodyHtml = '';

                if ($section->is_applicable && filled($bodyMarkdown)) {
                    $bodyHtml = Str::markdown($bodyMarkdown, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ]);
                }

                return [
                    'section_key' => $section->section_key->value,
                    'title' => $this->sectionTitle($section->section_key->value),
                    'source' => $section->source->value,
                    'source_label' => $this->sourceLabel($section->source->value),
                    'is_applicable' => $section->is_applicable,
                    'body_markdown' => $bodyMarkdown,
                    'body_html' => $bodyHtml,
                    'override_reason' => $section->override_reason,
                ];
            })
            ->all();

        return [
            'organization' => [
                'name' => $organization->name,
            ],
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'package' => [
                'id' => $package->id,
                'title' => $package->title,
                'status' => $package->status->value,
                'status_label' => $this->statusLabel($package->status->value),
                'version_label' => $package->version_label,
                'locale' => $package->locale,
                'locale_label' => $this->localeLabel($package->locale),
                'product_version_number' => $package->productVersion?->version_number,
                'published_at' => $package->published_at?->toIso8601String(),
                'published_by_name' => $package->publisher?->name,
                'notes' => $package->notes,
                'sections' => $sections,
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMarkdown(array $payload): string
    {
        /** @var array<string, mixed> $package */
        $package = $payload['package'];
        $lines = [];

        $lines[] = '# ' . $package['title'];
        $lines[] = '';
        $lines[] = '> ' . Translations::get('products.technical_documentation.export.disclaimer');
        $lines[] = '';
        $lines[] = '- **' . Translations::get('products.technical_documentation.export.meta_organization') . ':** '
            . $payload['organization']['name'];
        $lines[] = '- **' . Translations::get('products.technical_documentation.export.meta_product') . ':** '
            . $payload['product']['name'];
        $lines[] = '- **' . Translations::get('products.technical_documentation.columns.status') . ':** '
            . $package['status_label'];
        $lines[] = '- **' . Translations::get('products.technical_documentation.fields.version_label') . ':** '
            . $package['version_label'];
        $lines[] = '- **' . Translations::get('products.technical_documentation.fields.product_version') . ':** '
            . ($package['product_version_number']
                ?: Translations::get('products.technical_documentation.product_wide'));
        $lines[] = '- **' . Translations::get('products.technical_documentation.fields.locale') . ':** '
            . $package['locale_label'];

        if ($package['published_at']) {
            $published = $package['published_at'];
            if ($package['published_by_name']) {
                $published .= ' (' . $package['published_by_name'] . ')';
            }
            $lines[] = '- **' . Translations::get('products.technical_documentation.export.published_at') . ':** '
                . $published;
        }

        $lines[] = '- **' . Translations::get('products.technical_documentation.export.generated_at') . ':** '
            . $payload['generated_at'];

        if (filled($package['notes'])) {
            $lines[] = '';
            $lines[] = '## ' . Translations::get('products.technical_documentation.fields.notes');
            $lines[] = '';
            $lines[] = $package['notes'];
        }

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.technical_documentation.export.section_contents');
        $lines[] = '';

        foreach ($package['sections'] as $section) {
            $lines[] = '### ' . $section['title'];
            $lines[] = '';
            $lines[] = '- **' . Translations::get('products.technical_documentation.export.source') . ':** '
                . $section['source_label'];

            if (!$section['is_applicable']) {
                $lines[] = '';
                $lines[] = '*' . Translations::get('products.technical_documentation.not_applicable') . '*';
                if (filled($section['override_reason'] ?? null)) {
                    $lines[] = '';
                    $lines[] = '**' . Translations::get('products.technical_documentation.fields.override_reason') . ':** '
                        . $section['override_reason'];
                }
                $lines[] = '';

                continue;
            }

            $lines[] = '';
            $lines[] = filled($section['body_markdown'])
                ? $section['body_markdown']
                : Translations::get('products.technical_documentation.export.empty');
            $lines[] = '';
        }

        return implode("\n", $lines);
    }

    private function resolveSectionMarkdown(TechnicalDocumentationSection $section): string
    {
        if (!$section->is_applicable) {
            return '';
        }

        return match ($section->source) {
            TechnicalDocumentationSectionSource::Authored => trim((string) $section->body_markdown),
            TechnicalDocumentationSectionSource::Generated => $this->generatedSectionMarkdown($section),
            TechnicalDocumentationSectionSource::Linked => $this->linkedSectionMarkdown($section),
        };
    }

    private function generatedSectionMarkdown(TechnicalDocumentationSection $section): string
    {
        $parts = [];
        $generated = $this->payloadMarkdown($section->generated_payload);

        if ($generated !== '') {
            $parts[] = $generated;
        }

        $notes = trim((string) $section->body_markdown);
        if ($notes !== '') {
            $parts[] = '#### ' . Translations::get('products.technical_documentation.fields.supplemental_notes');
            $parts[] = '';
            $parts[] = $notes;
        }

        return implode("\n", $parts);
    }

    private function linkedSectionMarkdown(TechnicalDocumentationSection $section): string
    {
        $notes = trim((string) $section->body_markdown);
        if ($notes !== '') {
            return $notes;
        }

        return Translations::get('products.technical_documentation.linked_placeholder');
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function payloadMarkdown(?array $payload): string
    {
        if ($payload === null) {
            return '';
        }

        $markdown = $payload['markdown'] ?? null;

        return is_string($markdown) ? trim($markdown) : '';
    }

    private function filenameBase(TechnicalDocumentationPackage $package, Product $product): string
    {
        $slug = Str::slug($product->slug !== '' ? $product->slug : $product->name);

        return sprintf(
            'tech-doc-%s-%d-%s',
            $slug !== '' ? $slug : 'product',
            $package->id,
            now()->format('Y-m-d'),
        );
    }

    private function sectionTitle(string $key): string
    {
        return $this->translateOrFallback(
            "products.technical_documentation.sections.{$key}",
            $key,
        );
    }

    private function statusLabel(string $value): string
    {
        return $this->translateOrFallback(
            "products.technical_documentation.statuses.{$value}",
            $value,
        );
    }

    private function localeLabel(string $value): string
    {
        return $this->translateOrFallback(
            "products.technical_documentation.locales.{$value}",
            strtoupper($value),
        );
    }

    private function sourceLabel(string $value): string
    {
        return $this->translateOrFallback(
            "products.technical_documentation.sources.{$value}",
            $value,
        );
    }

    private function translateOrFallback(string $key, string $fallback): string
    {
        $translated = Translations::get($key);

        return $translated === $key ? $fallback : $translated;
    }
}
