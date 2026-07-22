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
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class UserSecurityInstructionExportService
{
    /**
     * @return Response|SymfonyResponse
     */
    public function export(
        UserSecurityInstruction $instruction,
        Product $product,
        Organization $organization,
        string $format,
        User $actor,
    ): Response|SymfonyResponse {
        $format = strtolower($format);

        if (!in_array($format, ['html', 'pdf'], true)) {
            throw ValidationException::withMessages([
                'format' => Translations::get('products.user_security_instructions.export.invalid_format'),
            ]);
        }

        $instruction->loadMissing([
            'sections',
            'publisher:id,name',
        ]);

        $payload = $this->viewPayload($instruction, $product, $organization);
        $filenameBase = $this->filenameBase($instruction, $product);

        AuditLogger::logUserSecurityInstructionExported($instruction, $actor, $format);

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.user-security-instructions', $payload)
                ->setPaper('a4')
                ->stream($filenameBase . '.pdf');
        }

        $html = view('pdf.user-security-instructions', $payload)->render();

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.html"',
        ]);
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
        $sections = $instruction->sections
            ->sortBy('sort_order')
            ->values()
            ->map(function (UserSecurityInstructionSection $section) {
                $defaultTitleKey = 'products.user_security_instructions.sections.' . $section->section_key->value;
                $defaultTitle = Translations::get($defaultTitleKey);
                if ($defaultTitle === $defaultTitleKey) {
                    $defaultTitle = $section->section_key->value;
                }

                $title = filled($section->title_override)
                    ? (string) $section->title_override
                    : $defaultTitle;

                $bodyHtml = '';
                if ($section->is_applicable && filled($section->body)) {
                    $bodyHtml = Str::markdown($section->body, [
                        'html_input' => 'strip',
                        'allow_unsafe_links' => false,
                    ]);
                }

                return [
                    'section_key' => $section->section_key->value,
                    'title' => $title,
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
