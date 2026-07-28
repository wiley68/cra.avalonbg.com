<?php

namespace App\Services;

use App\Enums\BillingDocumentType;
use App\Enums\BillingInterval;
use App\Enums\PaymentMethod;
use App\Enums\RoleSlug;
use App\Mail\BillingDocumentMail;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingDocumentService
{
    /**
     * @return list<array<string, mixed>>
     */
    public function listPayload(Organization $organization): array
    {
        return $organization->billingDocuments()
            ->latest('id')
            ->get()
            ->map(fn(OrganizationBillingDocument $document) => $this->documentPayload($document))
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    public function documentPayload(OrganizationBillingDocument $document): array
    {
        return [
            'id' => $document->id,
            'type' => $document->typeValue(),
            'title' => $document->title,
            'source_filename' => $document->source_filename,
            'size_bytes' => $document->size_bytes,
            'mime_type' => $document->mime_type,
            'checksum_sha256' => $document->checksum_sha256,
            'uploaded_by' => $document->uploaded_by,
            'sent_at' => $document->sent_at?->toIso8601String(),
            'sent_to_email' => $document->sent_to_email,
            'notes' => $document->notes,
            'created_at' => $document->created_at?->toIso8601String(),
        ];
    }

    public function upload(
        Organization $organization,
        User $actor,
        UploadedFile $file,
        BillingDocumentType $type,
        ?string $title = null,
        ?string $notes = null,
    ): OrganizationBillingDocument {
        $contents = method_exists($file, 'getContent')
            ? $file->getContent()
            : $file->get();

        if ($contents === false || $contents === null || $contents === '') {
            throw ValidationException::withMessages([
                'file' => Translations::get('billing.documents.errors.file_empty'),
            ]);
        }

        $originalName = $file->getClientOriginalName();
        $resolvedTitle = filled($title) ? trim((string) $title) : pathinfo($originalName, PATHINFO_FILENAME);
        if ($resolvedTitle === '') {
            $resolvedTitle = $originalName;
        }

        $storagePath = sprintf(
            'billing/%d/%s_%s',
            $organization->id,
            uniqid('bd_', true),
            $originalName,
        );

        Storage::disk('local')->put($storagePath, $contents);

        $document = OrganizationBillingDocument::query()->create([
            'organization_id' => $organization->id,
            'type' => $type->value,
            'title' => $resolvedTitle,
            'storage_path' => $storagePath,
            'source_filename' => $originalName,
            'checksum_sha256' => hash('sha256', $contents),
            'size_bytes' => strlen($contents),
            'mime_type' => $file->getClientMimeType() ?: $file->getMimeType(),
            'uploaded_by' => $actor->id,
            'notes' => $notes,
        ]);

        AuditLogger::logBillingDocumentUploaded($document, $actor);

        return $document;
    }

    /**
     * Generate a simple subscription license PDF and store it as a license document.
     */
    public function generateLicense(
        Organization $organization,
        User $actor,
        ?string $title = null,
        ?string $notes = null,
    ): OrganizationBillingDocument {
        $payload = $this->licenseViewPayload($organization);
        $pdfBinary = Pdf::loadView('pdf.billing-license', $payload)
            ->setPaper('a4', 'portrait')
            ->output();

        if ($pdfBinary === '' || !is_string($pdfBinary)) {
            throw ValidationException::withMessages([
                'license' => Translations::get('billing.documents.errors.generate_failed'),
            ]);
        }

        $filename = sprintf(
            'license-%s-%s.pdf',
            Str::slug($organization->slug !== '' ? $organization->slug : (string) $organization->id),
            now()->format('Ymd'),
        );
        $resolvedTitle = filled($title)
            ? trim((string) $title)
            : Translations::get('billing.documents.license_pdf.default_title', [
                'organization' => $organization->name,
            ]);
        $resolvedNotes = filled($notes)
            ? trim((string) $notes)
            : Translations::get('billing.documents.license_pdf.default_notes');

        $storagePath = sprintf(
            'billing/%d/%s_%s',
            $organization->id,
            uniqid('bd_', true),
            $filename,
        );

        Storage::disk('local')->put($storagePath, $pdfBinary);

        $document = OrganizationBillingDocument::query()->create([
            'organization_id' => $organization->id,
            'type' => BillingDocumentType::License->value,
            'title' => $resolvedTitle,
            'storage_path' => $storagePath,
            'source_filename' => $filename,
            'checksum_sha256' => hash('sha256', $pdfBinary),
            'size_bytes' => strlen($pdfBinary),
            'mime_type' => 'application/pdf',
            'uploaded_by' => $actor->id,
            'notes' => $resolvedNotes,
        ]);

        AuditLogger::logBillingDocumentUploaded($document, $actor, source: 'generated');

        return $document;
    }

    /**
     * @return array{
     *     issuer: string,
     *     license: array{
     *         organization_name: string,
     *         organization_slug: string,
     *         reference: string,
     *         plan_label: string,
     *         interval_label: string,
     *         payment_method_label: string,
     *         billing_status_label: string,
     *         activated_at: string|null,
     *         issued_at: string
     *     }
     * }
     */
    public function licenseViewPayload(Organization $organization): array
    {
        $plan = $organization->resolvedSubscriptionPlan();
        $interval = $organization->billing_interval instanceof BillingInterval
            ? $organization->billing_interval
            : BillingInterval::tryFrom((string) ($organization->billing_interval ?? ''));
        $paymentMethod = $organization->payment_method instanceof PaymentMethod
            ? $organization->payment_method
            : PaymentMethod::tryFrom((string) ($organization->payment_method ?? ''));
        $status = $organization->resolvedBillingStatus();

        $reference = sprintf(
            'LIC-%d-%s-%s',
            $organization->id,
            now()->format('Ymd'),
            Str::upper(Str::random(6)),
        );

        return [
            'issuer' => (string) (config('billing.bank.beneficiary') ?: config('app.name')),
            'license' => [
                'organization_name' => $organization->name,
                'organization_slug' => $organization->slug,
                'reference' => $reference,
                'plan_label' => Translations::get('admin.organizations.plans.' . $plan->value),
                'interval_label' => $interval !== null
                    ? Translations::get('billing.interval.' . $interval->value)
                    : Translations::get('billing.interval.none'),
                'payment_method_label' => $paymentMethod !== null
                    ? Translations::get('billing.payment_method.' . $paymentMethod->value)
                    : Translations::get('billing.payment_method.none'),
                'billing_status_label' => Translations::get('billing.status.' . $status->value),
                'activated_at' => $organization->billing_activated_at?->timezone(config('app.timezone'))->format('Y-m-d H:i'),
                'issued_at' => now()->timezone(config('app.timezone'))->format('Y-m-d H:i'),
            ],
        ];
    }

    public function download(OrganizationBillingDocument $document): StreamedResponse
    {
        $disk = Storage::disk('local');

        if (!$disk instanceof FilesystemAdapter) {
            abort(500, 'Local billing disk is misconfigured.');
        }

        if (!$disk->exists($document->storage_path)) {
            abort(404);
        }

        return $disk->download(
            $document->storage_path,
            $document->source_filename,
        );
    }

    public function send(
        OrganizationBillingDocument $document,
        User $actor,
        Organization $organization,
    ): OrganizationBillingDocument {
        if ($document->organization_id !== $organization->id) {
            abort(404);
        }

        $email = $this->resolveRecipientEmail($organization);

        if ($email === null) {
            throw ValidationException::withMessages([
                'billing_email' => Translations::get('billing.documents.errors.no_recipient'),
            ]);
        }

        Mail::to($email)->send(new BillingDocumentMail(
            organization: $organization,
            document: $document,
        ));

        $document->forceFill([
            'sent_at' => now(),
            'sent_to_email' => $email,
            'sent_by' => $actor->id,
        ])->save();

        AuditLogger::logBillingDocumentSent($document->fresh(), $actor, $email);

        return $document->fresh();
    }

    public function delete(
        OrganizationBillingDocument $document,
        User $actor,
        Organization $organization,
    ): void {
        if ($document->organization_id !== $organization->id) {
            abort(404);
        }

        $path = $document->storage_path;
        $documentId = $document->id;
        $type = $document->typeValue();
        $title = $document->title;

        $document->delete();

        if ($path !== '' && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }

        AuditLogger::logBillingDocumentDeleted(
            $organization,
            $actor,
            $documentId,
            $type,
            $title,
        );
    }

    public function resolveRecipientEmail(Organization $organization): ?string
    {
        if (filled($organization->billing_email)) {
            return (string) $organization->billing_email;
        }

        $ownerRoleId = Role::query()
            ->where('slug', RoleSlug::OrganizationOwner->value)
            ->value('id');

        if ($ownerRoleId === null) {
            return null;
        }

        $owner = $organization->users()
            ->wherePivot('role_id', $ownerRoleId)
            ->orderBy('users.id')
            ->first();

        return $owner?->email;
    }

    public function deleteStorageForOrganization(Organization $organization): void
    {
        Storage::disk('local')->deleteDirectory('billing/' . $organization->id);
    }
}
