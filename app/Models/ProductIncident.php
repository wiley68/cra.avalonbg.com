<?php

namespace App\Models;

use App\Enums\IncidentSeverity;
use App\Enums\IncidentStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product-scoped security incident (§5.10) — separate from vulnerability.
 *
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property string $title
 * @property IncidentStatus $status
 * @property IncidentSeverity $severity
 * @property string|null $summary
 * @property string|null $root_cause
 * @property string|null $corrective_measures
 * @property string|null $lessons_learned
 * @property int|null $product_vulnerability_id
 * @property int|null $owner_user_id
 * @property Carbon|null $actual_started_at
 * @property Carbon|null $detected_at
 * @property Carbon|null $awareness_at
 * @property Carbon|null $classified_at
 * @property Carbon|null $closed_at
 * @property int|null $closed_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Product|null $product
 * @property-read ProductVulnerability|null $vulnerability
 * @property-read User|null $owner
 * @property-read User|null $closer
 */
#[Fillable([
    'organization_id',
    'product_id',
    'title',
    'status',
    'severity',
    'summary',
    'root_cause',
    'corrective_measures',
    'lessons_learned',
    'product_vulnerability_id',
    'owner_user_id',
    'actual_started_at',
    'detected_at',
    'awareness_at',
    'classified_at',
    'closed_at',
    'closed_by',
    'notes',
])]
class ProductIncident extends Model
{
    protected function casts(): array
    {
        return [
            'status' => IncidentStatus::class,
            'severity' => IncidentSeverity::class,
            'actual_started_at' => 'datetime',
            'detected_at' => 'datetime',
            'awareness_at' => 'datetime',
            'classified_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Organization, $this> */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /** @return BelongsTo<Product, $this> */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** @return BelongsTo<ProductVulnerability, $this> */
    public function vulnerability(): BelongsTo
    {
        return $this->belongsTo(ProductVulnerability::class, 'product_vulnerability_id');
    }

    /** @return BelongsTo<User, $this> */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    /** @return HasMany<IncidentTimelineEvent, $this> */
    public function timelineEvents(): HasMany
    {
        return $this->hasMany(IncidentTimelineEvent::class, 'incident_id')
            ->orderBy('occurred_at')
            ->orderBy('id');
    }

    /** @return HasMany<IncidentReport, $this> */
    public function reports(): HasMany
    {
        return $this->hasMany(IncidentReport::class, 'incident_id')
            ->orderByDesc('submitted_at')
            ->orderByDesc('id');
    }

    /** @return BelongsToMany<ProductVersion, $this> */
    public function versions(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductVersion::class,
            'incident_product_versions',
            'incident_id',
            'product_version_id',
        )->withTimestamps();
    }

    /** @return BelongsToMany<Customer, $this> */
    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(
            Customer::class,
            'incident_customers',
            'incident_id',
            'customer_id',
        )->withTimestamps();
    }

    /** @return BelongsToMany<ProductDeployment, $this> */
    public function deployments(): BelongsToMany
    {
        return $this->belongsToMany(
            ProductDeployment::class,
            'incident_product_deployments',
            'incident_id',
            'product_deployment_id',
        )->withTimestamps();
    }

    public function isOpen(): bool
    {
        return in_array($this->status, IncidentStatus::active(), true);
    }

    public function isTerminal(): bool
    {
        return $this->status->isTerminal();
    }
}
