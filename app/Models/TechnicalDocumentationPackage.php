<?php

namespace App\Models;

use App\Enums\TechnicalDocumentationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product-scoped technical documentation package (§5.12).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property string $title
 * @property TechnicalDocumentationStatus $status
 * @property string $version_label
 * @property string $locale
 * @property int|null $supersedes_id
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Product|null $product
 * @property-read ProductVersion|null $productVersion
 * @property-read TechnicalDocumentationPackage|null $supersedes
 * @property-read User|null $publisher
 * @property-read \Illuminate\Database\Eloquent\Collection<int, TechnicalDocumentationSection> $sections
 */
#[Fillable([
    'organization_id',
    'product_id',
    'product_version_id',
    'title',
    'status',
    'version_label',
    'locale',
    'supersedes_id',
    'published_at',
    'published_by',
    'notes',
])]
class TechnicalDocumentationPackage extends Model
{
    protected function casts(): array
    {
        return [
            'status' => TechnicalDocumentationStatus::class,
            'published_at' => 'datetime',
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

    /** @return BelongsTo<ProductVersion, $this> */
    public function productVersion(): BelongsTo
    {
        return $this->belongsTo(ProductVersion::class);
    }

    /** @return BelongsTo<TechnicalDocumentationPackage, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return HasMany<TechnicalDocumentationSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(TechnicalDocumentationSection::class, 'package_id')
            ->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            TechnicalDocumentationStatus::Draft,
            TechnicalDocumentationStatus::UnderReview,
        ], true);
    }

    public function isPublished(): bool
    {
        return $this->status === TechnicalDocumentationStatus::Published;
    }
}
