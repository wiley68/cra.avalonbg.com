<?php

namespace App\Contracts;

interface VcsProvider
{
    /**
     * @return list<array{name: string, commit_sha: string|null}>
     */
    public function listTags(string $fullName): array;

    /**
     * @return list<array{tag_name: string, name: string|null, published_at: string|null, html_url: string|null}>
     */
    public function listReleases(string $fullName): array;

    /**
     * @return array{status: string, conclusion: string|null, workflow_name: string|null, html_url: string|null, head_sha: string|null}
     */
    public function defaultBranchCiStatus(string $fullName, string $defaultBranch): array;
}
