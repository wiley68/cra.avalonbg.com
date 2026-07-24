<?php

namespace App\Contracts;

interface AlmProvider
{
    /**
     * @return array{key: string, name: string, id: string|null}
     */
    public function getProject(string $projectKey): array;

    /**
     * @return list<array{
     *     external_id: string,
     *     key: string,
     *     title: string,
     *     summary: string|null,
     *     issue_type: string|null,
     *     priority: string|null,
     *     status: string|null,
     *     html_url: string,
     *     updated_at: string|null
     * }>
     */
    public function listIssues(string $projectKey, int $maxResults = 50): array;
}
