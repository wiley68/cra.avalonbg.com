<?php

namespace App\Models;

use App\Enums\BankPaymentRequestStatus;
use App\Enums\BillingInterval;
use App\Enums\SubscriptionPlan;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'subscription_plan',
    'billing_interval',
    'amount_eur',
    'currency',
    'payment_reference',
    'status',
    'requested_by',
    'activated_by',
    'activated_at',
    'notes',
])]
class OrganizationBankPaymentRequest extends Model
{
    protected function casts(): array
    {
        return [
            'amount_eur' => 'decimal:2',
            'status' => BankPaymentRequestStatus::class,
            'billing_interval' => BillingInterval::class,
            'activated_at' => 'datetime',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function activator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function resolvedPlan(): SubscriptionPlan
    {
        return SubscriptionPlan::fromStoredOrDefault($this->subscription_plan);
    }

    public function isPending(): bool
    {
        return $this->status === BankPaymentRequestStatus::Pending;
    }
}
