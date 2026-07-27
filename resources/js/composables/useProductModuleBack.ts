import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    registerReturnBackRule,
    setReturnBackUrl,
    useReturnBack,
} from '@/composables/useReturnBack';

export type ProductModuleOrigin = 'edit' | 'index';

/**
 * Product module entry pages (Index / Show) that share one Back target per product.
 */
const productModuleEntryPathPattern =
    /^\/products\/(\d+)\/(versions|support-periods|components|risks|requirements|controls|evidence|tasks|vulnerabilities|deployments|campaigns|incidents|sdl|passport|readiness|assistant|security-instructions|technical-documentation)(?:\/unsupported)?\/?$/;

/** Hubs that should become the remembered Back target when opening other modules. */
const productModuleHubPathPattern =
    /^\/products\/\d+\/(passport|readiness|assistant)\/?$/;

const productModuleNestedSkipFromPatterns = [
    /^\/products\/\d+\/[^/]+\/create\/?$/,
    /^\/products\/\d+\/[^/]+\/\d+(?:\/[^/]+)*\/(?:edit|create)\/?$/,
    /^\/products\/\d+\/[^/]+\/\d+\/?$/,
];

export function productModuleReturnScope(productId: number): string {
    return `product-module-return.${productId}`;
}

function matchProductModuleEntry(pathname: string): string | null {
    const match = pathname.match(productModuleEntryPathPattern);

    return match ? productModuleReturnScope(Number(match[1])) : null;
}

registerReturnBackRule({
    matchDestination: matchProductModuleEntry,
    shouldSkipCaptureFrom: (pathname) => {
        if (
            productModuleNestedSkipFromPatterns.some((pattern) =>
                pattern.test(pathname),
            )
        ) {
            return true;
        }

        // Keep the original entry when jumping between sibling work modules
        // (versions ↔ risks). Still capture when leaving hubs like passport.
        if (matchProductModuleEntry(pathname) !== null) {
            return !productModuleHubPathPattern.test(pathname);
        }

        return false;
    },
});

/**
 * Explicitly seed the module Back target (used before Inertia visits from
 * product cards / edit module menu). Automatic tracking usually covers this.
 */
export function setProductModuleOrigin(
    productId: number,
    origin: ProductModuleOrigin,
): void {
    const url =
        origin === 'edit' ? editProduct(productId).url : productsIndex().url;

    setReturnBackUrl(productModuleReturnScope(productId), url);
}

/**
 * Resolves Back for product module Index/Show: remembered page (passport,
 * product edit, products index, …), else products index.
 */
export function useProductModuleBack(productId: number) {
    return useReturnBack(
        productModuleReturnScope(productId),
        () => productsIndex().url,
    );
}
