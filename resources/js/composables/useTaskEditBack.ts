import { router } from '@inertiajs/vue3';
import { computed } from 'vue';
import { index as productTasksIndex } from '@/routes/products/tasks';

const storageKey = (taskId: number): string => `cra.task-edit-return.${taskId}`;

const taskEditPathPattern = /^\/products\/(\d+)\/tasks\/(\d+)\/edit\/?$/;
const taskCreatePathPattern = /^\/products\/\d+\/tasks\/create\/?$/;

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

function idsFromTaskEditPath(
    pathname: string,
): { productId: number; taskId: number } | null {
    const match = pathname.match(taskEditPathPattern);

    if (!match) {
        return null;
    }

    return {
        productId: Number(match[1]),
        taskId: Number(match[2]),
    };
}

function shouldCaptureReturnFrom(pathname: string): boolean {
    if (taskEditPathPattern.test(pathname)) {
        return false;
    }

    if (taskCreatePathPattern.test(pathname)) {
        return false;
    }

    return true;
}

export function setTaskEditReturnUrl(taskId: number, url: string): void {
    const safeUrl = toPathAndSearch(url);

    if (!isSafeInternalUrl(safeUrl)) {
        return;
    }

    try {
        sessionStorage.setItem(storageKey(taskId), safeUrl);
    } catch {
        // Ignore private-mode / storage failures.
    }
}

export function getTaskEditReturnUrl(taskId: number): string | null {
    try {
        const stored = sessionStorage.getItem(storageKey(taskId));

        if (stored === null || !isSafeInternalUrl(stored)) {
            return null;
        }

        return stored;
    } catch {
        return null;
    }
}

/**
 * Remembers the page the user came from when opening task Edit,
 * so Back can return there (e.g. Dashboard). Skips task edit/create.
 */
export function initializeTaskEditReturnTracking(): void {
    router.on('before', (event) => {
        if (typeof window === 'undefined') {
            return;
        }

        const destination = toPathAndSearch(String(event.detail.visit.url));
        const destinationPath = destination.split('?')[0] ?? destination;
        const ids = idsFromTaskEditPath(destinationPath);

        if (ids === null) {
            return;
        }

        const fromPath = window.location.pathname;

        if (!shouldCaptureReturnFrom(fromPath)) {
            return;
        }

        setTaskEditReturnUrl(
            ids.taskId,
            `${window.location.pathname}${window.location.search}`,
        );
    });
}

/**
 * Resolves Back target for task Edit: remembered origin, else product tasks index.
 */
export function useTaskEditBack(productId: number, taskId: number) {
    const backHref = computed(
        () => getTaskEditReturnUrl(taskId) ?? productTasksIndex(productId).url,
    );

    return { backHref };
}
