<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use App\Models\Organization;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\PolicyTemplates;
use App\Support\Translations;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrgPolicyService
{
    public function __construct(
        private readonly EvidenceService $evidence,
    ) {
    }
    /**
     * @return LengthAwarePaginator<int, array{
     *     id: int,
     *     policy_type: string,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     approved_at: string|null,
     *     updated_at: string|null
     * }>
     */
    public function paginate(
        Organization $organization,
        int $perPage = 10,
        int $page = 1,
        string $sortBy = 'updated_at',
        string $sortOrder = 'desc',
        string $search = '',
    ): LengthAwarePaginator {
        $query = OrgPolicy::query()->where('organization_id', $organization->id);

        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('policy_type', 'like', "%{$search}%")
                    ->orWhere('status', 'like', "%{$search}%")
                    ->orWhere('version_label', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%");

                if (ctype_digit($search)) {
                    $builder->orWhere('id', (int) $search);
                }
            });
        }

        $orderColumn = match ($sortBy) {
            'id' => 'id',
            'title' => 'title',
            'policy_type' => 'policy_type',
            'status' => 'status',
            'version_label' => 'version_label',
            'approved_at' => 'approved_at',
            default => 'updated_at',
        };

        $query->orderBy($orderColumn, $sortOrder === 'desc' ? 'desc' : 'asc');

        return $query
            ->paginate($perPage, ['*'], 'page', $page)
            ->through(fn(OrgPolicy $policy) => $this->listItemPayload($policy));
    }

    /**
     * @param  array{
     *     policy_type: PolicyType,
     *     title: string,
     *     version_label: string,
     *     body: string,
     *     notes?: string|null,
     *     supersedes_id?: int|null,
     *     use_template?: bool
     * }  $attributes
     */
    public function create(Organization $organization, array $attributes, User $actor): OrgPolicy
    {
        $type = $attributes['policy_type'];
        $useTemplate = (bool) ($attributes['use_template'] ?? false);

        if ($useTemplate) {
            $template = PolicyTemplates::for($type, $organization->resolvedLocale());
            $attributes['title'] = $attributes['title'] !== ''
                ? $attributes['title']
                : $template['title'];
            $attributes['version_label'] = $attributes['version_label'] !== ''
                ? $attributes['version_label']
                : $template['version_label'];
            $attributes['body'] = $attributes['body'] !== ''
                ? $attributes['body']
                : $template['body'];
        }

        $supersedesId = $attributes['supersedes_id'] ?? null;
        if ($supersedesId !== null) {
            $this->assertSupersedesBelongsToOrg($organization, $supersedesId, $type);
        }

        $policy = OrgPolicy::query()->create([
            'organization_id' => $organization->id,
            'policy_type' => $type,
            'title' => $attributes['title'],
            'status' => PolicyStatus::Draft,
            'version_label' => $attributes['version_label'],
            'body' => $attributes['body'],
            'supersedes_id' => $supersedesId,
            'notes' => $attributes['notes'] ?? null,
        ]);

        AuditLogger::logOrgPolicyCreated($policy, $actor);

        return $policy;
    }

    /**
     * @param  array{
     *     title: string,
     *     version_label: string,
     *     body: string,
     *     notes?: string|null
     * }  $attributes
     */
    public function update(OrgPolicy $policy, array $attributes, User $actor): OrgPolicy
    {
        $this->assertEditable($policy);

        $policy->update([
            'title' => $attributes['title'],
            'version_label' => $attributes['version_label'],
            'body' => $attributes['body'],
            'notes' => $attributes['notes'] ?? null,
        ]);

        $fresh = $policy->fresh();
        AuditLogger::logOrgPolicyUpdated($fresh, $actor);

        return $fresh;
    }

    public function delete(OrgPolicy $policy, User $actor): void
    {
        if ($policy->status === PolicyStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('policies.cannot_delete_approved')],
            ]);
        }

        AuditLogger::logOrgPolicyDeleted($policy, $actor);
        $policy->delete();
    }

    public function submitForReview(OrgPolicy $policy, User $actor): OrgPolicy
    {
        if ($policy->status !== PolicyStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('policies.only_draft_submit')],
            ]);
        }

        $policy->update(['status' => PolicyStatus::UnderReview]);
        $fresh = $policy->fresh();
        AuditLogger::logOrgPolicySubmitted($fresh, $actor);

        return $fresh;
    }

    public function approve(OrgPolicy $policy, User $actor): OrgPolicy
    {
        if ($policy->status !== PolicyStatus::UnderReview) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('policies.only_under_review_approve')],
            ]);
        }

        return DB::transaction(function () use ($policy, $actor): OrgPolicy {
            OrgPolicy::query()
                ->where('organization_id', $policy->organization_id)
                ->where('policy_type', $policy->policy_type->value)
                ->where('status', PolicyStatus::Approved->value)
                ->whereKeyNot($policy->id)
                ->update([
                    'status' => PolicyStatus::Retired->value,
                ]);

            $policy->update([
                'status' => PolicyStatus::Approved,
                'approved_at' => now(),
                'approved_by' => $actor->id,
            ]);

            $fresh = $policy->fresh(['approver']);
            AuditLogger::logOrgPolicyApproved($fresh, $actor);

            return $fresh;
        });
    }

    public function retire(OrgPolicy $policy, User $actor): OrgPolicy
    {
        if ($policy->status !== PolicyStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('policies.only_approved_retire')],
            ]);
        }

        $policy->update(['status' => PolicyStatus::Retired]);
        $fresh = $policy->fresh();
        AuditLogger::logOrgPolicyRetired($fresh, $actor);

        return $fresh;
    }

    public function publishEvidence(OrgPolicy $policy, Product $product, User $actor): OrgPolicy
    {
        if ($policy->status !== PolicyStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('policies.only_approved_publish')],
            ]);
        }

        if ($policy->evidence_id !== null) {
            throw ValidationException::withMessages([
                'evidence_id' => [Translations::get('policies.already_published')],
            ]);
        }

        if ($product->organization_id !== $policy->organization_id) {
            throw ValidationException::withMessages([
                'product_id' => [Translations::get('policies.publish_product_invalid')],
            ]);
        }

        return DB::transaction(function () use ($policy, $product, $actor): OrgPolicy {
            $evidence = $this->evidence->createFromOrgPolicy($product, $policy, $actor);

            $policy->update(['evidence_id' => $evidence->id]);

            $fresh = $policy->fresh(['evidence']);
            AuditLogger::logOrgPolicyPublishedEvidence($fresh, $evidence, $actor);

            return $fresh;
        });
    }

    /**
     * @return array{title: string, body: string, version_label: string}
     */
    public function templatePayload(PolicyType $type, string $locale = 'en'): array
    {
        return PolicyTemplates::for($type, $locale);
    }

    private function assertEditable(OrgPolicy $policy): void
    {
        if (!$policy->isEditable()) {
            throw ValidationException::withMessages([
                'status' => [Translations::get('policies.only_editable_draft_or_review')],
            ]);
        }
    }

    private function assertSupersedesBelongsToOrg(
        Organization $organization,
        int $supersedesId,
        PolicyType $type,
    ): void {
        $exists = OrgPolicy::query()
            ->whereKey($supersedesId)
            ->where('organization_id', $organization->id)
            ->where('policy_type', $type->value)
            ->exists();

        if (!$exists) {
            throw ValidationException::withMessages([
                'supersedes_id' => [Translations::get('policies.supersedes_invalid')],
            ]);
        }
    }

    /**
     * @return array{
     *     id: int,
     *     policy_type: string,
     *     title: string,
     *     status: string,
     *     version_label: string,
     *     approved_at: string|null,
     *     updated_at: string|null
     * }
     */
    private function listItemPayload(OrgPolicy $policy): array
    {
        return [
            'id' => $policy->id,
            'policy_type' => $policy->policy_type->value,
            'title' => $policy->title,
            'status' => $policy->status->value,
            'version_label' => $policy->version_label,
            'approved_at' => $policy->approved_at?->toIso8601String(),
            'updated_at' => $policy->updated_at?->toIso8601String(),
        ];
    }
}
