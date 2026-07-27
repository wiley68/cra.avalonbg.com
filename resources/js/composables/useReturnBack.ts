import { router } from '@inertiajs/vue3';
import { computed, type ComputedRef } from 'vue';

export type ReturnBackRule = {
    /**
     * When navigating to a tracked page, return a stable storage scope key
     * (without the `cra.` prefix), or null if this rule does not apply.
     */
    matchDestination: (pathname: string) => string | null;
    /**
     * Skip capturing the current page as return URL (e.g. same edit/create).
     */
    shouldSkipCaptureFrom: (pathname: string) => boolean;
};

const rules: ReturnBackRule[] = [];

let trackingInitialized = false;

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

function storageKey(scope: string): string {
    return `cra.${scope}`;
}

export function registerReturnBackRule(rule: ReturnBackRule): void {
    rules.push(rule);
}

/**
 * Helper for Edit pages with a matching Create path that should not overwrite
 * the remembered origin (e.g. after store → edit redirect).
 */
export function createEditReturnBackRule(options: {
    /** Destination edit path → storage scope */
    matchEdit: (pathname: string) => string | null;
    /** Paths that must not become the stored return URL */
    skipFrom?: RegExp[];
}): ReturnBackRule {
    const skipFrom = options.skipFrom ?? [];

    return {
        matchDestination: options.matchEdit,
        shouldSkipCaptureFrom: (pathname) => {
            if (options.matchEdit(pathname) !== null) {
                return true;
            }

            return skipFrom.some((pattern) => pattern.test(pathname));
        },
    };
}

export function setReturnBackUrl(scope: string, url: string): void {
    const safeUrl = toPathAndSearch(url);

    if (!isSafeInternalUrl(safeUrl)) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(scope), safeUrl);
    } catch {
        // Ignore private-mode / storage failures.
    }
}

export function getReturnBackUrl(scope: string): string | null {
    try {
        const stored = sessionStorage.getItem(storageKey(scope));

        if (stored === null || !isSafeInternalUrl(stored)) {
            return null;
        }

        return stored;
    } catch {
        return null;
    }
}

/**
 * Single Inertia listener: when visiting a registered destination, remember
 * the previous internal page for Back.
 */
export function initializeReturnBackTracking(): void {
    if (trackingInitialized || typeof window === 'undefined') {
        return;
    }

    trackingInitialized = true;

    router.on('before', (event) => {
        const destination = toPathAndSearch(String(event.detail.visit.url));
        const destinationPath = destination.split('?')[0] ?? destination;
        const fromPath = window.location.pathname;
        const fromUrl = `${window.location.pathname}${window.location.search}`;

        for (const rule of rules) {
            const scope = rule.matchDestination(destinationPath);

            if (scope === null) {
                continue;
            }

            if (
                fromPath.replace(/\/$/, '') ===
                destinationPath.replace(/\/$/, '')
            ) {
                continue;
            }

            if (rule.shouldSkipCaptureFrom(fromPath)) {
                continue;
            }

            setReturnBackUrl(scope, fromUrl);
        }
    });
}

/**
 * Resolves Back href: remembered origin for `scope`, else `fallbackHref`.
 */
export function useReturnBack(
    scope: string,
    fallbackHref: string | (() => string),
): { backHref: ComputedRef<string> } {
    const backHref = computed(() => {
        const fallback =
            typeof fallbackHref === 'function' ? fallbackHref() : fallbackHref;

        return getReturnBackUrl(scope) ?? fallback;
    });

    return { backHref };
}
