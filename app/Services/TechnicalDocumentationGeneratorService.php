<?php

namespace App\Services;

use App\Enums\TechnicalDocumentationSectionKey;
use App\Enums\TechnicalDocumentationSectionSource;
use App\Models\Product;
use App\Models\ProductComponent;
use App\Models\ProductControl;
use App\Models\ProductRequirement;
use App\Models\ProductRisk;
use App\Models\ProductSupportPeriod;
use App\Models\ProductVersion;
use App\Models\Sbom;
use App\Models\SdlRun;
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
use App\Models\UserSecurityInstruction;
use App\Support\Translations;
use Illuminate\Support\Collection;

class TechnicalDocumentationGeneratorService
{
    private const COMPONENT_LIMIT = 200;

    /**
     * @return list<TechnicalDocumentationSectionKey>
     */
    public function refreshableKeys(): array
    {
        return array_values(array_filter(
            TechnicalDocumentationSectionKey::ordered(),
            fn(TechnicalDocumentationSectionKey $key) => in_array(
                $key->defaultSource(),
                [
                    TechnicalDocumentationSectionSource::Generated,
                    TechnicalDocumentationSectionSource::Linked,
                ],
                true,
            ),
        ));
    }

    /**
     * @param  list<TechnicalDocumentationSectionKey>|null  $keys
     * @return array{refreshed: list<string>, skipped: list<string>}
     */
    public function refreshPackage(
        TechnicalDocumentationPackage $package,
        ?array $keys = null,
    ): array {
        $package->loadMissing([
            'product.productOwner:id,name',
            'product.securityContact:id,name',
            'sections',
            'userSecurityInstruction.productVersion:id,version_number',
            'userSecurityInstruction.sections',
            'sdlRun.version:id,version_number',
        ]);

        $targetKeys = $keys ?? $this->refreshableKeys();
        $refreshed = [];
        $skipped = [];

        $sectionsByKey = $package->sections
            ->keyBy(fn(TechnicalDocumentationSection $section) => $section->section_key->value);

        foreach ($targetKeys as $key) {
            $section = $sectionsByKey->get($key->value);

            if ($section === null) {
                $skipped[] = $key->value;

                continue;
            }

            $canRefresh = match ($section->source) {
                TechnicalDocumentationSectionSource::Generated => true,
                TechnicalDocumentationSectionSource::Linked => $key === TechnicalDocumentationSectionKey::UserSecurityInstructions,
                default => false,
            };

            if (!$canRefresh) {
                $skipped[] = $key->value;

                continue;
            }

            if (!$section->is_applicable) {
                $skipped[] = $key->value;

                continue;
            }

            $payload = $this->buildPayload($package, $key);
            $section->update([
                'generated_payload' => $payload,
            ]);
            $refreshed[] = $key->value;
        }

        return [
            'refreshed' => $refreshed,
            'skipped' => $skipped,
        ];
    }

