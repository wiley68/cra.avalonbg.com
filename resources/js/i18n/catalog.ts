let catalog: Record<string, unknown> = {};
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
    return resolveKey(catalog, key, replace);
}

export function translationsLoaded(): boolean {
    return loadedLocale !== null && Object.keys(catalog).length > 0;
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
        if (loadedLocale === locale && loadedVersion === version) {
            return;
        }
    }

    loading = (async () => {
        const response = await fetch(
            `/translations/${encodeURIComponent(locale)}.json?v=${encodeURIComponent(version)}`,
            {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            },
        );

        if (!response.ok) {
            catalog = {};
            loadedLocale = locale;
            loadedVersion = version;
            return;
        }

        catalog = (await response.json()) as Record<string, unknown>;
        loadedLocale = locale;
        loadedVersion = version;
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
