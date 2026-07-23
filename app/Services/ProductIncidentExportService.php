<?php

namespace App\Services;

use App\Models\Organization;
use App\Models\Product;
use App\Models\ProductIncident;
use App\Models\User;
use App\Support\AuditLogger;
use App\Support\Translations;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProductIncidentExportService
{
    public const FORMATS = ['markdown', 'pdf'];

    public function export(
        ProductIncident $incident,
        Product $product,
        Organization $organization,
        string $format,
        User $actor,
    ): Response {
        $format = strtolower($format);

        if (!in_array($format, self::FORMATS, true)) {
            throw ValidationException::withMessages([
                'format' => Translations::get('products.incidents.export.invalid_format'),
            ]);
        }

        $incident->loadMissing([
            'owner:id,name',
            'closer:id,name',
            'versions:id,version_number',
            'customers:id,name',
            'deployments.customer:id,name',
            'deployments.productVersion:id,version_number',
            'timelineEvents.creator:id,name',
            'vulnerability:id,title,cve_id,status',
        ]);

        $viewPayload = $this->viewPayload($incident, $product, $organization);
        $filenameBase = $this->filenameBase($incident, $product);

        AuditLogger::logIncidentExported($incident, $actor, $format);

        return match ($format) {
            'pdf' => Pdf::loadView('pdf.product-incident', $viewPayload)
                ->setPaper('a4')
                ->stream($filenameBase . '.pdf'),
            'markdown' => response($this->toMarkdown($viewPayload), 200, [
                'Content-Type' => 'text/markdown; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="' . $filenameBase . '.md"',
            ]),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function viewPayload(
        ProductIncident $incident,
        Product $product,
        Organization $organization,
    ): array {
        $incident->loadMissing([
            'owner:id,name',
            'closer:id,name',
            'versions:id,version_number',
            'customers:id,name',
            'deployments.customer:id,name',
            'deployments.productVersion:id,version_number',
            'timelineEvents.creator:id,name',
            'vulnerability:id,title,cve_id,status',
        ]);

        return [
            'organization' => [
                'name' => $organization->name,
            ],
            'product' => [
                'name' => $product->name,
                'slug' => $product->slug,
            ],
            'incident' => [
                'id' => $incident->id,
                'title' => $incident->title,
                'status' => $incident->status->value,
                'status_label' => $this->statusLabel($incident->status->value),
                'severity' => $incident->severity->value,
                'severity_label' => $this->severityLabel($incident->severity->value),
                'confidentiality_impact' => $incident->confidentiality_impact?->value,
                'confidentiality_impact_label' => $this->ciaImpactLabel(
                    $incident->confidentiality_impact?->value,
                ),
                'integrity_impact' => $incident->integrity_impact?->value,
                'integrity_impact_label' => $this->ciaImpactLabel(
                    $incident->integrity_impact?->value,
                ),
                'availability_impact' => $incident->availability_impact?->value,
                'availability_impact_label' => $this->ciaImpactLabel(
                    $incident->availability_impact?->value,
                ),
                'attack_vector' => $incident->attack_vector?->value,
                'attack_vector_label' => $this->attackVectorLabel(
                    $incident->attack_vector?->value,
                ),
                'summary' => $incident->summary,
                'root_cause' => $incident->root_cause,
                'corrective_measures' => $incident->corrective_measures,
                'lessons_learned' => $incident->lessons_learned,
                'notes' => $incident->notes,
                'owner_name' => $incident->owner?->name,
                'closed_by_name' => $incident->closer?->name,
                'actual_started_at' => $incident->actual_started_at?->toIso8601String(),
                'detected_at' => $incident->detected_at?->toIso8601String(),
                'awareness_at' => $incident->awareness_at?->toIso8601String(),
                'classified_at' => $incident->classified_at?->toIso8601String(),
                'closed_at' => $incident->closed_at?->toIso8601String(),
                'versions' => $incident->versions
                    ->pluck('version_number')
                    ->filter()
                    ->values()
                    ->all(),
                'customers' => $incident->customers
                    ->pluck('name')
                    ->filter()
                    ->values()
                    ->all(),
                'deployments' => $incident->deployments
                    ->map(function ($deployment): string {
                        $customer = $deployment->customer?->name ?? ('#' . $deployment->customer_id);
                        $environment = $deployment->environment->value;
                        $version = $deployment->productVersion?->version_number ?? '—';

                        return "{$customer} — {$environment} ({$version})";
                    })
                    ->values()
                    ->all(),
                'linked_vulnerability' => $incident->vulnerability
                    ? [
                        'id' => $incident->vulnerability->id,
                        'title' => $incident->vulnerability->title,
                        'cve_id' => $incident->vulnerability->cve_id,
                        'status' => $incident->vulnerability->status->value,
                    ]
                    : null,
                'timeline_events' => $incident->timelineEvents
                    ->map(fn($event) => [
                        'occurred_at' => $event->occurred_at->toIso8601String(),
                        'label' => $event->label,
                        'notes' => $event->notes,
                        'created_by' => $event->creator?->name,
                    ])
                    ->values()
                    ->all(),
            ],
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function toMarkdown(array $payload): string
    {
        /** @var array<string, mixed> $incident */
        $incident = $payload['incident'];
        $lines = [];

        $lines[] = '# ' . $incident['title'];
        $lines[] = '';
        $lines[] = '> ' . Translations::get('products.incidents.export.disclaimer');
        $lines[] = '';
        $lines[] = '- **' . Translations::get('products.incidents.export.meta_organization') . ':** '
            . $payload['organization']['name'];
        $lines[] = '- **' . Translations::get('products.incidents.export.meta_product') . ':** '
            . $payload['product']['name'];
        $lines[] = '- **' . Translations::get('products.incidents.fields.status') . ':** '
            . $incident['status_label'];
        $lines[] = '- **' . Translations::get('products.incidents.fields.severity') . ':** '
            . $incident['severity_label'];

        foreach ([
            'confidentiality_impact_label' => 'products.incidents.fields.confidentiality_impact',
            'integrity_impact_label' => 'products.incidents.fields.integrity_impact',
            'availability_impact_label' => 'products.incidents.fields.availability_impact',
            'attack_vector_label' => 'products.incidents.fields.attack_vector',
        ] as $field => $labelKey) {
            if (!empty($incident[$field])) {
                $lines[] = '- **' . Translations::get($labelKey) . ':** ' . $incident[$field];
            }
        }

        if ($incident['owner_name']) {
            $lines[] = '- **' . Translations::get('products.incidents.fields.owner') . ':** '
                . $incident['owner_name'];
        }

        $lines[] = '- **' . Translations::get('products.incidents.export.generated_at') . ':** '
            . $payload['generated_at'];

        foreach ([
            'actual_started_at' => 'products.incidents.fields.actual_started_at',
            'detected_at' => 'products.incidents.fields.detected_at',
            'awareness_at' => 'products.incidents.fields.awareness_at',
            'classified_at' => 'products.incidents.fields.classified_at',
            'closed_at' => 'products.incidents.fields.closed_at',
        ] as $field => $labelKey) {
            if (!empty($incident[$field])) {
                $lines[] = '- **' . Translations::get($labelKey) . ':** ' . $incident[$field];
            }
        }

        if ($incident['closed_by_name']) {
            $lines[] = '- **' . Translations::get('products.incidents.export.closed_by') . ':** '
                . $incident['closed_by_name'];
        }

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.fields.summary');
        $lines[] = '';
        $lines[] = $this->blockOrEmpty($incident['summary']);

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.investigation_title');
        $lines[] = '';
        $lines[] = '### ' . Translations::get('products.incidents.fields.root_cause');
        $lines[] = '';
        $lines[] = $this->blockOrEmpty($incident['root_cause']);
        $lines[] = '';
        $lines[] = '### ' . Translations::get('products.incidents.fields.corrective_measures');
        $lines[] = '';
        $lines[] = $this->blockOrEmpty($incident['corrective_measures']);
        $lines[] = '';
        $lines[] = '### ' . Translations::get('products.incidents.fields.lessons_learned');
        $lines[] = '';
        $lines[] = $this->blockOrEmpty($incident['lessons_learned']);

        if (filled($incident['notes'])) {
            $lines[] = '';
            $lines[] = '## ' . Translations::get('products.incidents.fields.notes');
            $lines[] = '';
            $lines[] = $incident['notes'];
        }

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.fields.versions');
        $lines[] = '';
        $lines = [...$lines, ...$this->bulletList($incident['versions'])];

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.fields.customers');
        $lines[] = '';
        $lines = [...$lines, ...$this->bulletList($incident['customers'])];

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.fields.deployments');
        $lines[] = '';
        $lines = [...$lines, ...$this->bulletList($incident['deployments'])];

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.vulnerability_title');
        $lines[] = '';

        if ($incident['linked_vulnerability'] === null) {
            $lines[] = Translations::get('products.incidents.export.empty');
        } else {
            $vuln = $incident['linked_vulnerability'];
            $label = $vuln['title'];
            if (filled($vuln['cve_id'])) {
                $label .= ' (' . $vuln['cve_id'] . ')';
            }
            $lines[] = '- ' . $label . ' — ' . $vuln['status'];
        }

        $lines[] = '';
        $lines[] = '## ' . Translations::get('products.incidents.timeline_title');
        $lines[] = '';

        if ($incident['timeline_events'] === []) {
            $lines[] = Translations::get('products.incidents.export.empty');
        } else {
            foreach ($incident['timeline_events'] as $event) {
                $entry = '- **' . $event['occurred_at'] . '** — ' . $event['label'];
                if ($event['created_by']) {
                    $entry .= ' (' . $event['created_by'] . ')';
                }
                $lines[] = $entry;
                if (filled($event['notes'])) {
                    $lines[] = '  - ' . Str::of((string) $event['notes'])->replace("\n", ' ')->trim();
                }
            }
        }

        $lines[] = '';

        return implode("\n", $lines);
    }

    private function filenameBase(ProductIncident $incident, Product $product): string
    {
        $slug = Str::slug($product->slug !== '' ? $product->slug : $product->name);

        return sprintf(
            'incident-%s-%d-%s',
            $slug !== '' ? $slug : 'product',
            $incident->id,
            now()->format('Y-m-d'),
        );
    }

    private function statusLabel(string $value): string
    {
        $key = "products.incidents.statuses.{$value}";
        $translated = Translations::get($key);

        return $translated === $key ? $value : $translated;
    }

    private function severityLabel(string $value): string
    {
        $key = "products.incidents.severities.{$value}";
        $translated = Translations::get($key);

        return $translated === $key ? $value : $translated;
    }

    private function ciaImpactLabel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = "products.incidents.cia_impacts.{$value}";
        $translated = Translations::get($key);

        return $translated === $key ? $value : $translated;
    }

    private function attackVectorLabel(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $key = "products.incidents.attack_vectors.{$value}";
        $translated = Translations::get($key);

        return $translated === $key ? $value : $translated;
    }

    private function blockOrEmpty(?string $value): string
    {
        return filled($value)
            ? $value
            : Translations::get('products.incidents.export.empty');
    }

    /**
     * @param  list<string>  $items
     * @return list<string>
     */
    private function bulletList(array $items): array
    {
        if ($items === []) {
            return [Translations::get('products.incidents.export.empty')];
        }

        return array_map(fn(string $item): string => '- ' . $item, $items);
    }
}
