<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Free = 'free';
    case Small = 'small';
    case Standard = 'standard';
    case Enterprise = 'enterprise';

    /**
     * Resolve a stored / alias / legacy value to a canonical plan.
     */
    public static function tryFromStored(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = strtolower(trim($value));

        $fromEnum = self::tryFrom($normalized);
        if ($fromEnum !== null) {
            return $fromEnum;
        }

        /** @var array<string, string> $aliases */
        $aliases = config('billing.aliases', []);
        $canonical = $aliases[$normalized] ?? null;

        return is_string($canonical) ? self::tryFrom($canonical) : null;
    }

    public static function fromStoredOrFallback(?string $value): self
    {
        $resolved = self::tryFromStored($value);
        if ($resolved !== null) {
            return $resolved;
        }

        $fallback = (string) config('billing.null_plan_fallback', self::Enterprise->value);

        return self::tryFrom($fallback) ?? self::Enterprise;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function maxProducts(): ?int
    {
        $max = config('billing.plans.' . $this->value . '.max_products');

        return $max === null ? null : (int) $max;
    }

    public function monthlyPriceEur(): float
    {
        return (float) config('billing.plans.' . $this->value . '.monthly_price_eur', 0);
    }

    public function yearlyPriceEur(): ?float
    {
        $yearly = config('billing.plans.' . $this->value . '.yearly_price_eur');

        return $yearly === null ? null : (float) $yearly;
    }

    /**
     * @return list<array{
     *     value: string,
     *     max_products: int|null,
     *     monthly_price_eur: float,
     *     yearly_price_eur: float|null
     * }>
     */
    public static function catalogPayload(): array
    {
        return array_map(
            static fn(self $plan): array => [
                'value' => $plan->value,
                'max_products' => $plan->maxProducts(),
                'monthly_price_eur' => $plan->monthlyPriceEur(),
                'yearly_price_eur' => $plan->yearlyPriceEur(),
            ],
            self::cases(),
        );
    }
}
