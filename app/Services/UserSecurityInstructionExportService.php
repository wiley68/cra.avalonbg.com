<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use App\Models\User;
use App\Models\UserSecurityInstruction;
use App\Models\UserSecurityInstructionSection;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use ZipArchive;

class UserSecurityInstructionExportService
{
    private const FORMATS = ['html', 'pdf', 'readme', 'release'];

    /**
     * @return Response|BinaryFileResponse|SymfonyResponse
     */
    public function export(
        UserSecurityInstruction $instruction,
        Product $product,
        Organization $organization,
        string $format,
        User $actor,
    ): Response|BinaryFileResponse|SymfonyResponse {
        $format = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => Translations::get('products.user_security_instructions.export.invalid_format'),
            ]);
        }

        $instruction->loadMissing([
            'sections',
            'publisher:id,name',
        ]);

        $viewPayload = $this->viewPayload($instruction, $product, $organization);
        $markdown = $this->toMarkdown($instruction, $product, $organization);
        $filenameBase = $this->filenameBase($instruction, $product);

        AuditLogger::logUserSecurityInstructionExported($instruction, $actor, $format);

        return match ($format) {
            'pdf' => Pdf::loadView('pdf.user-security-instructions', $viewPayload)
                ->setPaper('a4')
                ->stream($filenameBase . '.pdf'),
            'html' => response(
                view('pdf.user-security-instructions', $viewPayload)->render(),
                200,
                [
                    'Content-Type' => 'text/html; charset=UTF-8',
                    'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.html"',
                ],
            ),
            'readme' => response($markdown, 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '-README.md"',
            ]),
            'release' => $this->downloadReleaseZip(
                $filenameBase,
                $markdown,
                view('pdf.user-security-instructions', $viewPayload)->render(),
                Pdf::loadView('pdf.user-security-instructions', $viewPayload)
                    ->setPaper('a4')
                    ->output(),
            ),
        };
    }

    private function downloadReleaseZip(
        string $filenameBase,
        string $markdown,
        string $html,
        string $pdfBinary,
    ): BinaryFileResponse {
        $zipPath = tempnam(sys_get_temp_dir(), 'usi-release-');
        if ($zipPath === false) {
            abort(500, 'Could not create temporary export file.');
        }

        $zipPathWithExt = $zipPath . '.zip';
        rename($zipPath, $zipPathWithExt);
        $zipPath = $zipPathWithExt;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            abort(500, 'Could not create ZIP archive.');
        }

        $zip->addFromString('README.md', $markdown);
        $zip->addFromString('security-instructions.html', $html);
        $zip->addFromString('security-instructions.pdf', $pdfBinary);
        $zip->close();

        return response()
            ->download($zipPath, $filenameBase . '-release.zip', [
                'Content-Type' => 'application/zip',
            ])
            ->deleteFileAfterSend(true);
    }

    /**
     * Assembled Markdown used by README/release export and Evidence publish.
     */
    public function toMarkdown(
        UserSecurityInstruction $instruction,
        Product $product,
        Organization $organization,
    ): string {
        $instruction->loadMissing(['sections', 'publisher:id,name']);

        return $this->buildMarkdown($instruction, $product, $organization);
    }

    private function buildMarkdown(
        UserSecurityInstruction $instruction,
        Product $product,
        Organization $organization,
    ): string {
        $instruction->loadMissing('productVersion:id,version_number');

        $lines = [];
        $lines[] = '# ' . $instruction->title;
        $lines[] = '';
        $lines[] = '> ' . Translations::get('products.user_security_instructions.export.disclaimer');
        $lines[] = '';
        $lines[] = '- **' . Translations::get('products.user_security_instructions.export.meta_organization') . ':** ' . $organization->name;
        $lines[] = '- **' . Translations::get('products.user_security_instructions.export.meta_product') . ':** ' . $product->name;
        $lines[] = '- **' . Translations::get('products.user_security_instructions.fields.product_version') . ':** '
            . ($instruction->productVersion?->version_number
                ?? Translations::get('products.user_security_instructions.product_wide'));
        $lines[] = '- **' . Translations::get('products.user_security_instructions.fields.version_label') . ':** ' . $instruction->version_label;
        $lines[] = '- **' . Translations::get('products.user_security_instructions.fields.locale') . ':** ' . $instruction->locale;
        $lines[] = '- **' . Translations::get('products.user_security_instructions.columns.status') . ':** ' . $instruction->status->value;
        $lines[] = '- **' . Translations::get('products.user_security_instructions.export.generated_at') . ':** ' . now()->toIso8601String();

        if ($instruction->published_at !== null) {
            $published = $instruction->published_at->toIso8601String();
            if ($instruction->publisher?->name) {
                $published .= ' (' . $instruction->publisher->name . ')';
            }
            $lines[] = '- **' . Translations::get('products.user_security_instructions.fields.published_at') . ':** ' . $published;
        }

        $lines[] = '';

        foreach ($instruction->sections->sortBy('sort_order') as $section) {
            /** @var UserSecurityInstructionSection $section */
            $lines[] = '## ' . $this->sectionTitle($section);
            $lines[] = '';

            if (!$section->is_applicable) {
                $lines[] = '*' . Translations::get('products.user_security_instructions.export.not_applicable') . '*';
                $lines[] = '';

                continue;
            }

            if (!filled($section->body)) {
                $lines[] = '*' . Translations::get('products.user_security_instructions.export.empty_section') . '*';
                $lines[] = '';

                continue;
            }

            $lines[] = rtrim((string) $section->body);
            $lines[] = '';
        }

        return implode("\n", $lines) . "\n";
    }

    private function sectionTitle(UserSecurityInstructionSection $section): string
    {
        if (filled($section->title_override)) {
            return (string) $section->title_override;
        }

        $defaultTitleKey = 'products.user_security_instructions.sections.' . $section->section_key->value;
        $defaultTitle = Translations::get($defaultTitleKey);

        return $defaultTitle === $defaultTitleKey
            ? $section->section_key->value
            : $defaultTitle;
    }

    /**
     * @return array{
     *     organization: array{id: int, name: string, slug: string},
     *     product: array{id: int, name: string, slug: string},
     *     instruction: array{
     *         title: string,
     *         status: string,
     *         version_label: string,
     *         locale: string,
     *         product_version_number: string|null,
     *         published_at: string|null,
     *         published_by_name: string|null
     *     },
     *     sections: list<array{
     *         section_key: string,
     *         title: string,
     *         is_applicable: bool,
     *         body_html: string
     *     }>,
     *     generated_at: string
     * }
     */
    private function viewPayload(
        UserSecurityInstruction $instruction,
        Product $product,
        Organization $organization,
    ): array {
        $instruction->loadMissing('productVersion:id,version_number');

        $sections = $instruction->sections
            ->sortBy('sort_order')
            ->values()
            ->map(function (UserSecurityInstructionSection $section) {
                $bodyHtml = '';
                if ($section->is_applicable && filled($section->body)) {
                    $bodyHtml = Str::markdown($section->body, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ]);
                }

                return [
                    'section_key' => $section->section_key->value,
                    'title' => $this->sectionTitle($section),
                    'is_applicable' => $section->is_applicable,
                    'body_html' => $bodyHtml,
                ];
            })
            ->all();

        return [
            'organization' => [
                'id' => $organization->id,
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'product' => [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'instruction' => [
                'title' => $instruction->title,
                'status' => $instruction->status->value,
                'version_label' => $instruction->version_label,
                'locale' => $instruction->locale,
                'product_version_number' => $instruction->productVersion?->version_number,
                'published_at' => $instruction->published_at?->toIso8601String(),
                'published_by_name' => $instruction->publisher?->name,
            ],
            'sections' => $sections,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    private function filenameBase(UserSecurityInstruction $instruction, Product $product): string
    {
        $slug = Str::slug($instruction->title) ?: 'security-instructions';
        $version = Str::slug($instruction->version_label) ?: 'version';

        return sprintf(
            'security-instructions-%s-%s-%s-%s',
            $product->slug,
            $slug,
            $version,
            now()->format('Y-m-d'),
        );
    }
}
