<?php

namespace App\Enums;

enum SsoProvider: string
{
    case Generic = 'generic';
    case Entra = 'entra';
    case Saml = 'saml';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_values(array_column(self::cases(), 'value'));
    }

    public function isOidc(): bool
    {
        return $this === self::Generic || $this === self::Entra;
    }

    public function isSaml(): bool
    {
        return $this === self::Saml;
    }
}
