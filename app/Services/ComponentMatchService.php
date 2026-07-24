<?php

namespace App\Services;

use App\Enums\PackageEcosystem;
use App\Models\ProductComponent;

class ComponentMatchService
{
    /**
     * Match product SBOM/components to a scanner package by purl and/or name.
     *
     * @return list<array{id: int, name: string, version: string|null, purl: string|null}>
     */
    public function matchForPackage(
        int $productId,
        ?string $packageName = null,
        ?string $packageEcosystem = null,
        ?string $packagePurl = null,
    ): array {
        $parsed = $this->parsePackage($packageName, $packageEcosystem, $packagePurl);

        if ($parsed['name'] === null && $parsed['purl_identity'] === null && $parsed['purl_exact'] === null) {
            return [];
        }

        $components = ProductComponent::query()
            ->where('product_id', $productId)
            ->orderBy('id')
            ->get(['id', 'name', 'version', 'purl', 'package_ecosystem']);

        $matched = [];

        foreach ($components as $component) {
            if ($this->componentMatches($component, $parsed)) {
                $matched[] = [
                    'id' => $component->id,
                    'name' => $component->name,
                    'version' => $component->version,
                    'purl' => $component->purl,
                ];
            }

            if (count($matched) >= 20) {
                break;
            }
        }

        return $matched;
    }

    /**
     * @return list<int>
     */
    public function matchIdsForPackage(
        int $productId,
        ?string $packageName = null,
        ?string $packageEcosystem = null,
        ?string $packagePurl = null,
    ): array {
        return array_values(array_map(
            static fn (array $row): int => $row['id'],
            $this->matchForPackage($productId, $packageName, $packageEcosystem, $packagePurl),
        ));
    }

    /**
     * @return array{
     *     name: string|null,
     *     version: string|null,
     *     ecosystem: string|null,
     *     purl_exact: string|null,
     *     purl_identity: string|null
     * }
     */
    public function parsePackage(
        ?string $packageName,
        ?string $packageEcosystem = null,
        ?string $packagePurl = null,
    ): array {
        $purlExact = $this->normalizePurl($packagePurl);
        $purlIdentity = $purlExact !== null ? $this->purlIdentity($purlExact) : null;

        $name = null;
        $version = null;

        $rawName = is_string($packageName) ? trim($packageName) : '';
        if ($rawName !== '') {
            if (str_starts_with(strtolower($rawName), 'pkg:')) {
                $fromName = $this->normalizePurl($rawName);
                if ($fromName !== null) {
                    $purlExact ??= $fromName;
                    $purlIdentity ??= $this->purlIdentity($fromName);
                    $name = $this->nameFromPurl($fromName);
                    $version = $this->versionFromPurl($fromName);
                }
            } else {
                [$name, $version] = $this->splitNameVersion($rawName);
            }
        }

        if ($name === null && $purlExact !== null) {
            $name = $this->nameFromPurl($purlExact);
            $version ??= $this->versionFromPurl($purlExact);
        }

        $ecosystem = $this->normalizeEcosystem($packageEcosystem)
            ?? ($purlExact !== null ? $this->ecosystemFromPurl($purlExact)?->value : null);

        return [
            'name' => $name !== null && $name !== '' ? strtolower($name) : null,
            'version' => $version,
            'ecosystem' => $ecosystem,
            'purl_exact' => $purlExact,
            'purl_identity' => $purlIdentity,
        ];
    }

    /**
     * @param  array{
     *     name: string|null,
     *     version: string|null,
     *     ecosystem: string|null,
     *     purl_exact: string|null,
     *     purl_identity: string|null
     * }  $parsed
     */
    private function componentMatches(ProductComponent $component, array $parsed): bool
    {
        $componentPurl = $this->normalizePurl($component->purl);

        if ($parsed['purl_exact'] !== null && $componentPurl !== null && $componentPurl === $parsed['purl_exact']) {
            return true;
        }

        if (
            $parsed['purl_identity'] !== null
            && $componentPurl !== null
            && $this->purlIdentity($componentPurl) === $parsed['purl_identity']
        ) {
            return true;
        }

        if ($parsed['name'] === null) {
            return false;
        }

        if (strtolower($component->name) !== $parsed['name']) {
            return false;
        }

        if ($parsed['ecosystem'] !== null && $component->package_ecosystem->value !== $parsed['ecosystem']) {
            return false;
        }

        return true;
    }

    private function normalizePurl(?string $purl): ?string
    {
        if ($purl === null) {
            return null;
        }

        $trimmed = trim($purl);
        if ($trimmed === '') {
            return null;
        }

        $withoutQuery = explode('?', $trimmed, 2)[0];

        return strtolower($withoutQuery);
    }

    private function purlIdentity(string $normalizedPurl): string
    {
        return explode('@', $normalizedPurl, 2)[0];
    }

    private function nameFromPurl(string $normalizedPurl): ?string
    {
        if (! preg_match('#^pkg:[^/]+/(.+)$#', $normalizedPurl, $matches)) {
            return null;
        }

        $path = explode('@', $matches[1], 2)[0];
        $segments = explode('/', $path);
        $name = end($segments);

        return is_string($name) && $name !== '' ? $name : null;
    }

    private function versionFromPurl(string $normalizedPurl): ?string
    {
        if (! str_contains($normalizedPurl, '@')) {
            return null;
        }

        $version = explode('@', $normalizedPurl, 2)[1] ?? '';

        return $version !== '' ? $version : null;
    }

    /**
     * @return array{0: string|null, 1: string|null}
     */
    private function splitNameVersion(string $raw): array
    {
        if (! str_contains($raw, '@')) {
            return [$raw, null];
        }

        [$name, $version] = explode('@', $raw, 2);

        return [
            $name !== '' ? $name : null,
            $version !== '' ? $version : null,
        ];
    }

    private function normalizeEcosystem(?string $ecosystem): ?string
    {
        if ($ecosystem === null || trim($ecosystem) === '') {
            return null;
        }

        $normalized = strtolower(trim($ecosystem));

        return match ($normalized) {
            'npm', 'javascript', 'node', 'js' => PackageEcosystem::Npm->value,
            'composer', 'php' => PackageEcosystem::Composer->value,
            'nuget', 'dotnet', '.net' => PackageEcosystem::Nuget->value,
            'maven', 'java' => PackageEcosystem::Maven->value,
            'pypi', 'python', 'pip' => PackageEcosystem::Pypi->value,
            'first_party', 'first-party' => PackageEcosystem::FirstParty->value,
            'other' => PackageEcosystem::Other->value,
            default => in_array($normalized, array_column(PackageEcosystem::cases(), 'value'), true)
            ? $normalized
            : null,
        };
    }

    private function ecosystemFromPurl(string $normalizedPurl): ?PackageEcosystem
    {
        return match (true) {
            str_starts_with($normalizedPurl, 'pkg:composer/') => PackageEcosystem::Composer,
            str_starts_with($normalizedPurl, 'pkg:npm/') => PackageEcosystem::Npm,
            str_starts_with($normalizedPurl, 'pkg:nuget/') => PackageEcosystem::Nuget,
            str_starts_with($normalizedPurl, 'pkg:maven/') => PackageEcosystem::Maven,
            str_starts_with($normalizedPurl, 'pkg:pypi/') => PackageEcosystem::Pypi,
            default => null,
        };
    }
}
