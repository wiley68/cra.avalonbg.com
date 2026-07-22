<?php

namespace App\Services;

use App\Contracts\EmbeddingProvider;
use App\Enums\AiEmbeddingSourceType;
use App\Enums\PolicyStatus;
use App\Models\AiEmbeddingChunk;
use App\Models\Evidence;
use App\Models\OrgPolicy;
use App\Models\Product;
use App\Models\ProductControl;
use App\Models\ProductRequirement;
use App\Services\Ai\AiTextChunker;
use Illuminate\Support\Facades\DB;

class AiEmbeddingIndexer
{
    public function __construct(
        private readonly EmbeddingProvider $embeddings,
    ) {
    }

    /**
     * @return array{chunks: int, sources: int}
     */
    public function indexProduct(Product $product): array
    {
        $sources = 0;
        $chunks = 0;

        DB::transaction(function () use ($product, &$sources, &$chunks): void {
            $chunks += $this->indexRequirements($product, $sources);
            $chunks += $this->indexControls($product, $sources);
            $chunks += $this->indexEvidence($product, $sources);
            $chunks += $this->indexPolicies($product, $sources);
        });

        return [
            'chunks' => $chunks,
            'sources' => $sources,
        ];
    }

    private function indexRequirements(Product $product, int &$sources): int
    {
        $rows = ProductRequirement::query()
            ->where('product_id', $product->id)
            ->with([
                'requirement:id,code',
                'requirementVersion:id,requirement_text,plain_language',
            ])
            ->orderBy('id')
            ->get();

        $totalChunks = 0;

        foreach ($rows as $row) {
            $code = (string) ($row->requirement?->code ?? 'unknown');
            $plain = trim((string) ($row->requirementVersion?->plain_language ?? ''));
            $legal = trim((string) ($row->requirementVersion?->requirement_text ?? ''));
            $rationale = trim((string) ($row->rationale ?? ''));

            $body = trim(implode("\n\n", array_filter([
                "Requirement {$code} (status: {$row->status->value})",
                $plain !== '' ? "Plain language:\n{$plain}" : null,
                $legal !== '' ? "Requirement text:\n{$legal}" : null,
                $rationale !== '' ? "Rationale:\n{$rationale}" : null,
            ])));

            if ($body === '') {
                continue;
            }

            $sources++;
            $totalChunks += $this->upsertChunks(
                organizationId: $product->organization_id,
                productId: $product->id,
                sourceType: AiEmbeddingSourceType::RequirementVersion,
                sourceId: $row->id,
                text: $body,
                metadata: [
                    'requirement_code' => $code,
                    'requirement_version_id' => $row->requirement_version_id,
                    'product_requirement_id' => $row->id,
                ],
            );
        }

        return $totalChunks;
    }

    private function indexControls(Product $product, int &$sources): int
    {
        $rows = ProductControl::query()
            ->where('product_id', $product->id)
            ->with(['control:id,code,name,description,implementation_guidance'])
            ->orderBy('id')
            ->get();

        $totalChunks = 0;

        foreach ($rows as $row) {
            $control = $row->control;
            if ($control === null) {
                continue;
            }

            $body = trim(implode("\n\n", array_filter([
                "Control {$control->code}: {$control->name} (status: {$row->status->value})",
                filled($control->description) ? (string) $control->description : null,
                filled($control->implementation_guidance) ? 'Guidance: ' . $control->implementation_guidance : null,
            ])));

            if ($body === '') {
                continue;
            }

            $sources++;
            $totalChunks += $this->upsertChunks(
                organizationId: $product->organization_id,
                productId: $product->id,
                sourceType: AiEmbeddingSourceType::Control,
                sourceId: $row->id,
                text: $body,
                metadata: [
                    'control_code' => $control->code,
                    'control_id' => $control->id,
                    'product_control_id' => $row->id,
                ],
            );
        }

        return $totalChunks;
    }

    private function indexEvidence(Product $product, int &$sources): int
    {
        $rows = Evidence::query()
            ->where('product_id', $product->id)
            ->orderBy('id')
            ->get(['id', 'type', 'title', 'notes', 'source', 'freshness_status']);

        $totalChunks = 0;

        foreach ($rows as $row) {
            $body = trim(implode("\n\n", array_filter([
                "Evidence: {$row->title}",
                'Type: ' . $row->type->value,
                'Freshness: ' . $row->freshness_status->value,
                filled($row->source) ? 'Source: ' . $row->source : null,
                filled($row->notes) ? 'Notes: ' . $row->notes : null,
            ])));

            if ($body === '') {
                continue;
            }

            $sources++;
            $totalChunks += $this->upsertChunks(
                organizationId: $product->organization_id,
                productId: $product->id,
                sourceType: AiEmbeddingSourceType::Evidence,
                sourceId: $row->id,
                text: $body,
                metadata: [
                    'evidence_type' => $row->type->value,
                    'title' => $row->title,
                ],
            );
        }

        return $totalChunks;
    }

    private function indexPolicies(Product $product, int &$sources): int
    {
        $policies = OrgPolicy::query()
            ->where('organization_id', $product->organization_id)
            ->where('status', PolicyStatus::Approved)
            ->orderBy('id')
            ->get();

        $totalChunks = 0;

        foreach ($policies as $policy) {
            $type = $policy->policy_type->value;
            $header = "Approved policy ({$type}): {$policy->title} v{$policy->version_label}";
            $body = trim($header . "\n\n" . trim((string) $policy->body));

            if ($body === $header) {
                continue;
            }

            $sources++;
            // Org-scoped: product_id null so all products in org can retrieve.
            $totalChunks += $this->upsertChunks(
                organizationId: $product->organization_id,
                productId: null,
                sourceType: AiEmbeddingSourceType::OrgPolicy,
                sourceId: $policy->id,
                text: $body,
                metadata: [
                    'policy_type' => $type,
                    'title' => $policy->title,
                    'version_label' => $policy->version_label,
                ],
            );
        }

        return $totalChunks;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function upsertChunks(
        int $organizationId,
        ?int $productId,
        AiEmbeddingSourceType $sourceType,
        int $sourceId,
        string $text,
        array $metadata,
    ): int {
        $parts = AiTextChunker::chunk($text);
        if ($parts === []) {
            return 0;
        }

        $keptIndexes = [];

        foreach ($parts as $index => $part) {
            $hash = hash('sha256', $part);
            $existing = AiEmbeddingChunk::query()
                ->where('source_type', $sourceType)
                ->where('source_id', $sourceId)
                ->where('chunk_index', $index)
                ->first();

            if (
                $existing !== null
                && $existing->content_hash === $hash
                && $existing->embedding_model === $this->embeddings->model()
            ) {
                $keptIndexes[] = $index;

                continue;
            }

            $vector = $this->embeddings->embed($part);

            AiEmbeddingChunk::query()->updateOrCreate(
                [
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'chunk_index' => $index,
                ],
                [
                    'organization_id' => $organizationId,
                    'product_id' => $productId,
                    'content' => $part,
                    'embedding' => $vector,
                    'embedding_model' => $this->embeddings->model(),
                    'dimensions' => count($vector),
                    'content_hash' => $hash,
                    'metadata' => $metadata,
                ],
            );

            $keptIndexes[] = $index;
        }

        AiEmbeddingChunk::query()
            ->where('source_type', $sourceType)
            ->where('source_id', $sourceId)
            ->whereNotIn('chunk_index', $keptIndexes)
            ->delete();

        return count($keptIndexes);
    }
}
