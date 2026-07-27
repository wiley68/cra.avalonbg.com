import { index as productRisksIndex } from '@/routes/products/risks';
import {
    createEditReturnBackRule,
    registerReturnBackRule,
    useReturnBack,
} from '@/composables/useReturnBack';

const riskEditPathPattern = /^\/products\/(\d+)\/risks\/(\d+)\/edit\/?$/;

export function riskEditReturnScope(riskId: number): string {
    return `risk-edit-return.${riskId}`;
}

registerReturnBackRule(
    createEditReturnBackRule({
        matchEdit: (pathname) => {
            const match = pathname.match(riskEditPathPattern);

            return match ? riskEditReturnScope(Number(match[2])) : null;
        },
        skipFrom: [/^\/products\/\d+\/risks\/create\/?$/],
    }),
);

/**
 * Resolves Back target for risk Edit: remembered origin, else product risks index.
 */
export function useRiskEditBack(productId: number, riskId: number) {
    return useReturnBack(
        riskEditReturnScope(riskId),
        () => productRisksIndex(productId).url,
    );
}
