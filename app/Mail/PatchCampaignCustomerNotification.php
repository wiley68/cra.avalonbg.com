<?php

namespace App\Mail;

use App\Models\PatchCampaign;
use App\Models\PatchCampaignTarget;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class PatchCampaignCustomerNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public PatchCampaign $campaign,
        public PatchCampaignTarget $target,
        public Product $product,
        public string $customerName,
        public string $targetVersionNumber,
        public ?string $currentVersionNumber,
        public string $environment,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[%s] Security update available — %s',
                $this->product->name,
                $this->campaign->title,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.patch-campaign-customer-notification',
        );
    }
}
