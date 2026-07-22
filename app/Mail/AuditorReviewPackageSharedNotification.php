<?php

namespace App\Mail;

use App\Models\AuditorReviewPackage;
use App\Models\Product;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AuditorReviewPackageSharedNotification extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public AuditorReviewPackage $package,
        public Product $product,
        public User $auditor,
        public string $organizationName,
        public string $reviewUrl,
        public string $sharedByName,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf(
                '[%s] Review package shared — %s',
                $this->product->name,
                $this->package->title,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.auditor-review-package-shared',
        );
    }
}
