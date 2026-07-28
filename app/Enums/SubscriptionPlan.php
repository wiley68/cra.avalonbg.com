<?php

namespace App\Enums;

enum SubscriptionPlan: string
{
    case Free = 'free';
    case Small = 'small';
    case Standard = 'standard';
    case Enterprise = 'enterprise';

    public static function tryFromStored(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::tryFrom(strtolower(trim($value)));
    }

    public static function fromStoredOrDefault(?string $value): self
    {
        $resolved = self::tryFromStored($value);
        if ($resolved !== null) {
            return $resolved;
        }

        $default = (string) config('billing.default_plan', self::Free->value);

        return self::tryFrom($default) ?? self::Free;
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

    /**
     * Maximum organization seats (users); null = unlimited.
     */
    public function maxSeats(): ?int
    {
        $max = config('billing.plans.' . $this->value . '.max_seats');

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
     *     max_seats: int|null,
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
                'max_seats' => $plan->maxSeats(),
                'monthly_price_eur' => $plan->monthlyPriceEur(),
                'yearly_price_eur' => $plan->yearlyPriceEur(),
            ],
            self::cases(),
        );
    }
}