    /**
     * @return array{
     *     generated_at: string,
     *     product_id: int,
     *     product_version_id: int|null,
     *     source_module: string,
     *     facts: array<string, mixed>,
     *     markdown: string
     * }
     */
    public function buildPayload(
        TechnicalDocumentationPackage $package,
        TechnicalDocumentationSectionKey $key,
    ): array {
        $product = $package->product;
        $versionId = $package->product_version_id;
        $locale = $package->locale;

        [$sourceModule, $facts, $markdown] = match ($key) {
            TechnicalDocumentationSectionKey::ProductIdentification => $this->productIdentification($product, $locale),
            TechnicalDocumentationSectionKey::CybersecurityRiskAssessment => $this->riskAssessment($product, $versionId, $locale),
            TechnicalDocumentationSectionKey::Sbom => $this->sbom($product, $versionId, $locale),
            TechnicalDocumentationSectionKey::ComponentInventory => $this->componentInventory($product, $versionId, $locale),
            TechnicalDocumentationSectionKey::SupportPeriod => $this->supportPeriod($product, $locale),
            TechnicalDocumentationSectionKey::ReleaseHistory => $this->releaseHistory($product, $locale),
            TechnicalDocumentationSectionKey::EssentialRequirementsMatrix => $this->requirementsMatrix($product, $locale),
            TechnicalDocumentationSectionKey::DesignDevelopmentControls => $this->controls($product, $locale),
            TechnicalDocumentationSectionKey::UserSecurityInstructions => $this->userSecurityInstructions($package, $locale),
            default => throw new \InvalidArgumentException('Section is not generated: ' . $key->value),
        };

        return [
            'generated_at' => now()->toIso8601String(),
            'product_id' => $product->id,
            'product_version_id' => $versionId,
            'source_module' => $sourceModule,
            'facts' => $facts,
            'markdown' => $markdown,
        ];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function productIdentification(Product $product, string $locale): array
    {
        $facts = [
            'name' => $product->name,
            'slug' => $product->slug,
            'product_line' => $product->product_line,
            'description' => $product->description,
            'intended_purpose' => $product->intended_purpose,
            'product_type' => $product->product_type->value,
            'manufacturer' => $product->manufacturer,
            'trademark' => $product->trademark,
            'licensing_model' => $product->licensing_model->value,
            'has_remote_data_processing' => $product->has_remote_data_processing,
            'has_network_connectivity' => $product->has_network_connectivity,
            'deployment_model' => $product->deployment_model,
            'scope_status' => $product->scope_status->value,
            'classification_status' => $product->classification_status->value,
            'product_owner' => $product->productOwner?->name,
            'security_contact' => $product->securityContact?->name,
        ];

        $lines = [
            '# ' . $this->sectionTitle('product_identification', $locale),
            '',
            $this->bullet($locale, 'label_name', $facts['name']),
            $this->bullet($locale, 'label_slug', $facts['slug']),
            $this->bullet($locale, 'label_type', $facts['product_type']),
            $this->bullet($locale, 'label_manufacturer', $facts['manufacturer'] ?: '—'),
            $this->bullet($locale, 'label_product_line', $facts['product_line'] ?: '—'),
            $this->bullet($locale, 'label_licensing', $facts['licensing_model']),
            $this->bullet($locale, 'label_network_connectivity', $this->yesNo($facts['has_network_connectivity'], $locale)),
            $this->bullet($locale, 'label_remote_data_processing', $this->yesNo($facts['has_remote_data_processing'], $locale)),
            $this->bullet($locale, 'label_deployment_model', $facts['deployment_model'] ?: '—'),
            $this->bullet($locale, 'label_scope_status', $facts['scope_status']),
            $this->bullet($locale, 'label_classification', $facts['classification_status']),
            $this->bullet($locale, 'label_product_owner', $facts['product_owner'] ?: '—'),
            $this->bullet($locale, 'label_security_contact', $facts['security_contact'] ?: '—'),
            '',
        ];

        if (filled($facts['description'])) {
            $lines[] = '## ' . $this->g('heading_description', $locale);
            $lines[] = '';
            $lines[] = (string) $facts['description'];
            $lines[] = '';
        }

        if (filled($facts['intended_purpose'])) {
            $lines[] = '## ' . $this->g('heading_intended_purpose', $locale);
            $lines[] = '';
            $lines[] = (string) $facts['intended_purpose'];
            $lines[] = '';
        }

        return ['product', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function riskAssessment(Product $product, ?int $versionId, string $locale): array
    {
        $query = ProductRisk::query()
            ->with('owner:id,name')
            ->where('product_id', $product->id)
            ->orderByDesc('id');

        if ($versionId !== null) {
            $query->where(function ($builder) use ($versionId): void {
                $builder->whereNull('product_version_id')
                    ->orWhere('product_version_id', $versionId);
            });
        }

        /** @var Collection<int, ProductRisk> $risks */
        $risks = $query->get();

        $rows = $risks->map(fn(ProductRisk $risk) => [
            'id' => $risk->id,
            'title' => $risk->title,
            'category' => $risk->category->value,
            'status' => $risk->status->value,
            'treatment' => $risk->treatment->value,
            'initial_risk' => $risk->initialRiskLevel()->value,
            'residual_risk' => $risk->residualRiskLevel()?->value,
            'owner_name' => $risk->owner?->name,
            'deadline' => $risk->deadline?->toDateString(),
            'product_version_id' => $risk->product_version_id,
        ])->all();

        $facts = [
            'count' => count($rows),
            'risks' => $rows,
        ];

        $lines = [
            '# ' . $this->sectionTitle('cybersecurity_risk_assessment', $locale),
            '',
            $this->bullet($locale, 'label_risk_records', (string) $facts['count']),
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_title', $locale),
                $this->g('col_category', $locale),
                $this->g('col_status', $locale),
                $this->g('col_initial', $locale),
                $this->g('col_residual', $locale),
                $this->g('col_owner', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s |',
                    $this->cell($row['title']),
                    $this->cell($row['category']),
                    $this->cell($row['status']),
                    $this->cell($row['initial_risk']),
                    $this->cell($row['residual_risk'] ?? '—'),
                    $this->cell($row['owner_name'] ?? '—'),
                );
            }
            $lines[] = '';
        }

        return ['risks', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function sbom(Product $product, ?int $versionId, string $locale): array
    {
        $query = Sbom::query()
            ->with('productVersion:id,version_number')
            ->where('product_id', $product->id)
            ->orderByDesc('imported_at');

        if ($versionId !== null) {
            $query->where('product_version_id', $versionId);
        }

        $rows = $query->get()->map(fn(Sbom $sbom) => [
            'id' => $sbom->id,
            'format' => $sbom->format->value,
            'source_filename' => $sbom->source_filename,
            'component_count' => $sbom->component_count,
            'product_version_id' => $sbom->product_version_id,
            'version_number' => $sbom->productVersion?->version_number,
            'imported_at' => $sbom->imported_at?->toIso8601String(),
            'checksum_sha256' => $sbom->checksum_sha256,
        ])->all();

        $facts = [
            'count' => count($rows),
            'total_components' => array_sum(array_column($rows, 'component_count')),
            'sboms' => $rows,
        ];

        $lines = [
            '# ' . $this->sectionTitle('sbom', $locale),
            '',
            $this->bullet($locale, 'label_sbom_records', (string) $facts['count']),
            $this->bullet($locale, 'label_listed_components', (string) $facts['total_components']),
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_file', $locale),
                $this->g('col_format', $locale),
                $this->g('col_version', $locale),
                $this->g('col_components', $locale),
                $this->g('col_imported', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- | --- | --- |';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s |',
                    $this->cell($row['source_filename']),
                    $this->cell($row['format']),
                    $this->cell($row['version_number'] ?? '—'),
                    $this->cell((string) $row['component_count']),
                    $this->cell($row['imported_at'] ?? '—'),
                );
            }
            $lines[] = '';
        }

        return ['sbom', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function componentInventory(Product $product, ?int $versionId, string $locale): array
    {
        $query = ProductComponent::query()
            ->with('productVersion:id,version_number')
            ->where('product_id', $product->id)
            ->orderBy('name');

        if ($versionId !== null) {
            $query->where('product_version_id', $versionId);
        }

        $total = (clone $query)->count();
        $components = $query->limit(self::COMPONENT_LIMIT)->get();

        $rows = $components->map(fn(ProductComponent $component) => [
            'id' => $component->id,
            'name' => $component->name,
            'version' => $component->version,
            'package_ecosystem' => $component->package_ecosystem->value,
            'licence' => $component->licence,
            'is_direct' => $component->is_direct,
            'is_dev' => $component->is_dev,
            'support_status' => $component->support_status->value,
            'product_version_id' => $component->product_version_id,
            'version_number' => $component->productVersion?->version_number,
            'purl' => $component->purl,
        ])->all();

        $facts = [
            'count' => $total,
            'returned' => count($rows),
            'truncated' => $total > self::COMPONENT_LIMIT,
            'components' => $rows,
        ];

        $lines = [
            '# ' . $this->sectionTitle('component_inventory', $locale),
            '',
            $this->bullet($locale, 'label_components', (string) $facts['count']),
            '',
        ];

        if ($facts['truncated']) {
            $lines[] = '> ' . $this->g('components_truncated', $locale, [
                'shown' => (string) self::COMPONENT_LIMIT,
                'total' => (string) $total,
            ]);
            $lines[] = '';
        }

        if ($rows === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_name', $locale),
                $this->g('col_version', $locale),
                $this->g('col_ecosystem', $locale),
                $this->g('col_licence', $locale),
                $this->g('col_direct', $locale),
                $this->g('col_support', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s |',
                    $this->cell($row['name']),
                    $this->cell($row['version'] ?? '—'),
                    $this->cell($row['package_ecosystem']),
                    $this->cell($row['licence'] ?? '—'),
                    $this->yesNo($row['is_direct'], $locale),
                    $this->cell($row['support_status']),
                );
            }
            $lines[] = '';
        }

        return ['components', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function supportPeriod(Product $product, string $locale): array
    {
        $periods = ProductSupportPeriod::query()
            ->with('versions:id,version_number')
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get()
            ->map(fn(ProductSupportPeriod $period) => [
                'id' => $period->id,
                'type' => $period->type->value,
                'start_basis' => $period->start_basis->value,
                'duration_months' => $period->duration_months,
                'basis' => $period->basis,
                'is_extended' => $period->is_extended,
                'schedule_resolved' => $period->scheduleResolved(),
                'effective_starts_at' => $period->effectiveStartsAt()?->toDateString(),
                'effective_ends_at' => $period->effectiveEndsAt()?->toDateString(),
                'is_active' => $period->isActive(),
                'days_until_end' => $period->daysUntilEnd(),
                'versions' => $period->versions->map(fn(ProductVersion $version) => [
                    'id' => $version->id,
                    'version_number' => $version->version_number,
                ])->values()->all(),
            ])->all();

        $facts = [
            'count' => count($periods),
            'support_period_notes' => $product->support_period_notes,
            'end_of_support_policy' => $product->end_of_support_policy,
            'periods' => $periods,
        ];

        $lines = [
            '# ' . $this->sectionTitle('support_period', $locale),
            '',
            $this->bullet($locale, 'label_support_periods', (string) $facts['count']),
            '',
        ];

        if (filled($facts['support_period_notes'])) {
            $lines[] = '## ' . $this->g('heading_product_notes', $locale);
            $lines[] = '';
            $lines[] = (string) $facts['support_period_notes'];
            $lines[] = '';
        }

        if (filled($facts['end_of_support_policy'])) {
            $lines[] = '## ' . $this->g('heading_end_of_support_policy', $locale);
            $lines[] = '';
            $lines[] = (string) $facts['end_of_support_policy'];
            $lines[] = '';
        }

        if ($periods === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_type', $locale),
                $this->g('col_duration_months', $locale),
                $this->g('col_starts', $locale),
                $this->g('col_ends', $locale),
                $this->g('col_active', $locale),
                $this->g('col_versions', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';
            foreach ($periods as $period) {
                $versionLabels = collect($period['versions'])
                    ->pluck('version_number')
                    ->implode(', ');
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s |',
                    $this->cell($period['type']),
                    $this->cell((string) $period['duration_months']),
                    $this->cell($period['effective_starts_at'] ?? '—'),
                    $this->cell($period['effective_ends_at'] ?? '—'),
                    $this->yesNo($period['is_active'], $locale),
                    $this->cell($versionLabels !== '' ? $versionLabels : '—'),
                );
            }
            $lines[] = '';
        }

        return ['support', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function releaseHistory(Product $product, string $locale): array
    {
        $versions = ProductVersion::query()
            ->where('product_id', $product->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn(ProductVersion $version) => [
                'id' => $version->id,
                'version_number' => $version->version_number,
                'release_date' => $version->release_date?->toDateString(),
                'state' => $version->state->value,
                'support_status' => $version->support_status->value,
                'security_support_deadline' => $version->security_support_deadline?->toDateString(),
                'git_ref' => $version->git_ref,
                'build_identifier' => $version->build_identifier,
            ])->all();

        $facts = [
            'count' => count($versions),
            'versions' => $versions,
        ];

        $lines = [
            '# ' . $this->sectionTitle('release_history', $locale),
            '',
            $this->bullet($locale, 'label_versions', (string) $facts['count']),
            '',
        ];

        if ($versions === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_version', $locale),
                $this->g('col_released', $locale),
                $this->g('col_state', $locale),
                $this->g('col_support', $locale),
                $this->g('col_security_support_until', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- | --- | --- |';
            foreach ($versions as $version) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s |',
                    $this->cell($version['version_number']),
                    $this->cell($version['release_date'] ?? '—'),
                    $this->cell($version['state']),
                    $this->cell($version['support_status']),
                    $this->cell($version['security_support_deadline'] ?? '—'),
                );
            }
            $lines[] = '';
        }

        return ['versions', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function requirementsMatrix(Product $product, string $locale): array
    {
        $rows = ProductRequirement::query()
            ->with('requirement')
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get()
            ->map(fn(ProductRequirement $item) => [
                'id' => $item->id,
                'code' => $item->requirement?->code,
                'article_ref' => $item->requirement?->article_ref,
                'status' => $item->status->value,
                'rationale' => $item->rationale,
            ])->all();

        $facts = [
            'count' => count($rows),
            'requirements' => $rows,
        ];

        $lines = [
            '# ' . $this->sectionTitle('essential_requirements_matrix', $locale),
            '',
            $this->bullet($locale, 'label_mapped_requirements', (string) $facts['count']),
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_code', $locale),
                $this->g('col_article', $locale),
                $this->g('col_status', $locale),
                $this->g('col_rationale', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- | --- |';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s |',
                    $this->cell($row['code'] ?? '—'),
                    $this->cell($row['article_ref'] ?? '—'),
                    $this->cell($row['status']),
                    $this->cell($row['rationale'] ?? '—'),
                );
            }
            $lines[] = '';
        }

        return ['requirements', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function controls(Product $product, string $locale): array
    {
        $rows = ProductControl::query()
            ->with('control')
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get()
            ->map(fn(ProductControl $item) => [
                'id' => $item->id,
                'code' => $item->control?->code,
                'name' => $item->control?->name,
                'status' => $item->status->value,
            ])->all();

        $facts = [
            'count' => count($rows),
            'controls' => $rows,
        ];

        $lines = [
            '# ' . $this->sectionTitle('design_development_controls', $locale),
            '',
            $this->bullet($locale, 'label_mapped_controls', (string) $facts['count']),
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . $this->g('empty', $locale) . '*';
            $lines[] = '';
        } else {
            $lines[] = '| ' . implode(' | ', [
                $this->g('col_code', $locale),
                $this->g('col_name', $locale),
                $this->g('col_status', $locale),
            ]) . ' |';
            $lines[] = '| --- | --- | --- |';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '| %s | %s | %s |',
                    $this->cell($row['code'] ?? '—'),
                    $this->cell($row['name'] ?? '—'),
                    $this->cell($row['status']),
                );
            }
            $lines[] = '';
        }

        return ['controls', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function userSecurityInstructions(
        TechnicalDocumentationPackage $package,
        string $locale,
    ): array {
        $package->loadMissing([
            'userSecurityInstruction.productVersion:id,version_number',
            'userSecurityInstruction.sections',
            'sdlRun.version:id,version_number',
        ]);

        /** @var UserSecurityInstruction|null $usi */
        $usi = $package->userSecurityInstruction;
        /** @var SdlRun|null $sdl */
        $sdl = $package->sdlRun;

        $facts = [
            'linked' => $usi !== null,
            'usi_id' => $usi?->id,
            'usi_title' => $usi?->title,
            'usi_version_label' => $usi?->version_label,
            'usi_locale' => $usi?->locale,
            'usi_product_version_number' => $usi?->productVersion?->version_number,
            'usi_published_at' => $usi?->published_at?->toIso8601String(),
            'usi_sections_count' => $usi !== null ? $usi->sections->count() : 0,
            'sdl_run_id' => $sdl?->id,
            'sdl_title' => $sdl?->title,
            'sdl_status' => $sdl?->status->value,
            'sdl_product_version_number' => $sdl?->version?->version_number,
        ];

        $lines = [
            '# ' . $this->sectionTitle(
                TechnicalDocumentationSectionKey::UserSecurityInstructions->value,
                $locale,
            ),
            '',
        ];

        if ($usi === null) {
            $lines[] = Translations::get(
                'products.technical_documentation.linked_usi_empty',
                [],
                $locale,
            );
        } else {
            $lines[] = $this->bullet($locale, 'label_usi_title', (string) $usi->title);
            $lines[] = $this->bullet($locale, 'label_usi_version', (string) $usi->version_label);
            $lines[] = $this->bullet(
                $locale,
                'label_usi_locale',
                strtoupper((string) $usi->locale),
            );
            $lines[] = $this->bullet(
                $locale,
                'label_usi_product_version',
                $usi->productVersion?->version_number
                ?: Translations::get(
                    'products.technical_documentation.product_wide',
                    [],
                    $locale,
                ),
            );
            if ($usi->published_at !== null) {
                $lines[] = $this->bullet(
                    $locale,
                    'label_usi_published_at',
                    $usi->published_at->toIso8601String(),
                );
            }
            $lines[] = $this->bullet(
                $locale,
                'label_usi_sections',
                (string) $usi->sections->count(),
            );
        }

        if ($sdl !== null) {
            $lines[] = '';
            $lines[] = '## ' . Translations::get(
                'products.technical_documentation.generated.heading_linked_sdl',
                [],
                $locale,
            );
            $lines[] = '';
            $lines[] = $this->bullet($locale, 'label_sdl_title', (string) $sdl->title);
            $lines[] = $this->bullet(
                $locale,
                'label_sdl_status',
                Translations::get(
                    'products.sdl.statuses.' . $sdl->status->value,
                    [],
                    $locale,
                ),
            );
            $lines[] = $this->bullet(
                $locale,
                'label_sdl_product_version',
                $sdl->version?->version_number
                ?: Translations::get(
                    'products.technical_documentation.product_wide',
                    [],
                    $locale,
                ),
            );
        }

        $lines[] = '';

        return ['user_security_instructions', $facts, implode("\n", $lines)];
    }

    /**
     * @param  array<string, string>  $replace
     */
    private function g(string $key, string $locale, array $replace = []): string
    {
        return Translations::get(
            'products.technical_documentation.generated.' . $key,
            $replace,
            $locale,
        );
    }

    private function sectionTitle(string $sectionKey, string $locale): string
    {
        return Translations::get(
            'products.technical_documentation.sections.' . $sectionKey,
            [],
            $locale,
        );
    }

    private function bullet(string $locale, string $labelKey, string $value): string
    {
        return '- **' . $this->g($labelKey, $locale) . ':** ' . $value;
    }

    private function yesNo(?bool $value, string $locale): string
    {
        if ($value === null) {
            return '—';
        }

        return $this->g($value ? 'yes' : 'no', $locale);
    }

    private function cell(?string $value): string
    {
        $value = str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) ($value ?? ''));

        return $value !== '' ? $value : '—';
    }
}
