<?php

namespace App\Models;

use App\Enums\ClassificationStatus;
use App\Enums\LicensingModel;
use App\Enums\ProductType;
use App\Enums\ScopeStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property string $name
 * @property string $slug
 * @property string|null $product_line
 * @property string|null $description
 * @property string|null $intended_purpose
 * @property ProductType $product_type
 * @property string|null $manufacturer
 * @property string|null $trademark
 * @property LicensingModel $licensing_model
 * @property bool $has_remote_data_processing
 * @property bool $has_network_connectivity
 * @property string|null $deployment_model
 * @property string|null $support_period_notes
 * @property string|null $end_of_support_policy
 * @property int|null $product_owner_user_id
 * @property int|null $security_contact_user_id
 * @property ScopeStatus $scope_status
 * @property string|null $scope_rationale
 * @property Carbon|null $scope_reviewed_at
 * @property int|null $scope_reviewed_by
 * @property ClassificationStatus $classification_status
 * @property string|null $classification_rationale
 * @property Carbon|null $classification_reviewed_at
 * @property int|null $classification_reviewed_by
 * @property Carbon|null $classification_next_review_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'name',
    'slug',
    'product_line',
    'description',
    'intended_purpose',
    'product_type',
    'manufacturer',
    'trademark',
    'licensing_model',
    'has_remote_data_processing',
    'has_network_connectivity',
    'deployment_model',
    'support_period_notes',
    'end_of_support_policy',
    'product_owner_user_id',
    'security_contact_user_id',
    'scope_status',
    'scope_rationale',
    'scope_reviewed_at',
    'scope_reviewed_by',
    'classification_status',
    'classification_rationale',
    'classification_reviewed_at',
    'classification_reviewed_by',
    'classification_next_review_at',
])]
class Product extends Model
{
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'licensing_model' => LicensingModel::class,
            'has_remote_data_processing' => 'boolean',
            'has_network_connectivity' => 'boolean',
            'scope_status' => ScopeStatus::class,
            'scope_reviewed_at' => 'datetime',
            'classification_status' => ClassificationStatus::class,
            'classification_reviewed_at' => 'datetime',
            'classification_next_review_at' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(ProductVersion::class);
    }

    public function scopeAssessments(): HasMany
    {
        return $this->hasMany(ProductScopeAssessment::class);
    }

    public function latestScopeAssessment(): ?ProductScopeAssessment
    {
        return $this->scopeAssessments()->latest('id')->first();
    }

    public function classifications(): HasMany
    {
        return $this->hasMany(ProductClassification::class);
    }

    public function latestClassification(): ?ProductClassification
    {
        return $this->classifications()->latest('id')->first();
    }

    public function productOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'product_owner_user_id');
    }

    public function securityContact(): BelongsTo
    {
        return $this->belongsTo(User::class, 'security_contact_user_id');
    }

    public function scopeReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scope_reviewed_by');
    }

    public function classificationReviewedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'classification_reviewed_by');
    }
}
