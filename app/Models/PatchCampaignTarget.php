<?php

namespace App\Models;

use App\Enums\PatchCampaignTargetStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $campaign_id
 * @property int $deployment_id
 * @property PatchCampaignTargetStatus $status
 * @property Carbon|null $notified_at
 * @property Carbon|null $acknowledged_at
 * @property Carbon|null $confirmed_at
 * @property string|null $notification_note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read PatchCampaign|null $campaign
 * @property-read ProductDeployment|null $deployment
 * @property-read \Illuminate\Database\Eloquent\Collection<int, PatchCampaignTargetNotificationEvent> $notificationEvents
 */
#[Fillable([
    'campaign_id',
    'deployment_id',
    'status',
    'notified_at',
    'acknowledged_at',
    'confirmed_at',
    'notification_note',
])]
class PatchCampaignTarget extends Model
{
    protected function casts(): array
    {
        return [
            'status' => PatchCampaignTargetStatus::class,
            'notified_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PatchCampaign, $this> */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PatchCampaign::class, 'campaign_id');
    }

    /** @return BelongsTo<ProductDeployment, $this> */
    public function deployment(): BelongsTo
    {
        return $this->belongsTo(ProductDeployment::class, 'deployment_id');
    }

    /** @return HasMany<PatchCampaignTargetNotificationEvent, $this> */
    public function notificationEvents(): HasMany
    {
        return $this->hasMany(
            PatchCampaignTargetNotificationEvent::class,
            'patch_campaign_target_id',
        );
    }
}
