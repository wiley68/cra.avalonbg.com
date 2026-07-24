<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class IntegrationHealthExportService
{
    public const FORMATS = ['markdown', 'pdf'];

    public function __construct(
        private readonly IntegrationHealthService $health,
    ) {
    }

    public function export(
        Organization $organization,
        string $format,
        User $actor,
    ): Response {
        $format = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => Translations::get('integrations.health.export.invalid_format'),
            ]);
        }

        $viewPayload = $this->viewPayload($organization);
        $filenameBase = $this->filenameBase($organization);

        AuditLogger::logIntegrationHealthExported(
            $organization,
            $actor,
            $format,
            count($viewPayload['rows']),
        );

        return match ($format) {
            'pdf' => Pdf::loadView('pdf.integration-health', $viewPayload)
                ->setPaper('a4', 'landscape')
                ->stream($filenameBase . '.pdf'),
            'markdown' => response($this->toMarkdown($viewPayload), 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.md"',
            ]),
        };
    }

    /**
     * @return array{
     *     organization: array{name: string, slug: string},
     *     rows: list<array<string, mixed>>,
     *     counts: array{total: int, failed: int, soft_fail: int, never: int, ok: int},
     *     generated_at: string
     * }
     */
    public function viewPayload(Organization $organization): array
    {
        $rows = $this->health->rowsForOrganization($organization)
            ->map(function (array $row): array {
                $health = (string) ($row['health'] ?? 'never');

                return [
                    'provider' => (string) ($row['provider'] ?? ''),
                    'product_name' => (string) ($row['product_name'] ?? ''),
                    'target' => (string) ($row['target'] ?? ''),
                    'connection_status' => (string) ($row['connection_status'] ?? ''),
                    'last_synced_at' => $row['last_synced_at'] ?? null,
                    'health' => $health,
                    'health_label' => $this->healthLabel($health),
                    'last_error' => $row['last_error'] ?? null,
                    'pending_suggestions' => (int) ($row['pending_suggestions'] ?? 0),
                    'source' => (string) ($row['source'] ?? ''),
                ];
            })
            ->sort(function (array $a, array $b): int {
                $rank = [
                    'failed' => 0,
                    'soft_fail' => 1,
                    'never' => 2,
                    'ok' => 3,
                ];
                $cmp = ($rank[$a['health']] ?? 99) <=> ($rank[$b['health']] ?? 99);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcmp($a['product_name'], $b['product_name']);
            })
            ->values()
            ->all();

        $counts = [
            'total' => count($rows),
            'failed' => 0,
            'soft_fail' => 0,
            'never' => 0,
            'ok' => 0,
        ];

        foreach ($rows as $row) {
            $key = $row['health'];
            if (array_key_exists($key, $counts)) {
                $counts[$key]++;
            }
        }

        return [
            'organization' => [
                'name' => $organization->name,
                'slug' => $organization->slug,
            ],
            'rows' => $rows,
            'counts' => $counts,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMarkdown(array $payload): string
    {
        $lines = [];
        $lines[] = '# ' . Translations::get('integrations.health.export.title');
        $lines[] = '';
        $lines[] = '> ' . Translations::get('integrations.health.export.disclaimer');
        $lines[] = '';
        $lines[] = '- **' . Translations::get('integrations.health.export.meta_organization') . ':** '
            . $payload['organization']['name'];
        $lines[] = '- **' . Translations::get('integrations.health.export.generated_at') . ':** '
            . $payload['generated_at'];
        $lines[] = '- **' . Translations::get('integrations.health.export.row_count') . ':** '
            . $payload['counts']['total'];
        $lines[] = '- **' . Translations::get('integrations.health.status.failed') . ':** '
            . $payload['counts']['failed'];
        $lines[] = '- **' . Translations::get('integrations.health.status.soft_fail') . ':** '
            . $payload['counts']['soft_fail'];
        $lines[] = '- **' . Translations::get('integrations.health.status.never') . ':** '
            . $payload['counts']['never'];
        $lines[] = '- **' . Translations::get('integrations.health.status.ok') . ':** '
            . $payload['counts']['ok'];
        $lines[] = '';
        $lines[] = '## ' . Translations::get('integrations.health.export.section_rows');
        $lines[] = '';

        if ($payload['rows'] === []) {
            $lines[] = Translations::get('integrations.health.export.empty');
            $lines[] = '';

            return implode("\n", $lines);
        }

        $lines[] = '| '
            . Translations::get('integrations.health.columns.provider') . ' | '
            . Translations::get('integrations.health.columns.product') . ' | '
            . Translations::get('integrations.health.columns.target') . ' | '
            . Translations::get('integrations.health.columns.connection_status') . ' | '
            . Translations::get('integrations.health.columns.last_synced_at') . ' | '
            . Translations::get('integrations.health.columns.health') . ' | '
            . Translations::get('integrations.health.columns.last_error') . ' | '
            . Translations::get('integrations.health.columns.pending_suggestions')
            . ' |';
        $lines[] = '| --- | --- | --- | --- | --- | --- | --- | --- |';

        foreach ($payload['rows'] as $row) {
            $lines[] = '| '
                . $this->cell($row['provider']) . ' | '
                . $this->cell($row['product_name']) . ' | '
                . $this->cell($row['target']) . ' | '
                . $this->cell($row['connection_status']) . ' | '
                . $this->cell($row['last_synced_at'] ?: '—') . ' | '
                . $this->cell($row['health_label']) . ' | '
                . $this->cell($row['last_error'] ?: '—') . ' | '
                . $this->cell((string) $row['pending_suggestions'])
                . ' |';
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function filenameBase(Organization $organization): string
    {
        $slug = Str::slug($organization->slug !== '' ? $organization->slug : $organization->name);

        return sprintf(
            'integration-health-%s-%s',
            $slug !== '' ? $slug : 'organization',
            now()->format('Y-m-d'),
        );
    }

    private function healthLabel(string $health): string
    {
        $key = "integrations.health.status.{$health}";
        $translated = Translations::get($key);

        return $translated === $key ? $health : $translated;
    }

    private function cell(string $value): string
    {
        return str_replace(['|', "\n", "\r"], ['\\|', ' ', ''], $value);
    }
}
