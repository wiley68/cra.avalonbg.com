import { shallowRef } from 'vue';

/**
 * Reactive catalog so Vue re-renders when translations finish loading
 * after an Inertia locale change (otherwise UI stays one locale behind).
 */
const catalog = shallowRef<Record<string, unknown>>({});
let loadedLocale: string | null = null;
let loadedVersion: string | null = null;
let loading: Promise<void> | null = null;

function resolveKey(
    tree: unknown,
    key: string,
    replace: Record<string, string> = {},
): string {
    const parts = key.split('.');
    let value: unknown = tree;

    for (const part of parts) {
        if (typeof value !== 'object' || value === null || !(part in value)) {
            return key;
        }

        value = (value as Record<string, unknown>)[part];
    }

    if (typeof value !== 'string') {
        return key;
    }

    return Object.entries(replace).reduce(
        (text, [placeholder, replacement]) =>
            text.replace(`:${placeholder}`, replacement),
        value,
    );
}

export function translate(
    key: string,
    replace: Record<string, string> = {},
): string {
    return resolveKey(catalog.value, key, replace);
}

export function translationsLoaded(): boolean {
    return loadedLocale !== null && Object.keys(catalog.value).length > 0;
}

export async function ensureTranslations(
    locale: string,
    version = '0',
): Promise<void> {
    if (
        loadedLocale === locale &&
        loadedVersion === version &&
        translationsLoaded()
    ) {
        return;
    }

    if (loading) {
        await loading;
        if (
            loadedLocale === locale &&
            loadedVersion === version &&
            translationsLoaded()
        ) {
            return;
        }
    }

    const requestLocale = locale;
    const requestVersion = version;

    loading = (async () => {
        const response = await fetch(
            `/translations/${encodeURIComponent(requestLocale)}.json?v=${encodeURIComponent(requestVersion)}`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            catalog.value = {};
            loadedLocale = requestLocale;
            loadedVersion = requestVersion;
            return;
        }

        catalog.value = (await response.json()) as Record<string, unknown>;
        loadedLocale = requestLocale;
        loadedVersion = requestVersion;
        document.documentElement.lang = requestLocale;
    })();

    try {
        await loading;
    } finally {
        loading = null;
    }
}

export function localeFromHtml(): string {
    const htmlLang = (document.documentElement.lang || 'en').toLowerCase();

    return htmlLang.startsWith('bg') ? 'bg' : htmlLang.slice(0, 2) || 'en';
}
