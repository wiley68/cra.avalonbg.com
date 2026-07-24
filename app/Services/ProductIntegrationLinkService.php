<?php

namespace App\Services;

use App\Enums\IntegrationConnectionStatus;
use App\Enums\IntegrationProvider;
use App\Models\Organization;
use App\Models\OrganizationIntegration;
use App\Models\Product;
use App\Models\ProductIntegrationLink;
use App\Models\User;
use App\Services\Integrations\AzureDevOpsProvider;
use App\Services\Integrations\JiraCloudProvider;
use App\Services\Integrations\SnykApiProvider;
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
        $integration = $this->activeIntegration($product->organization_id, IntegrationProvider::Jira, 'project_key');
        $provider = JiraCloudProvider::fromIntegration($integration);
        $project = $provider->getProject($projectKey);

        return $this->upsertLink(
            product: $product,
            integration: $integration,
            actor: $actor,
            attributes: [
                'external_project_key' => $project['key'],
                'external_target_id' => $project['id'],
                'external_label' => $project['name'],
                'config' => [
                    'jql' => sprintf('project = "%s" ORDER BY updated DESC', $project['key']),
                ],
            ],
        );
    }

    public function linkSnykTarget(
        Product $product,
        string $orgId,
        string $projectId,
        User $actor,
    ): ProductIntegrationLink {
        $integration = $this->activeIntegration($product->organization_id, IntegrationProvider::Snyk, 'org_id');
        $provider = SnykApiProvider::fromIntegration($integration);
        $project = $provider->getProject($orgId, $projectId);

        return $this->upsertLink(
            product: $product,
            integration: $integration,
            actor: $actor,
            attributes: [
                'external_project_key' => $project['org_id'],
                'external_target_id' => $project['id'],
                'external_label' => $project['name'],
                'config' => [
                    'org_id' => $project['org_id'],
                    'project_id' => $project['id'],
                ],
            ],
        );
    }

    public function linkAzureDevOpsProject(
        Product $product,
        string $project,
        User $actor,
    ): ProductIntegrationLink {
        $integration = $this->activeIntegration(
            $product->organization_id,
            IntegrationProvider::AzureDevops,
            'project',
        );
        $provider = AzureDevOpsProvider::fromIntegration($integration);
        $resolved = $provider->getProject($project);

        return $this->upsertLink(
            product: $product,
            integration: $integration,
            actor: $actor,
            attributes: [
                'external_project_key' => $resolved['key'],
                'external_target_id' => $resolved['id'],
                'external_label' => $resolved['name'],
                'config' => [
                    'project' => $resolved['key'],
                ],
            ],
        );
    }

    public function unlink(ProductIntegrationLink $link, User $actor): void
    {
        $link->loadMissing(['product', 'integration']);
        AuditLogger::logIntegrationUnlinked($link, $actor);
        $link->delete();
    }

    public function jiraLinkForProduct(Product $product): ?ProductIntegrationLink
    {
        return $this->linkForProduct($product, IntegrationProvider::Jira);
    }

    public function snykLinkForProduct(Product $product): ?ProductIntegrationLink
    {
        return $this->linkForProduct($product, IntegrationProvider::Snyk);
    }

    public function azureDevOpsLinkForProduct(Product $product): ?ProductIntegrationLink
    {
        return $this->linkForProduct($product, IntegrationProvider::AzureDevops);
    }

    public function linkForProvider(Product $product, IntegrationProvider $provider): ?ProductIntegrationLink
    {
        return $this->linkForProduct($product, $provider);
    }

    /**
     * @return array{
     *     id: int,
     *     provider: string,
     *     external_project_key: string|null,
     *     external_target_id: string|null,
     *     external_label: string|null,
     *     last_synced_at: string|null,
     *     last_sync_summary: array<string, mixed>|null
     * }|null
     */
    public function jiraPayload(?ProductIntegrationLink $link): ?array
    {
        return $this->linkPayload($link);
    }

    /**
     * @return array{
     *     id: int,
     *     provider: string,
     *     external_project_key: string|null,
     *     external_target_id: string|null,
     *     external_label: string|null,
     *     last_synced_at: string|null,
     *     last_sync_summary: array<string, mixed>|null
     * }|null
     */
    public function snykPayload(?ProductIntegrationLink $link): ?array
    {
        return $this->linkPayload($link);
    }

    /**
     * @return array{
     *     id: int,
     *     provider: string,
     *     external_project_key: string|null,
     *     external_target_id: string|null,
     *     external_label: string|null,
     *     last_synced_at: string|null,
     *     last_sync_summary: array<string, mixed>|null
     * }|null
     */
    public function azureDevOpsPayload(?ProductIntegrationLink $link): ?array
    {
        return $this->linkPayload($link);
    }

    /**
     * @return array{connected: bool, label: string|null, status: string|null}|null
     */
    public function jiraIntegrationOption(Organization $organization): ?array
    {
        return $this->integrationOption($organization, IntegrationProvider::Jira);
    }

    /**
     * @return array{connected: bool, label: string|null, status: string|null}|null
     */
    public function snykIntegrationOption(Organization $organization): ?array
    {
        return $this->integrationOption($organization, IntegrationProvider::Snyk);
    }

    /**
     * @return array{connected: bool, label: string|null, status: string|null}|null
     */
    public function azureDevOpsIntegrationOption(Organization $organization): ?array
    {
        return $this->integrationOption($organization, IntegrationProvider::AzureDevops);
    }

    /**
     * @param  array{
     *     external_project_key: string|null,
     *     external_target_id: string|null,
     *     external_label: string|null,
     *     config: array<string, mixed>|null
     * }  $attributes
     */
    private function upsertLink(
        Product $product,
        OrganizationIntegration $integration,
        User $actor,
        array $attributes,
    ): ProductIntegrationLink {
        $existing = ProductIntegrationLink::query()
            ->where('product_id', $product->id)
            ->where('integration_id', $integration->id)
            ->first();

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

    private function linkForProduct(Product $product, IntegrationProvider $provider): ?ProductIntegrationLink
    {
        return ProductIntegrationLink::query()
            ->where('product_id', $product->id)
            ->whereHas('integration', function ($query) use ($provider): void {
                $query->where('provider', $provider);
            })
            ->with('integration')
            ->first();
    }

    /**
     * @return array{
     *     id: int,
     *     provider: string,
     *     external_project_key: string|null,
     *     external_target_id: string|null,
     *     external_label: string|null,
     *     last_synced_at: string|null,
     *     last_sync_summary: array<string, mixed>|null
     * }|null
     */
    private function linkPayload(?ProductIntegrationLink $link): ?array
    {
        if ($link === null) {
            return null;
        }

        $link->loadMissing('integration');

        return [
            'id' => $link->id,
            'provider' => $link->integration->provider->value,
            'external_project_key' => $link->external_project_key,
            'external_target_id' => $link->external_target_id,
            'external_label' => $link->external_label,
            'last_synced_at' => $link->last_synced_at?->toIso8601String(),
            'last_sync_summary' => $link->last_sync_summary,
        ];
    }

    /**
     * @return array{connected: bool, label: string|null, status: string|null}|null
     */
    private function integrationOption(Organization $organization, IntegrationProvider $provider): ?array
    {
        $integration = OrganizationIntegration::query()
            ->where('organization_id', $organization->id)
            ->where('provider', $provider)
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

    private function activeIntegration(
        int $organizationId,
        IntegrationProvider $provider,
        string $errorField,
    ): OrganizationIntegration {
        $integration = OrganizationIntegration::query()
            ->where('organization_id', $organizationId)
            ->where('provider', $provider)
            ->where('status', IntegrationConnectionStatus::Active)
            ->first();

        if ($integration === null) {
            $messageKey = match ($provider) {
                IntegrationProvider::Snyk => 'products.integrations.snyk_not_connected',
                IntegrationProvider::AzureDevops => 'products.integrations.azure_devops_not_connected',
                default => 'products.integrations.jira_not_connected',
            };

            throw ValidationException::withMessages([
                $errorField => [Translations::get($messageKey)],
            ]);
        }

        return $integration;
    }
}
