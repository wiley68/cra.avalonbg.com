<?php

namespace App\Models;

use App\Enums\PatchCampaignTargetNotificationChannel;
use App\Enums\PatchCampaignTargetNotificationEventType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only notification / status event log for a patch campaign target.
 *
 * @property int $id
 * @property int $patch_campaign_target_id
 * @property PatchCampaignTargetNotificationEventType $event_type
 * @property PatchCampaignTargetNotificationChannel $channel
 * @property string|null $status_before
 * @property string|null $status_after
 * @property string|null $body
 * @property string|null $recipient
 * @property int|null $created_by
 * @property Carbon $created_at
 * @property-read PatchCampaignTarget|null $target
 * @property-read User|null $creator
 */
class PatchCampaignTargetNotificationEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'patch_campaign_target_id',
        'event_type',
        'channel',
        'status_before',
        'status_after',
        'body',
        'recipient',
        'created_by',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'event_type' => PatchCampaignTargetNotificationEventType::class,
            'channel' => PatchCampaignTargetNotificationChannel::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<PatchCampaignTarget, $this> */
    public function target(): BelongsTo
    {
        return $this->belongsTo(PatchCampaignTarget::class, 'patch_campaign_target_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
