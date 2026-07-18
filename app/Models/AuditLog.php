<?php

namespace App\Models;

use App\Enums\AuditEventSource;
use App\Enums\AuditEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'occurred_at',
    'event_type',
    'event_source',
    'is_success',
    'organization_id',
    'product_id',
    'user_id',
    'user_email',
    'user_name',
    'description',
])]
class AuditLog extends Model
{
    public const COLUMN_EVENT_TYPE = 'event_type';

    public const COLUMN_USER_EMAIL = 'user_email';

    public const COLUMN_USER_ID = 'user_id';

    public const COLUMN_ID = 'id';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'event_type' => AuditEventType::class,
            'event_source' => AuditEventSource::class,
            'is_success' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
