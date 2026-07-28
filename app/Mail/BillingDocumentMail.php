<?php

namespace App\Mail;

use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BillingDocumentMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    public function __construct(
        public Organization $organization,
        public OrganizationBillingDocument $document,
    ) {
    }

    public function envelope(): Envelope
    {
        $type = $this->document->typeValue();

        return new Envelope(
            subject: sprintf(
                '[%s] %s — %s',
                config('app.name'),
                ucfirst($type),
                $this->document->title,
            ),
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.billing-document',
        );
    }

    /**
     * @return array<int, Attachment>
     */
    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->document->storage_path)
                ->as($this->document->source_filename),
        ];
    }
}
