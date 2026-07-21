<?php

namespace App\Jobs;

use App\Enums\PatchCampaignTargetStatus;
use App\Mail\PatchCampaignCustomerNotification;
use App\Models\PatchCampaignTarget;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\CustomerContactEmail;
use App\Support\Translations;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendPatchCampaignCustomerNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $targetId,
        public ?int $actorUserId = null,
    ) {
    }

    public function handle(): void
    {
        $target = PatchCampaignTarget::query()
            ->with([
                'campaign.targetVersion',
                'campaign.product',
                'deployment.customer',
                'deployment.productVersion',
            ])
            ->find($this->targetId);

        if ($target === null || $target->campaign === null || $target->deployment === null) {
            return;
        }

        $customer = $target->deployment->customer;
        $email = CustomerContactEmail::extract($customer?->primary_contact);

        if ($email === null) {
            return;
        }

        $campaign = $target->campaign;
        $product = $campaign->product;

        if ($product === null) {
            return;
        }

        Mail::to($email)->send(new PatchCampaignCustomerNotification(
            campaign: $campaign,
            target: $target,
            product: $product,
            customerName: $customer?->name ?? '',
            targetVersionNumber: $campaign->targetVersion?->version_number ?? '',
            currentVersionNumber: $target->deployment->productVersion?->version_number,
            environment: $target->deployment->environment->value,
        ));

        $previousStatus = $target->status->value;
        $note = Translations::get('products.campaigns.notification_email_stub_note', [
            'email' => $email,
        ]);

        $target->update([
            'status' => PatchCampaignTargetStatus::Notified,
            'notified_at' => $target->notified_at ?? now(),
            'notification_note' => $target->notification_note
                ? $target->notification_note . "\n" . $note
                : $note,
        ]);

        $actor = $this->actorUserId !== null
            ? User::query()->find($this->actorUserId)
            : null;

        if ($actor !== null) {
            AuditLogger::logCampaignTargetUpdated(
                $target->fresh(['campaign', 'deployment']),
                $actor,
                $previousStatus,
            );
        }
    }
}
