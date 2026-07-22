<?php

namespace App\Models;

use App\Enums\AiConversationContextType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Product- or org-scoped AI assistant conversation.
 *
 * @property int $id
 * @property int $organization_id
 * @property int|null $product_id
 * @property int $user_id
 * @property AiConversationContextType $context_type
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Organization|null $organization
 * @property-read Product|null $product
 * @property-read User|null $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, AiMessage> $messages
 */
#[Fillable([
    'organization_id',
    'product_id',
    'user_id',
    'context_type',
])]
class AiConversation extends Model
{
    protected function casts(): array
    {
        return [
            'context_type' => AiConversationContextType::class,
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

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AiMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(AiMessage::class, 'conversation_id')->orderBy('id');
    }
}
