import { index as productsIndex } from '@/routes/products';
import {
    createEditReturnBackRule,
    registerReturnBackRule,
    useReturnBack,
} from '@/composables/useReturnBack';

const productEditPathPattern = /^\/products\/(\d+)\/edit\/?$/;

export function productEditReturnScope(productId: number): string {
    return `product-edit-return.${productId}`;
}

registerReturnBackRule(
    createEditReturnBackRule({
        matchEdit: (pathname) => {
            const match = pathname.match(productEditPathPattern);

            return match ? productEditReturnScope(Number(match[1])) : null;
        },
        skipFrom: [/^\/products\/create\/?$/],
    }),
);

/**
 * Resolves Back target for product Edit: remembered origin, else products index.
 */
export function useProductEditBack(productId: number) {
    return useReturnBack(
        productEditReturnScope(productId),
        () => productsIndex().url,
    );
}
