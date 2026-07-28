<?php

namespace App\Models;

use App\Enums\SsoProvider;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'provider',
    'issuer',
    'client_id',
    'client_secret',
    'allowed_email_domains',
    'is_enabled',
])]
#[Hidden(['client_secret'])]
class OrganizationSsoConnection extends Model
{
    protected function casts(): array
    {
        return [
            'provider' => SsoProvider::class,
            'client_secret' => 'encrypted',
            'allowed_email_domains' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return list<string>
     */
    public function normalizedDomains(): array
    {
        $domains = $this->allowed_email_domains ?? [];

        return array_values(array_unique(array_filter(array_map(
            static fn($domain): string => strtolower(trim((string) $domain)),
            is_array($domains) ? $domains : [],
        ))));
    }

    public function allowsEmailDomain(string $email): bool
    {
        $parts = explode('@', strtolower(trim($email)));
        if (count($parts) !== 2 || $parts[1] === '') {
            return false;
        }

        $domains = $this->normalizedDomains();

        if ($domains === []) {
            return false;
        }

        return in_array($parts[1], $domains, true);
    }
}
