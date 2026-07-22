<?php

namespace App\Jobs;

use App\Mail\AuditorReviewPackageSharedNotification;
use App\Models\AuditorReviewPackage;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

class SendAuditorReviewPackageSharedNotificationJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public int $packageId,
        public int $auditorUserId,
        public ?int $actorUserId = null,
    ) {
    }

    public function handle(): void
    {
        $package = AuditorReviewPackage::query()
            ->with(['product', 'organization', 'creator'])
            ->find($this->packageId);

        $auditor = User::query()->find($this->auditorUserId);

        if ($package === null || $auditor === null || blank($auditor->email)) {
            return;
        }

        $product = $package->product;

        if ($product === null) {
            return;
        }

        $actor = $this->actorUserId !== null
            ? User::query()->find($this->actorUserId)
            : null;

        Mail::to($auditor->email)->send(new AuditorReviewPackageSharedNotification(
            package: $package,
            product: $product,
            auditor: $auditor,
            organizationName: $package->organization?->name ?? '',
            reviewUrl: route('auditor.packages.show', $package),
            sharedByName: $actor?->name ?? $package->creator?->name ?? '',
        ));
    }
}
