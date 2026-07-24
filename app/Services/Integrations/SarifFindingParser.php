<?php

namespace App\Services\Integrations;

use App\Support\Translations;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

class SarifFindingParser
{
    /**
     * @return array{
     *     findings: list<array{
     *         external_id: string,
     *         title: string,
     *         summary: string|null,
     *         cve_id: string|null,
     *         severity: string|null,
     *         package_name: string|null,
     *         package_ecosystem: string|null,
     *         package_purl: string|null,
     *         html_url: string|null,
     *         snyk_issue_key: string|null,
     *         created_at: string|null,
     *         cvss_score: string|null
     *     }>,
     *     tool_name: string|null,
     *     runs_count: int
     * }
     */
    public function parse(UploadedFile|string $source): array
    {
        $raw = $source instanceof UploadedFile
            ? (string) file_get_contents($source->getRealPath() ?: '')
            : $source;

        if (trim($raw) === '') {
            throw ValidationException::withMessages([
                'file' => [Translations::get('products.integrations.sarif.invalid_empty')],
            ]);
        }

        try {
            /** @var mixed $decoded */
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('products.integrations.sarif.invalid_json')],
            ]);
        }

        if (!is_array($decoded) || !is_array($decoded['runs'] ?? null)) {
            throw ValidationException::withMessages([
                'file' => [Translations::get('products.integrations.sarif.invalid_schema')],
            ]);
        }

        $findings = [];
        $toolName = null;
        $runs = $decoded['runs'];

        foreach ($runs as $runIndex => $run) {
            if (!is_array($run)) {
                continue;
            }

            $driver = is_array($run['tool']['driver'] ?? null) ? $run['tool']['driver'] : [];
            $runTool = isset($driver['name']) && is_string($driver['name']) ? $driver['name'] : null;
            if ($toolName === null && $runTool !== null) {
                $toolName = $runTool;
            }

            $rulesById = $this->indexRules(is_array($driver['rules'] ?? null) ? $driver['rules'] : []);
            $results = is_array($run['results'] ?? null) ? $run['results'] : [];

            foreach ($results as $resultIndex => $result) {
                if (!is_array($result)) {
                    continue;
                }

                $finding = $this->mapResult(
                    result: $result,
                    rulesById: $rulesById,
                    runIndex: (int) $runIndex,
                    resultIndex: (int) $resultIndex,
                    toolName: $runTool ?? $toolName,
                );

                if ($finding !== null) {
                    $findings[] = $finding;
                }
            }
        }

        return [
            'findings' => $findings,
            'tool_name' => $toolName,
            'runs_count' => count($runs),
        ];
    }

    /**
     * Soft-parse for upload soft-fail path: returns empty findings + error message instead of throwing.
     *
     * @return array{
     *     findings: list<array<string, mixed>>,
     *     tool_name: string|null,
     *     runs_count: int,
     *     soft_fail: bool,
     *     last_error: string|null
     * }
     */
    public function tryParse(UploadedFile|string $source): array
    {
        try {
            $parsed = $this->parse($source);

            return [
                ...$parsed,
                'soft_fail' => false,
                'last_error' => null,
            ];
        } catch (ValidationException $exception) {
            $messages = $exception->errors();
            $first = collect($messages)->flatten()->first();

            return [
                'findings' => [],
                'tool_name' => null,
                'runs_count' => 0,
                'soft_fail' => true,
                'last_error' => is_string($first) ? $first : Translations::get('products.integrations.sarif.invalid_schema'),
            ];
        }
    }

    /**
     * @param  list<mixed>  $rules
     * @return array<string, array<string, mixed>>
     */
    private function indexRules(array $rules): array
    {
        $indexed = [];

        foreach ($rules as $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $id = isset($rule['id']) && is_string($rule['id']) ? $rule['id'] : null;
            if ($id === null || $id === '') {
                continue;
            }

            $indexed[$id] = $rule;
        }

        return $indexed;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, array<string, mixed>>  $rulesById
     * @return array{
     *     external_id: string,
     *     title: string,
     *     summary: string|null,
     *     cve_id: string|null,
     *     severity: string|null,
     *     package_name: string|null,
     *     package_ecosystem: string|null,
     *     package_purl: string|null,
     *     html_url: string|null,
     *     snyk_issue_key: string|null,
     *     created_at: string|null,
     *     cvss_score: string|null
     * }|null
     */
    private function mapResult(
        array $result,
        array $rulesById,
        int $runIndex,
        int $resultIndex,
        ?string $toolName,
    ): ?array {
        $ruleId = isset($result['ruleId']) && is_string($result['ruleId']) ? $result['ruleId'] : null;
        $rule = $ruleId !== null && isset($rulesById[$ruleId]) ? $rulesById[$ruleId] : [];

        $message = is_array($result['message'] ?? null) ? $result['message'] : [];
        $messageText = isset($message['text']) && is_string($message['text']) ? trim($message['text']) : '';
        $ruleName = isset($rule['name']) && is_string($rule['name']) ? $rule['name'] : null;
        $ruleShort = is_array($rule['shortDescription'] ?? null) ? $rule['shortDescription'] : [];
        $ruleFull = is_array($rule['fullDescription'] ?? null) ? $rule['fullDescription'] : [];
        $shortText = isset($ruleShort['text']) && is_string($ruleShort['text']) ? $ruleShort['text'] : null;
        $fullText = isset($ruleFull['text']) && is_string($ruleFull['text']) ? $ruleFull['text'] : null;

        $title = $ruleName ?: ($shortText ?: ($messageText !== '' ? $messageText : ($ruleId ?: 'SARIF finding')));
        $summary = $fullText ?: ($messageText !== '' ? $messageText : $shortText);

        $locationUri = $this->primaryLocationUri($result);
        $locationLine = $this->primaryLocationLine($result);
        $externalId = $this->externalId($result, $ruleId, $locationUri, $locationLine, $runIndex, $resultIndex);

        $cveId = $this->extractCve(implode("\n", array_filter([
            $ruleId,
            $title,
            $summary,
            json_encode($result['properties'] ?? null) ?: null,
            json_encode($rule['properties'] ?? null) ?: null,
        ])));

        $severity = $this->mapSeverity($result, $rule);
        $package = $this->extractPackage($result, $rule);
        $helpUri = isset($rule['helpUri']) && is_string($rule['helpUri']) ? $rule['helpUri'] : null;

        return [
            'external_id' => $externalId,
            'title' => mb_substr($title, 0, 255),
            'summary' => $summary,
            'cve_id' => $cveId,
            'severity' => $severity,
            'package_name' => $package['name'],
            'package_ecosystem' => $package['ecosystem'],
            'package_purl' => $package['purl'],
            'html_url' => $helpUri,
            'snyk_issue_key' => null,
            'created_at' => null,
            'cvss_score' => $this->extractCvss($result, $rule),
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function externalId(
        array $result,
        ?string $ruleId,
        ?string $locationUri,
        ?int $locationLine,
        int $runIndex,
        int $resultIndex,
    ): string {
        $fingerprints = is_array($result['fingerprints'] ?? null) ? $result['fingerprints'] : [];
        $partial = is_array($result['partialFingerprints'] ?? null) ? $result['partialFingerprints'] : [];

        foreach ([$fingerprints, $partial] as $bag) {
            foreach ($bag as $value) {
                if (is_string($value) && $value !== '') {
                    return 'sarif:' . substr(hash('sha256', $value), 0, 32);
                }
            }
        }

        $guid = isset($result['guid']) && is_string($result['guid']) ? $result['guid'] : null;
        if ($guid !== null && $guid !== '') {
            return 'sarif:' . $guid;
        }

        $basis = implode('|', [
            (string) $ruleId,
            (string) $locationUri,
            $locationLine !== null ? (string) $locationLine : '',
            (string) $runIndex,
            (string) $resultIndex,
        ]);

        return 'sarif:' . substr(hash('sha256', $basis), 0, 32);
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function primaryLocationUri(array $result): ?string
    {
        $locations = is_array($result['locations'] ?? null) ? $result['locations'] : [];
        $first = is_array($locations[0] ?? null) ? $locations[0] : [];
        $physical = is_array($first['physicalLocation'] ?? null) ? $first['physicalLocation'] : [];
        $artifact = is_array($physical['artifactLocation'] ?? null) ? $physical['artifactLocation'] : [];

        return isset($artifact['uri']) && is_string($artifact['uri']) ? $artifact['uri'] : null;
    }

    /**
     * @param  array<string, mixed>  $result
     */
    private function primaryLocationLine(array $result): ?int
    {
        $locations = is_array($result['locations'] ?? null) ? $result['locations'] : [];
        $first = is_array($locations[0] ?? null) ? $locations[0] : [];
        $physical = is_array($first['physicalLocation'] ?? null) ? $first['physicalLocation'] : [];
        $region = is_array($physical['region'] ?? null) ? $physical['region'] : [];

        return isset($region['startLine']) ? (int) $region['startLine'] : null;
    }

    private function extractCve(string $haystack): ?string
    {
        if (preg_match('/CVE-\d{4}-\d{4,}/i', $haystack, $matches) === 1) {
            return strtoupper($matches[0]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $rule
     */
    private function mapSeverity(array $result, array $rule): ?string
    {
        $properties = is_array($result['properties'] ?? null) ? $result['properties'] : [];
        $ruleProperties = is_array($rule['properties'] ?? null) ? $rule['properties'] : [];

        $securitySeverity = $properties['security-severity']
            ?? $properties['securitySeverity']
            ?? $ruleProperties['security-severity']
            ?? $ruleProperties['securitySeverity']
            ?? null;

        if (is_numeric($securitySeverity)) {
            $score = (float) $securitySeverity;

            return match (true) {
                $score >= 9.0 => 'critical',
                $score >= 7.0 => 'high',
                $score >= 4.0 => 'medium',
                default => 'low',
            };
        }

        $level = isset($result['level']) && is_string($result['level'])
            ? strtolower($result['level'])
            : (isset($rule['defaultConfiguration']['level']) && is_string($rule['defaultConfiguration']['level'])
                ? strtolower($rule['defaultConfiguration']['level'])
                : null);

        return match ($level) {
            'error' => 'high',
            'warning' => 'medium',
            'note', 'none' => 'low',
            default => $level,
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $rule
     * @return array{name: string|null, ecosystem: string|null, purl: string|null}
     */
    private function extractPackage(array $result, array $rule): array
    {
        $bags = [
            is_array($result['properties'] ?? null) ? $result['properties'] : [],
            is_array($rule['properties'] ?? null) ? $rule['properties'] : [],
        ];

        $purl = null;
        $name = null;
        $ecosystem = null;

        foreach ($bags as $bag) {
            foreach (['purl', 'packagePurl', 'package_url'] as $key) {
                if (isset($bag[$key]) && is_string($bag[$key]) && $bag[$key] !== '') {
                    $purl = $bag[$key];
                    break 2;
                }
            }
        }

        foreach ($bags as $bag) {
            foreach (['pkgName', 'packageName', 'package_name', 'package'] as $key) {
                if (isset($bag[$key]) && is_string($bag[$key]) && $bag[$key] !== '') {
                    $name = $bag[$key];
                    break 2;
                }
            }
        }

        if ($purl !== null && preg_match('#^pkg:([^/]+)/([^@?]+)#', $purl, $matches) === 1) {
            $ecosystem = $matches[1];
            if ($name === null) {
                $name = urldecode($matches[2]);
            }
        }

        return [
            'name' => $name,
            'ecosystem' => $ecosystem,
            'purl' => $purl,
        ];
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $rule
     */
    private function extractCvss(array $result, array $rule): ?string
    {
        $bags = [
            is_array($result['properties'] ?? null) ? $result['properties'] : [],
            is_array($rule['properties'] ?? null) ? $rule['properties'] : [],
        ];

        foreach ($bags as $bag) {
            foreach (['security-severity', 'securitySeverity', 'cvssScore', 'cvss'] as $key) {
                if (isset($bag[$key]) && is_numeric($bag[$key])) {
                    return (string) $bag[$key];
                }
            }
        }

        return null;
    }
}
