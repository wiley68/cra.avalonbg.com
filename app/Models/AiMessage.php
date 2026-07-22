<?php

namespace App\Models;

use App\Enums\AiMessageRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only message within an AI conversation.
 *
 * @property int $id
 * @property int $conversation_id
 * @property AiMessageRole $role
 * @property string $content
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read AiConversation|null $conversation
 */
#[Fillable([
    'conversation_id',
    'role',
    'content',
    'metadata',
])]
class AiMessage extends Model
{
    protected function casts(): array
    {
        return [
            'role' => AiMessageRole::class,
            'metadata' => 'array',
        ];
    }

    /** @return BelongsTo<AiConversation, $this> */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }
}
