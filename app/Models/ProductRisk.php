<?php

namespace App\Models;

use App\Enums\ProductRiskStatus;
use App\Enums\RiskCategory;
use App\Enums\RiskImpact;
use App\Enums\RiskLevel;
use App\Enums\RiskLikelihood;
use App\Enums\RiskTreatment;
use App\Services\ProductRiskService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property string $title
 * @property string|null $asset
 * @property string|null $threat
 * @property string|null $weakness
 * @property string|null $attack_scenario
 * @property RiskCategory $category
 * @property RiskLikelihood $likelihood
 * @property RiskImpact $impact
 * @property RiskLikelihood|null $residual_likelihood
 * @property RiskImpact|null $residual_impact
 * @property RiskTreatment $treatment
 * @property string|null $treatment_plan
 * @property ProductRiskStatus $status
 * @property int|null $owner_user_id
 * @property Carbon|null $deadline
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'product_id',
    'product_version_id',
    'title',
    'asset',
    'threat',
    'weakness',
    'attack_scenario',
    'category',
    'likelihood',
    'impact',
    'residual_likelihood',
    'residual_impact',
    'treatment',
    'treatment_plan',
    'status',
    'owner_user_id',
    'deadline',
    'reviewed_by',
    'reviewed_at',
])]
class ProductRisk extends Model
{
    protected function casts(): array
    {
        return [
            'category' => RiskCategory::class,
            'likelihood' => RiskLikelihood::class,
            'impact' => RiskImpact::class,
            'residual_likelihood' => RiskLikelihood::class,
            'residual_impact' => RiskImpact::class,
            'treatment' => RiskTreatment::class,
            'status' => ProductRiskStatus::class,
            'deadline' => 'date',
            'reviewed_at' => 'datetime',
        ];
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

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function controls(): BelongsToMany
    {
        return $this->belongsToMany(Control::class, 'product_risk_control')
            ->withTimestamps();
    }

    public function requirements(): BelongsToMany
    {
        return $this->belongsToMany(Requirement::class, 'product_risk_requirement')
            ->withTimestamps();
    }

    public function initialRiskLevel(): RiskLevel
    {
        return ProductRiskService::levelFromScores(
            $this->likelihood->value,
            $this->impact->value,
        );
    }

    public function residualRiskLevel(): ?RiskLevel
    {
        if ($this->residual_likelihood === null || $this->residual_impact === null) {
            return null;
        }

        return ProductRiskService::levelFromScores(
            $this->residual_likelihood->value,
            $this->residual_impact->value,
        );
    }
}
