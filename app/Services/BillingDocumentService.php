<?php

namespace App\Services;

use App\Enums\BillingDocumentType;
use App\Enums\RoleSlug;
use App\Mail\BillingDocumentMail;
use App\Models\Organization;
use App\Models\OrganizationBillingDocument;
use App\Models\Role;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
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
