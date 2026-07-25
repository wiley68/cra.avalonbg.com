import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as productsIndex } from '@/routes/products';

const storageKey = (productId: number): string =>
    `cra.product-edit-return.${productId}`;

const productEditPathPattern = /^\/products\/(\d+)\/edit\/?$/;
const productScopedPathPattern = /^\/products\/(\d+)(\/|$)/;

function toPathAndSearch(url: string): string {
    try {
        const parsed = new URL(url, window.location.origin);

        if (parsed.origin !== window.location.origin) {
            return '';
        }

        return `${parsed.pathname}${parsed.search}`;
    } catch {
        return url.startsWith('/') && !url.startsWith('//') ? url : '';
    }
}

function isSafeInternalUrl(url: string): boolean {
    return url.startsWith('/') && !url.startsWith('//') && !url.includes('://');
}

function productIdFromEditPath(pathname: string): number | null {
    const match = pathname.match(productEditPathPattern);

    return match ? Number(match[1]) : null;
}

function shouldCaptureReturnFrom(pathname: string): boolean {
    if (productEditPathPattern.test(pathname)) {
        return false;
    }

    if (pathname === '/products/create') {
        return false;
    }

    // Returning from a product submodule must not overwrite the original entry point.
    if (productScopedPathPattern.test(pathname)) {
        return false;
    }

    return true;
}

export function setProductEditReturnUrl(productId: number, url: string): void {
    const safeUrl = toPathAndSearch(url);

    if (!isSafeInternalUrl(safeUrl)) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(productId), safeUrl);
    } catch {
        // Ignore private-mode / storage failures.
    }
}

export function getProductEditReturnUrl(productId: number): string | null {
    try {
        const stored = sessionStorage.getItem(storageKey(productId));

        if (stored === null || !isSafeInternalUrl(stored)) {
            return null;
        }

        return stored;
    } catch {
        return null;
    }
}

/**
 * Remembers the page the user came from when opening product Edit,
 * so Back can return there. Skips product submodule navigations.
 */
export function initializeProductEditReturnTracking(): void {
    router.on('before', (event) => {
        if (typeof window === 'undefined') {
            return;
        }

        const destination = toPathAndSearch(String(event.detail.visit.url));
        const destinationPath = destination.split('?')[0] ?? destination;
        const productId = productIdFromEditPath(destinationPath);

        if (productId === null) {
            return;
        }

        const fromPath = window.location.pathname;

        if (!shouldCaptureReturnFrom(fromPath)) {
            return;
        }

        setProductEditReturnUrl(
            productId,
            `${window.location.pathname}${window.location.search}`,
        );
    });
}

/**
 * Resolves Back target for product Edit: remembered origin, else products index.
 */
export function useProductEditBack(productId: number) {
    const backHref = computed(
        () => getProductEditReturnUrl(productId) ?? productsIndex().url,
    );

    return { backHref };
}
