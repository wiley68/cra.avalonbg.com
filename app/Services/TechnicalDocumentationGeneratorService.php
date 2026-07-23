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
use App\Models\TechnicalDocumentationPackage;
use App\Models\TechnicalDocumentationSection;
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
            fn(TechnicalDocumentationSectionKey $key) => $key->defaultSource() === TechnicalDocumentationSectionSource::Generated,
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
        $package->loadMissing(['product.productOwner:id,name', 'product.securityContact:id,name', 'sections']);

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

            if ($section->source !== TechnicalDocumentationSectionSource::Generated) {
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

        [$sourceModule, $facts, $markdown] = match ($key) {
            TechnicalDocumentationSectionKey::ProductIdentification => $this->productIdentification($product),
            TechnicalDocumentationSectionKey::CybersecurityRiskAssessment => $this->riskAssessment($product, $versionId),
            TechnicalDocumentationSectionKey::Sbom => $this->sbom($product, $versionId),
            TechnicalDocumentationSectionKey::ComponentInventory => $this->componentInventory($product, $versionId),
            TechnicalDocumentationSectionKey::SupportPeriod => $this->supportPeriod($product),
            TechnicalDocumentationSectionKey::ReleaseHistory => $this->releaseHistory($product),
            TechnicalDocumentationSectionKey::EssentialRequirementsMatrix => $this->requirementsMatrix($product),
            TechnicalDocumentationSectionKey::DesignDevelopmentControls => $this->controls($product),
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
    private function productIdentification(Product $product): array
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
            '# ' . Translations::get('products.technical_documentation.sections.product_identification'),
            '',
            '- **Name:** ' . $facts['name'],
            '- **Slug:** ' . $facts['slug'],
            '- **Type:** ' . $facts['product_type'],
            '- **Manufacturer:** ' . ($facts['manufacturer'] ?: '—'),
            '- **Product line:** ' . ($facts['product_line'] ?: '—'),
            '- **Licensing:** ' . $facts['licensing_model'],
            '- **Network connectivity:** ' . ($facts['has_network_connectivity'] ? 'yes' : 'no'),
            '- **Remote data processing:** ' . ($facts['has_remote_data_processing'] ? 'yes' : 'no'),
            '- **Deployment model:** ' . ($facts['deployment_model'] ?: '—'),
            '- **Scope status:** ' . $facts['scope_status'],
            '- **Classification:** ' . $facts['classification_status'],
            '- **Product owner:** ' . ($facts['product_owner'] ?: '—'),
            '- **Security contact:** ' . ($facts['security_contact'] ?: '—'),
            '',
        ];

        if (filled($facts['description'])) {
            $lines[] = '## Description';
            $lines[] = '';
            $lines[] = (string) $facts['description'];
            $lines[] = '';
        }

        if (filled($facts['intended_purpose'])) {
            $lines[] = '## Intended purpose';
            $lines[] = '';
            $lines[] = (string) $facts['intended_purpose'];
            $lines[] = '';
        }

        return ['product', $facts, implode("\n", $lines)];
    }

    /**
     * @return array{0: string, 1: array<string, mixed>, 2: string}
     */
    private function riskAssessment(Product $product, ?int $versionId): array
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
            '# ' . Translations::get('products.technical_documentation.sections.cybersecurity_risk_assessment'),
            '',
            '- **Risk records:** ' . $facts['count'],
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| Title | Category | Status | Initial | Residual | Owner |';
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
    private function sbom(Product $product, ?int $versionId): array
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
            '# ' . Translations::get('products.technical_documentation.sections.sbom'),
            '',
            '- **SBOM records:** ' . $facts['count'],
            '- **Listed components (sum):** ' . $facts['total_components'],
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| File | Format | Version | Components | Imported |';
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
    private function componentInventory(Product $product, ?int $versionId): array
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
            '# ' . Translations::get('products.technical_documentation.sections.component_inventory'),
            '',
            '- **Components:** ' . $facts['count'],
            '',
        ];

        if ($facts['truncated']) {
            $lines[] = '> Showing first ' . self::COMPONENT_LIMIT . ' of ' . $total . ' components.';
            $lines[] = '';
        }

        if ($rows === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| Name | Version | Ecosystem | Licence | Direct | Support |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';
            foreach ($rows as $row) {
                $lines[] = sprintf(
                    '| %s | %s | %s | %s | %s | %s |',
                    $this->cell($row['name']),
                    $this->cell($row['version'] ?? '—'),
                    $this->cell($row['package_ecosystem']),
                    $this->cell($row['licence'] ?? '—'),
                    $row['is_direct'] ? 'yes' : 'no',
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
    private function supportPeriod(Product $product): array
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
            '# ' . Translations::get('products.technical_documentation.sections.support_period'),
            '',
            '- **Support periods:** ' . $facts['count'],
            '',
        ];

        if (filled($facts['support_period_notes'])) {
            $lines[] = '## Product notes';
            $lines[] = '';
            $lines[] = (string) $facts['support_period_notes'];
            $lines[] = '';
        }

        if (filled($facts['end_of_support_policy'])) {
            $lines[] = '## End-of-support policy';
            $lines[] = '';
            $lines[] = (string) $facts['end_of_support_policy'];
            $lines[] = '';
        }

        if ($periods === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| Type | Duration (months) | Starts | Ends | Active | Versions |';
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
                    $period['is_active'] ? 'yes' : 'no',
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
    private function releaseHistory(Product $product): array
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
            '# ' . Translations::get('products.technical_documentation.sections.release_history'),
            '',
            '- **Versions:** ' . $facts['count'],
            '',
        ];

        if ($versions === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| Version | Released | State | Support | Security support until |';
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
    private function requirementsMatrix(Product $product): array
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
            '# ' . Translations::get('products.technical_documentation.sections.essential_requirements_matrix'),
            '',
            '- **Mapped requirements:** ' . $facts['count'],
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| Code | Article | Status | Rationale |';
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
    private function controls(Product $product): array
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
            '# ' . Translations::get('products.technical_documentation.sections.design_development_controls'),
            '',
            '- **Mapped controls:** ' . $facts['count'],
            '',
        ];

        if ($rows === []) {
            $lines[] = '*' . Translations::get('products.technical_documentation.generated_empty') . '*';
            $lines[] = '';
        } else {
            $lines[] = '| Code | Name | Status |';
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

    private function cell(?string $value): string
    {
        $value = str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], (string) ($value ?? ''));

        return $value !== '' ? $value : '—';
    }
}
