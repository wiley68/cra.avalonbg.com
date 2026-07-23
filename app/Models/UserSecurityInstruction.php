<?php

namespace App\Models;

use App\Enums\UserSecurityInstructionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product-scoped user security instructions document (§5.17).
 *
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property int|null $product_version_id
 * @property string $title
 * @property UserSecurityInstructionStatus $status
 * @property string $version_label
 * @property string $locale
 * @property int|null $supersedes_id
 * @property int|null $paired_instruction_id
 * @property Carbon|null $published_at
 * @property int|null $published_by
 * @property int|null $evidence_id
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Product|null $product
 * @property-read ProductVersion|null $productVersion
 * @property-read UserSecurityInstruction|null $supersedes
 * @property-read UserSecurityInstruction|null $pairedInstruction
 * @property-read User|null $publisher
 * @property-read Evidence|null $evidence
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
    'paired_instruction_id',
    'published_at',
    'published_by',
    'evidence_id',
    'notes',
])]
class UserSecurityInstruction extends Model
{
    protected function casts(): array
    {
        return [
            'status' => UserSecurityInstructionStatus::class,
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

    /** @return BelongsTo<UserSecurityInstruction, $this> */
    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supersedes_id');
    }

    /** @return BelongsTo<UserSecurityInstruction, $this> */
    public function pairedInstruction(): BelongsTo
    {
        return $this->belongsTo(self::class, 'paired_instruction_id');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsTo<Evidence, $this> */
    public function evidence(): BelongsTo
    {
        return $this->belongsTo(Evidence::class);
    }

    /** @return HasMany<UserSecurityInstructionSection, $this> */
    public function sections(): HasMany
    {
        return $this->hasMany(UserSecurityInstructionSection::class, 'instruction_id')
            ->orderBy('sort_order');
    }

    public function isEditable(): bool
    {
        return in_array($this->status, [
            UserSecurityInstructionStatus::Draft,
            UserSecurityInstructionStatus::UnderReview,
        ], true);
    }

    public function isPublished(): bool
    {
        return $this->status === UserSecurityInstructionStatus::Published;
    }
}
