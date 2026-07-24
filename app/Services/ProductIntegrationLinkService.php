<?php

namespace App\Services;

use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\Integrations\JiraCloudProvider;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Validation\ValidationException;

class ProductIntegrationLinkService
{
    public function linkJiraProject(
        Product $product,
        string $projectKey,
        User $actor,
    ): ProductIntegrationLink {
        $integration = $this->activeJiraIntegration($product->organization_id);
        $provider = JiraCloudProvider::fromIntegration($integration);
        $project = $provider->getProject($projectKey);

        $existing = ProductIntegrationLink::query()
            ->where('product_id', $product->id)
            ->where('integration_id', $integration->id)
            ->first();

        $attributes = [
            'external_project_key' => $project['key'],
            'external_target_id' => $project['id'],
            'external_label' => $project['name'],
            'config' => [
                'jql' => sprintf('project = "%s" ORDER BY updated DESC', $project['key']),
            ],
        ];

        if ($existing !== null) {
            $existing->update($attributes);
            $link = $existing->fresh(['product', 'integration']);
            AuditLogger::logIntegrationLinked($link, $actor);

            return $link;
        }

        $link = ProductIntegrationLink::query()->create([
            'product_id' => $product->id,
            'integration_id' => $integration->id,
            ...$attributes,
        ]);

        AuditLogger::logIntegrationLinked($link->load(['product', 'integration']), $actor);

        return $link;
    }

    public function unlink(ProductIntegrationLink $link, User $actor): void
    {
        $link->loadMissing(['product', 'integration']);
        AuditLogger::logIntegrationUnlinked($link, $actor);
        $link->delete();
    }

    public function jiraLinkForProduct(Product $product): ?ProductIntegrationLink
    {
        return ProductIntegrationLink::query()
            ->where('product_id', $product->id)
            ->whereHas('integration', function ($query): void {
                $query->where('provider', IntegrationProvider::Jira);
            })
            ->with('integration')
            ->first();
    }

    /**
     * @return array{
     *     id: int,
     *     provider: string,
     *     external_project_key: string|null,
     *     external_label: string|null,
     *     last_synced_at: string|null,
     *     last_sync_summary: array<string, mixed>|null
     * }|null
     */
    public function jiraPayload(?ProductIntegrationLink $link): ?array
    {
        if ($link === null) {
            return null;
        }

        $link->loadMissing('integration');

        return [
            'id' => $link->id,
            'provider' => $link->integration->provider->value,
            'external_project_key' => $link->external_project_key,
            'external_label' => $link->external_label,
            'last_synced_at' => $link->last_synced_at?->toIso8601String(),
            'last_sync_summary' => $link->last_sync_summary,
        ];
    }

    /**
     * @return array{connected: bool, label: string|null, status: string|null}|null
     */
    public function jiraIntegrationOption(Organization $organization): ?array
    {
        $integration = OrganizationIntegration::query()
            ->where('organization_id', $organization->id)
            ->where('provider', IntegrationProvider::Jira)
            ->first();

        if ($integration === null) {
            return null;
        }

        return [
            'connected' => $integration->status === IntegrationConnectionStatus::Active,
            'label' => $integration->label,
            'status' => $integration->status->value,
        ];
    }

    private function activeJiraIntegration(int $organizationId): OrganizationIntegration
    {
        $integration = OrganizationIntegration::query()
            ->where('organization_id', $organizationId)
            ->where('provider', IntegrationProvider::Jira)
            ->where('status', IntegrationConnectionStatus::Active)
            ->first();

        if ($integration === null) {
            throw ValidationException::withMessages([
                'project_key' => [Translations::get('products.integrations.jira_not_connected')],
            ]);
        }

        return $integration;
    }
}
