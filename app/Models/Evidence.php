<?php

namespace App\Models;

use App\Enums\EvidenceConfidentiality;
use App\Enums\EvidenceFreshnessStatus;
use App\Enums\EvidenceType;
use App\Services\EvidenceService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property EvidenceType $type
 * @property string $title
 * @property string|null $source
 * @property int|null $owner_user_id
 * @property string|null $storage_path
 * @property string|null $source_filename
 * @property string|null $checksum_sha256
 * @property EvidenceConfidentiality $confidentiality
 * @property CarbonInterface|null $collected_at
 * @property CarbonInterface|null $valid_until
 * @property CarbonInterface|null $review_due_at
 * @property EvidenceFreshnessStatus $freshness_status
 * @property int|null $supersedes_evidence_id
 * @property int|null $uploaded_by
 * @property int|null $reviewer_user_id
 * @property CarbonInterface|null $reviewed_at
 * @property string|null $review_notes
 * @property string|null $notes
 */
#[Fillable([
    'organization_id',
    'product_id',
    'product_version_id',
    'type',
    'title',
    'source',
    'owner_user_id',
    'storage_path',
    'source_filename',
    'checksum_sha256',
    'confidentiality',
    'collected_at',
    'valid_until',
    'review_due_at',
    'freshness_status',
    'supersedes_evidence_id',
    'uploaded_by',
    'reviewer_user_id',
    'reviewed_at',
    'review_notes',
    'notes',
])]
class Evidence extends Model
{
    protected $table = 'evidence';

    protected function casts(): array
    {
        return [
            'type' => EvidenceType::class,
            'confidentiality' => EvidenceConfidentiality::class,
            'freshness_status' => EvidenceFreshnessStatus::class,
            'collected_at' => 'datetime',
            'valid_until' => 'date',
            'review_due_at' => 'date',
            'reviewed_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_user_id');
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_evidence_id');
    }

    public function requirements(): MorphToMany
    {
        return $this->morphedByMany(Requirement::class, 'linkable', 'evidence_links')->withTimestamps();
    }

    public function controls(): MorphToMany
    {
        return $this->morphedByMany(Control::class, 'linkable', 'evidence_links')->withTimestamps();
    }

    public function risks(): MorphToMany
    {
        return $this->morphedByMany(ProductRisk::class, 'linkable', 'evidence_links')->withTimestamps();
    }

    public function vulnerabilities(): MorphToMany
    {
        return $this->morphedByMany(ProductVulnerability::class, 'linkable', 'evidence_links')->withTimestamps();
    }

    public function auditorReviewPackages(): BelongsToMany
    {
        return $this->belongsToMany(
            AuditorReviewPackage::class,
            'auditor_review_package_evidence',
            'evidence_id',
            'package_id',
        )->withTimestamps();
    }

    public function refreshFreshness(): EvidenceFreshnessStatus
    {
        $status = EvidenceService::deriveFreshness(
            $this->freshness_status,
            $this->valid_until,
            $this->review_due_at,
        );

        if ($status !== $this->freshness_status) {
            $this->freshness_status = $status;
            $this->save();
        }

        return $status;
    }
}
