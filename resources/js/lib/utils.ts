import type { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx } from 'clsx';
import type { ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function valueUpdater<T>(
    updaterOrValue: T | ((old: T) => T),
    ref: { value: T },
) {
    ref.value =
        typeof updaterOrValue === 'function'
            ? (updaterOrValue as (old: T) => T)(ref.value)
            : updaterOrValue;
}
