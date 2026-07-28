<?php

namespace App\Models;

use App\Enums\BillingDocumentType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'type',
    'title',
    'storage_path',
    'source_filename',
    'checksum_sha256',
    'size_bytes',
    'mime_type',
    'uploaded_by',
    'sent_at',
    'sent_to_email',
    'sent_by',
    'notes',
])]
class OrganizationBillingDocument extends Model
{
    protected function casts(): array
    {
        return [
            'type' => BillingDocumentType::class,
            'size_bytes' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    public function typeValue(): string
    {
        return $this->type instanceof BillingDocumentType
            ? $this->type->value
            : (string) $this->type;
    }
}
