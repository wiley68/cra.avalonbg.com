export type PlanPriceOption = {
    value: string;
    monthly_price_eur: number;
    yearly_price_eur: number | null;
};

export function formatEur(amount: number): string {
    const rounded = Math.round(amount * 100) / 100;

    return Number.isInteger(rounded) ? String(rounded) : rounded.toFixed(2);
}

/** 12× monthly list price (before annual discount). */
export function monthlyYearTotalEur(plan: PlanPriceOption): number | null {
    if (plan.monthly_price_eur <= 0) {
        return null;
    }

    return Math.round(plan.monthly_price_eur * 12 * 100) / 100;
}

/** Absolute EUR saved by paying yearly vs 12× monthly. */
export function annualSavingsEur(plan: PlanPriceOption): number | null {
    const yearly = plan.yearly_price_eur;
    const monthlyYear = monthlyYearTotalEur(plan);

    if (yearly === null || monthlyYear === null) {
        return null;
    }

    const savings = Math.round((monthlyYear - yearly) * 100) / 100;

    return savings > 0 ? savings : null;
}
