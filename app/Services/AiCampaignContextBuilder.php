<?php

namespace App\Services;

use App\Enums\PatchCampaignTargetStatus;
use App\Models\PatchCampaign;

class AiCampaignContextBuilder
{
    public function forCampaign(PatchCampaign $campaign): string
    {
        $campaign->loadMissing([
            'product:id,name,slug',
            'targetVersion:id,version_number',
            'productVulnerability:id,title',
            'targets',
        ]);

        $targets = $campaign->targets;
        $total = $targets->count();
        $pending = $targets->where('status', PatchCampaignTargetStatus::Pending)->count();
        $notified = $targets->where('status', PatchCampaignTargetStatus::Notified)->count();
        $updated = $targets->where('status', PatchCampaignTargetStatus::Updated)->count();

        $environments = $targets
            ->pluck('environment')
            ->filter()
            ->unique()
            ->take(8)
            ->values()
            ->all();

        $lines = [
            '## Patch campaign',
            'Title: ' . $campaign->title,
            'Status: ' . $campaign->status->value,
            'Product: ' . ($campaign->product?->name ?? 'unknown'),
            'Target version: ' . ($campaign->targetVersion?->version_number ?? 'unknown'),
        ];

        if ($campaign->productVulnerability !== null) {
            $lines[] = 'Linked vulnerability: ' . $campaign->productVulnerability->title;
        }

        if (filled($campaign->notes)) {
            $lines[] = 'Campaign notes: ' . trim((string) $campaign->notes);
        }

        $lines[] = "Targets: {$total} total, {$pending} pending, {$notified} notified, {$updated} updated";

        if ($environments !== []) {
            $lines[] = 'Environments: ' . implode(', ', array_map('strval', $environments));
        }

        $lines[] = '';
        $lines[] = '## Customer notification email skeleton (reference only)';
        $lines[] = 'Subject pattern: [{product}] Security update available — {campaign.title}';
        $lines[] = 'Body slots: greeting, product name, campaign title, environment, current version, target version, optional campaign notes, ask to apply/confirm.';

        return implode("\n", $lines);
    }
}
