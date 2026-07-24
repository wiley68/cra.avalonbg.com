<?php

namespace App\Console\Commands;

use App\Services\EvidenceService;
use Illuminate\Console\Command;

class RefreshEvidenceFreshnessCommand extends Command
{
    protected $signature = 'evidence:refresh-freshness
                            {--organization= : Limit to organization ID}
                            {--dry-run : Count rows that would change without writing}';

    protected $description = 'Auto-mark evidence as review_due or expired when freshness dates elapse';

    public function handle(EvidenceService $evidence): int
    {
        $organizationOption = $this->option('organization');
        $organizationId = ($organizationOption !== null && $organizationOption !== '')
            ? (int) $organizationOption
            : null;
        $dryRun = (bool) $this->option('dry-run');

        $result = $evidence->refreshDerivedFreshnessStatuses(
            organizationId: $organizationId,
            dryRun: $dryRun,
        );

        $prefix = $dryRun ? '[dry-run] ' : '';

        $this->info(sprintf(
            '%sScanned %d evidence row(s); %d freshness status(es) %s.',
            $prefix,
            $result['scanned'],
            $result['updated'],
            $dryRun ? 'would update' : 'updated',
        ));

        return self::SUCCESS;
    }
}
