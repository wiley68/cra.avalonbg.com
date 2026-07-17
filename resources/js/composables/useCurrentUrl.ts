import type { InertiaLinkProps } from '@inertiajs/vue3';
import { usePage } from '@inertiajs/vue3';
import type { ComputedRef, DeepReadonly } from 'vue';
import { computed, readonly } from 'vue';
import { toUrl } from '@/lib/utils';

export type UseCurrentUrlReturn = {
    currentUrl: DeepReadonly<ComputedRef<string>>;
    isCurrentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    isCurrentOrParentUrl: (
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) => boolean;
    whenCurrentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
    whenCurrentOrParentUrl: <T, F = null>(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: T,
        ifFalse?: F,
    ) => T | F;
};

function resolvePathname(urlString: string): string {
    if (!urlString || urlString === '' || urlString === '#') {
        return '';
    }

    let pathname: string;

    if (urlString.startsWith('http')) {
        try {
            pathname = new URL(urlString).pathname;
        } catch {
            return '';
        }
    } else {
        pathname = urlString.split('?')[0] ?? urlString;
    }

    if (pathname === '/') {
        return '/';
    }

    return pathname.endsWith('/') ? pathname.slice(0, -1) : pathname;
}

const page = usePage();
const currentUrlReactive = computed(
    () =>
        new URL(
            page.url,
            typeof window !== 'undefined'
                ? window.location.origin
                : 'http://localhost',
        ).pathname,
);

export function useCurrentUrl(): UseCurrentUrlReturn {
    function normalizedCurrentPath(currentUrl?: string): string {
        return resolvePathname(currentUrl ?? currentUrlReactive.value);
    }

    function isCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        const comparePath = resolvePathname(toUrl(urlToCheck));

        if (!comparePath) {
            return false;
        }

        return comparePath === normalizedCurrentPath(currentUrl);
    }

    function isCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        currentUrl?: string,
    ) {
        const comparePath = resolvePathname(toUrl(urlToCheck));

        if (!comparePath) {
            return false;
        }

        const current = normalizedCurrentPath(currentUrl);

        if (comparePath === current) {
            return true;
        }

        if (comparePath === '/') {
            return current === '/';
        }

        return current.startsWith(`${comparePath}/`);
    }

    function whenCurrentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    function whenCurrentOrParentUrl(
        urlToCheck: NonNullable<InertiaLinkProps['href']>,
        ifTrue: any,
        ifFalse: any = null,
    ) {
        return isCurrentOrParentUrl(urlToCheck) ? ifTrue : ifFalse;
    }

    return {
        currentUrl: readonly(currentUrlReactive),
        isCurrentUrl,
        isCurrentOrParentUrl,
        whenCurrentUrl,
        whenCurrentOrParentUrl,
    };
}
