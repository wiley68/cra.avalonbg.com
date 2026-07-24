<?php

namespace App\Contracts;

interface ScannerProvider
{
    /**
     * @return array{id: string, name: string, org_id: string}
     */
    public function getProject(string $orgId, string $projectId): array;

    /**
     * @return list<array{
     *     external_id: string,
     *     title: string,
     *     summary: string|null,
     *     cve_id: string|null,
     *     severity: string|null,
     *     package_name: string|null,
     *     package_ecosystem: string|null,
     *     html_url: string|null,
     *     snyk_issue_key: string|null,
     *     created_at: string|null,
     *     cvss_score: string|null
     * }>
     */
    public function listFindings(string $orgId, string $projectId, int $limit = 50): array;
}
