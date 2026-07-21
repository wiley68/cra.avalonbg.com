<?php

namespace App\Support;

final class CustomerContactEmail
{
    public static function extract(?string $primaryContact): ?string
    {
        if ($primaryContact === null) {
            return null;
        }

        $trimmed = trim($primaryContact);

        if ($trimmed === '') {
            return null;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_EMAIL)) {
            return $trimmed;
        }

        if (preg_match('/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i', $trimmed, $matches) === 1) {
            return $matches[0];
        }

        return null;
    }
}
