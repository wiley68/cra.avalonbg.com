import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as productSdlIndex } from '@/routes/products/sdl';

const storageKey = (sdlRunId: number): string =>
    `cra.sdl-edit-return.${sdlRunId}`;

const sdlEditPathPattern = /^\/products\/(\d+)\/sdl\/(\d+)\/edit\/?$/;
const sdlCreatePathPattern = /^\/products\/\d+\/sdl\/create\/?$/;

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

function idsFromSdlEditPath(
    pathname: string,
): { productId: number; sdlRunId: number } | null {
    const match = pathname.match(sdlEditPathPattern);

    if (!match) {
        return null;
    }

    return {
        productId: Number(match[1]),
        sdlRunId: Number(match[2]),
    };
}

function shouldCaptureReturnFrom(pathname: string): boolean {
    if (sdlEditPathPattern.test(pathname)) {
        return false;
    }

    if (sdlCreatePathPattern.test(pathname)) {
        return false;
    }

    return true;
}

export function setSdlEditReturnUrl(sdlRunId: number, url: string): void {
    const safeUrl = toPathAndSearch(url);

    if (!isSafeInternalUrl(safeUrl)) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(sdlRunId), safeUrl);
    } catch {
        // Ignore private-mode / storage failures.
    }
}

export function getSdlEditReturnUrl(sdlRunId: number): string | null {
    try {
        const stored = sessionStorage.getItem(storageKey(sdlRunId));

        if (stored === null || !isSafeInternalUrl(stored)) {
            return null;
        }

        return stored;
    } catch {
        return null;
    }
}

/**
 * Remembers the page the user came from when opening SDL Edit,
 * so Back can return there (e.g. Dashboard). Skips SDL edit/create.
 */
export function initializeSdlEditReturnTracking(): void {
    router.on('before', (event) => {
        if (typeof window === 'undefined') {
            return;
        }

        const destination = toPathAndSearch(String(event.detail.visit.url));
        const destinationPath = destination.split('?')[0] ?? destination;
        const ids = idsFromSdlEditPath(destinationPath);

        if (ids === null) {
            return;
        }

        const fromPath = window.location.pathname;

        if (!shouldCaptureReturnFrom(fromPath)) {
            return;
        }

        setSdlEditReturnUrl(
            ids.sdlRunId,
            `${window.location.pathname}${window.location.search}`,
        );
    });
}

/**
 * Resolves Back target for SDL Edit: remembered origin, else product SDL index.
 */
export function useSdlEditBack(productId: number, sdlRunId: number) {
    const backHref = computed(
        () => getSdlEditReturnUrl(sdlRunId) ?? productSdlIndex(productId).url,
    );

    return { backHref };
}
