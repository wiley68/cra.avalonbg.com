import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as productRisksIndex } from '@/routes/products/risks';

const storageKey = (riskId: number): string => `cra.risk-edit-return.${riskId}`;

const riskEditPathPattern = /^\/products\/(\d+)\/risks\/(\d+)\/edit\/?$/;
const riskCreatePathPattern = /^\/products\/\d+\/risks\/create\/?$/;

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

function idsFromRiskEditPath(
    pathname: string,
): { productId: number; riskId: number } | null {
    const match = pathname.match(riskEditPathPattern);

    if (!match) {
        return null;
    }

    return {
        productId: Number(match[1]),
        riskId: Number(match[2]),
    };
}

function shouldCaptureReturnFrom(pathname: string): boolean {
    if (riskEditPathPattern.test(pathname)) {
        return false;
    }

    if (riskCreatePathPattern.test(pathname)) {
        return false;
    }

    return true;
}

export function setRiskEditReturnUrl(riskId: number, url: string): void {
    const safeUrl = toPathAndSearch(url);

    if (!isSafeInternalUrl(safeUrl)) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(riskId), safeUrl);
    } catch {
        // Ignore private-mode / storage failures.
    }
}

export function getRiskEditReturnUrl(riskId: number): string | null {
    try {
        const stored = sessionStorage.getItem(storageKey(riskId));

        if (stored === null || !isSafeInternalUrl(stored)) {
            return null;
        }

        return stored;
    } catch {
        return null;
    }
}

/**
 * Remembers the page the user came from when opening risk Edit,
 * so Back can return there (e.g. Dashboard). Skips risk edit/create.
 */
export function initializeRiskEditReturnTracking(): void {
    router.on('before', (event) => {
        if (typeof window === 'undefined') {
            return;
        }

        const destination = toPathAndSearch(String(event.detail.visit.url));
        const destinationPath = destination.split('?')[0] ?? destination;
        const ids = idsFromRiskEditPath(destinationPath);

        if (ids === null) {
            return;
        }

        const fromPath = window.location.pathname;

        if (!shouldCaptureReturnFrom(fromPath)) {
            return;
        }

        setRiskEditReturnUrl(
            ids.riskId,
            `${window.location.pathname}${window.location.search}`,
        );
    });
}

/**
 * Resolves Back target for risk Edit: remembered origin, else product risks index.
 */
export function useRiskEditBack(productId: number, riskId: number) {
    const backHref = computed(
        () => getRiskEditReturnUrl(riskId) ?? productRisksIndex(productId).url,
    );

    return { backHref };
}
