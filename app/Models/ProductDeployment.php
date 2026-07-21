<?php

namespace App\Models;

use App\Enums\DeploymentEnvironment;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $customer_id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property DeploymentEnvironment $environment
 * @property Carbon|null $installation_date
 * @property bool $internet_exposure
 * @property string|null $update_channel
 * @property Carbon|null $last_confirmed_at
 * @property bool $custom_modifications
 * @property bool $end_of_support_exception
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'customer_id',
    'product_id',
    'product_version_id',
    'environment',
    'installation_date',
    'internet_exposure',
    'update_channel',
    'last_confirmed_at',
    'custom_modifications',
    'end_of_support_exception',
    'notes',
])]
class ProductDeployment extends Model
{
    protected function casts(): array
    {
        return [
            'environment' => DeploymentEnvironment::class,
            'installation_date' => 'date',
            'internet_exposure' => 'boolean',
            'last_confirmed_at' => 'datetime',
            'custom_modifications' => 'boolean',
            'end_of_support_exception' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    public function campaignTargets(): HasMany
    {
        return $this->hasMany(PatchCampaignTarget::class, 'deployment_id');
    }
}
