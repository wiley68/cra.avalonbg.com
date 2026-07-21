<?php

namespace App\Models;

use App\Enums\PolicyStatus;
use App\Enums\PolicyType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Org-scoped policy library document (not Laravel Gate policy).
 *
 * @property int $id
 * @property int $organization_id
 * @property PolicyType $policy_type
 * @property string $title
 * @property PolicyStatus $status
 * @property string $version_label
 * @property string $body
 * @property int|null $supersedes_id
 * @property Carbon|null $approved_at
 * @property int|null $approved_by
 * @property int|null $evidence_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read OrgPolicy|null $supersedes
 * @property-read User|null $approver
 * @property-read Evidence|null $evidence
 */
#[Fillable([
    'organization_id',
    'policy_type',
    'title',
    'status',
    'version_label',
    'body',
    'supersedes_id',
    'approved_at',
    'approved_by',
    'evidence_id',
    'notes',
])]
class OrgPolicy extends Model
{
    protected $table = 'organization_policies';

    protected function casts(): array
    {
        return [
            'policy_type' => PolicyType::class,
            'status' => PolicyStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<OrgPolicy, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return BelongsTo<Evidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [PolicyStatus::Draft, PolicyStatus::UnderReview], true);
    }
}
