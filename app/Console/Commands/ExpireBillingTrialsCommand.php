<?php

namespace App\Console\Commands;

use App\Enums\BillingStatus;
use App\Models\Organization;
use Illuminate\Console\Command;

class ExpireBillingTrialsCommand extends Command
{
    protected $signature = 'billing:expire-trials';

    protected $description = 'Move unpaid expired trials to pending_payment (keeps org data)';

    public function handle(): int
    {
        $expired = Organization::query()
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', now())
            ->where('billing_status', BillingStatus::Active->value)
            ->whereNull('billing_activated_at')
            ->get();

        $count = 0;

        foreach ($expired as $organization) {
            if ($organization->syncExpiredTrial()) {
                $count++;
            }
        }

        $this->info("Expired {$count} trial(s).");

        return self::SUCCESS;
    }
}
