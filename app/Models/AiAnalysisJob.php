<?php

namespace App\Models;

use App\Enums\AiAnalysisJobStatus;
use App\Enums\AiAnalysisJobType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $organization_id
 * @property int $product_id
 * @property int $user_id
 * @property int|null $conversation_id
 * @property AiAnalysisJobType $type
 * @property AiAnalysisJobStatus $status
 * @property array<string, mixed>|null $payload
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'organization_id',
    'product_id',
    'user_id',
    'conversation_id',
    'type',
    'status',
    'payload',
    'error_message',
    'started_at',
    'finished_at',
])]
class AiAnalysisJob extends Model
{
    protected function casts(): array
    {
        return [
            'type' => AiAnalysisJobType::class,
            'status' => AiAnalysisJobStatus::class,
            'payload' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'conversation_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            AiAnalysisJobStatus::Succeeded,
            AiAnalysisJobStatus::Failed,
        ], true);
    }
}
