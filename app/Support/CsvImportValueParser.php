<?php

namespace App\Support;

use App\Enums\CustomerCriticality;
use App\Enums\DeploymentEnvironment;
use Illuminate\Validation\ValidationException;

final class CsvImportValueParser
{
    public static function bool(?string $value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        $normalized = strtolower(trim($value));

        return in_array($normalized, ['1', 'true', 'yes', 'y', 'on'], true);
    }

    public static function criticality(?string $value): CustomerCriticality
    {
        if ($value === null) {
            return CustomerCriticality::Medium;
        }

        $criticality = CustomerCriticality::tryFrom(strtolower($value));

        if ($criticality === null) {
            throw ValidationException::withMessages([
                'criticality' => [Translations::get('customers.import.invalid_criticality')],
            ]);
        }

        return $criticality;
    }

    public static function environment(?string $value): DeploymentEnvironment
    {
        if ($value === null) {
            throw ValidationException::withMessages([
                'environment' => [Translations::get('products.deployments.import.missing_environment')],
            ]);
        }

        $environment = DeploymentEnvironment::tryFrom(strtolower($value));

        if ($environment === null) {
            throw ValidationException::withMessages([
                'environment' => [Translations::get('products.deployments.import.invalid_environment')],
            ]);
        }

        return $environment;
    }

    public static function date(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return $value;
    }
}
