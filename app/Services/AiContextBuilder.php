<?php

namespace App\Services;

use App\Enums\PolicyStatus;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductRequirement;
use Illuminate\Support\Str;

class AiContextBuilder
{
    /**
     * Build a plain-text workspace summary for grounding the AI assistant.
     * No external API — local DB snapshots only (Must stub).
     */
    public function forProduct(Product $product): string
    {
        $sections = [
            $this->productSection($product),
            $this->requirementsSection($product),
            $this->controlsSection($product),
            $this->policiesSection($product),
        ];

        $text = implode("\n\n", array_filter($sections));
        $max = max(500, (int) config('ai.context_max_chars', 8000));

        if (mb_strlen($text) <= $max) {
            return $text;
        }

        return rtrim(mb_substr($text, 0, $max - 14)) . "\n…[truncated]";
    }

    private function productSection(Product $product): string
    {
        $lines = [
            '## Product',
            'Name: ' . $product->name,
            'Slug: ' . $product->slug,
            'Type: ' . $product->product_type->value,
            'Scope: ' . $product->scope_status->value,
            'Classification: ' . $product->classification_status->value,
        ];

        if (filled($product->manufacturer)) {
            $lines[] = 'Manufacturer: ' . $product->manufacturer;
        }

        if (filled($product->intended_purpose)) {
            $lines[] = 'Intended purpose: ' . $this->truncate((string) $product->intended_purpose, 200);
        }

        return implode("\n", $lines);
    }

    private function requirementsSection(Product $product): string
    {
        $limit = max(1, (int) config('ai.context_requirements_limit', 40));

        $rows = ProductRequirement::query()
            ->where('product_id', $product->id)
            ->with(['requirement:id,code', 'requirementVersion:id,plain_language'])
            ->orderBy('id')
            ->get();

        $total = $rows->count();
        $lines = [
            '## Requirements',
            "Total: {$total}",
        ];

        if ($total === 0) {
            $lines[] = '(none assigned)';

            return implode("\n", $lines);
        }

        foreach ($rows->take($limit) as $row) {
            $code = $row->requirement?->code ?? 'unknown';
            $status = $row->status->value;
            $plain = $this->truncate((string) ($row->requirementVersion?->plain_language ?? ''), 120);
            $lines[] = $plain !== ''
                ? "- {$code} | {$status} | {$plain}"
                : "- {$code} | {$status}";
        }

        if ($total > $limit) {
            $lines[] = '…and ' . ($total - $limit) . ' more';
        }

        return implode("\n", $lines);
    }

    private function controlsSection(Product $product): string
    {
        $limit = max(1, (int) config('ai.context_controls_limit', 40));

        $rows = ProductControl::query()
            ->where('product_id', $product->id)
            ->with(['control:id,code,name'])
            ->orderBy('id')
            ->get();

        $total = $rows->count();
        $lines = [
            '## Controls',
            "Total: {$total}",
        ];

        if ($total === 0) {
            $lines[] = '(none assigned)';

            return implode("\n", $lines);
        }

        foreach ($rows->take($limit) as $row) {
            $code = $row->control?->code ?? 'unknown';
            $name = $row->control?->name ?? '';
            $status = $row->status->value;
            $lines[] = $name !== ''
                ? "- {$code} | {$name} | {$status}"
                : "- {$code} | {$status}";
        }

        if ($total > $limit) {
            $lines[] = '…and ' . ($total - $limit) . ' more';
        }

        return implode("\n", $lines);
    }

    private function policiesSection(Product $product): string
    {
        $policies = OrgPolicy::query()
            ->where('organization_id', $product->organization_id)
            ->where('status', PolicyStatus::Approved)
            ->orderBy('policy_type')
            ->orderByDesc('id')
            ->get(['policy_type', 'title', 'version_label', 'status']);

        $lines = [
            '## Approved policies',
            'Total: ' . $policies->count(),
        ];

        if ($policies->isEmpty()) {
            $lines[] = '(none approved)';

            return implode("\n", $lines);
        }

        foreach ($policies as $policy) {
            $type = $policy->policy_type instanceof \BackedEnum
                ? $policy->policy_type->value
                : (string) $policy->policy_type;
            $lines[] = "- {$type} | {$policy->title} | v{$policy->version_label}";
        }

        return implode("\n", $lines);
    }

    private function truncate(string $value, int $limit): string
    {
        $trimmed = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return Str::limit($trimmed, $limit, '…');
    }
}
