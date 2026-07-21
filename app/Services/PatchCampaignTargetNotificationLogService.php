<?php

namespace App\Services;

use App\Enums\PatchCampaignTargetNotificationChannel;
use App\Enums\PatchCampaignTargetNotificationEventType;
use App\Models\PatchCampaignTarget;
use App\Models\PatchCampaignTargetNotificationEvent;
use App\Models\User;

class PatchCampaignTargetNotificationLogService
{
    /**
     * @return list<array{
     *     id: int,
     *     event_type: string,
     *     channel: string,
     *     status_before: string|null,
     *     status_after: string|null,
     *     body: string|null,
     *     recipient: string|null,
     *     created_by: string|null,
     *     created_at: string
     * }>
     */
    public function payloadForTarget(PatchCampaignTarget $target): array
    {
        return PatchCampaignTargetNotificationEvent::query()
            ->where('patch_campaign_target_id', $target->id)
            ->with('creator:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn(PatchCampaignTargetNotificationEvent $event) => [
                'id' => $event->id,
                'event_type' => $event->event_type->value,
                'channel' => $event->channel->value,
                'status_before' => $event->status_before,
                'status_after' => $event->status_after,
                'body' => $event->body,
                'recipient' => $event->recipient,
                'created_by' => $event->creator?->name,
                'created_at' => $event->created_at->toIso8601String(),
            ])
            ->all();
    }

    public function record(
        PatchCampaignTarget $target,
        PatchCampaignTargetNotificationEventType $eventType,
        PatchCampaignTargetNotificationChannel $channel,
        ?User $actor = null,
        ?string $statusBefore = null,
        ?string $statusAfter = null,
        ?string $body = null,
        ?string $recipient = null,
    ): PatchCampaignTargetNotificationEvent {
        return PatchCampaignTargetNotificationEvent::query()->create([
            'patch_campaign_target_id' => $target->id,
            'event_type' => $eventType,
            'channel' => $channel,
            'status_before' => $statusBefore,
            'status_after' => $statusAfter,
            'body' => $body,
            'recipient' => $recipient,
            'created_by' => $actor?->id,
            'created_at' => now(),
        ]);
    }
}
