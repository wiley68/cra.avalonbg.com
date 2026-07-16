import { router, usePage } from '@inertiajs/vue3';
import type { ComputedRef, Ref } from 'vue';
import { computed, onMounted, ref } from 'vue';
import { update as updateAppearanceRoute } from '@/routes/appearance';
import type { Appearance, ResolvedAppearance } from '@/types';

export type { Appearance, ResolvedAppearance };

export type UseAppearanceReturn = {
    appearance: Ref<Appearance>;
    resolvedAppearance: ComputedRef<ResolvedAppearance>;
    updateAppearance: (value: Appearance) => void;
    updateProfileAppearance: (value: Appearance) => void;
};

type ThemePageProps = {
    appearance?: Appearance | null;
    auth?: {
        user?: unknown | null;
    };
};

export function updateTheme(value: Appearance): void {
    if (typeof window === 'undefined') {
        return;
    }

    if (value === 'system') {
        const mediaQueryList = window.matchMedia(
            '(prefers-color-scheme: dark)',
        );
        const systemTheme = mediaQueryList.matches ? 'dark' : 'light';

        document.documentElement.classList.toggle(
            'dark',
            systemTheme === 'dark',
        );
    } else {
        document.documentElement.classList.toggle('dark', value === 'dark');
    }
}

const setCookie = (name: string, value: string, days = 365) => {
    if (typeof document === 'undefined') {
        return;
    }

    const maxAge = days * 24 * 60 * 60;

    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const mediaQuery = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return window.matchMedia('(prefers-color-scheme: dark)');
};

const getStoredAppearance = () => {
    if (typeof window === 'undefined') {
        return null;
    }

    return localStorage.getItem('appearance') as Appearance | null;
};

const prefersDark = (): boolean => {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches;
};

const handleSystemThemeChange = () => {
    updateTheme(appearance.value);
};

export function resolveAppearanceFromPageProps(
    pageProps: ThemePageProps,
): Appearance {
    const serverAppearance = pageProps.appearance ?? 'system';
    const isAuthenticated = pageProps.auth?.user != null;

    if (isAuthenticated) {
        return serverAppearance;
    }

    return getStoredAppearance() ?? serverAppearance;
}

export function syncThemeFromPageProps(pageProps: ThemePageProps): void {
    const resolvedAppearance = resolveAppearanceFromPageProps(pageProps);

    appearance.value = resolvedAppearance;
    localStorage.setItem('appearance', resolvedAppearance);
    setCookie('appearance', resolvedAppearance);
    updateTheme(resolvedAppearance);
}

export function initializeTheme(): void {
    if (typeof window === 'undefined') {
        return;
    }

    mediaQuery()?.addEventListener('change', handleSystemThemeChange);
}

const appearance = ref<Appearance>('system');

function applyAppearance(value: Appearance): void {
    appearance.value = value;
    localStorage.setItem('appearance', value);
    setCookie('appearance', value);
    updateTheme(value);
}

export function useAppearance(): UseAppearanceReturn {
    const page = usePage();

    onMounted(() => {
        syncThemeFromPageProps(page.props as ThemePageProps);
    });

    const resolvedAppearance = computed<ResolvedAppearance>(() => {
        if (appearance.value === 'system') {
            return prefersDark() ? 'dark' : 'light';
        }

        return appearance.value;
    });

    function updateAppearance(value: Appearance): void {
        applyAppearance(value);
    }

    function updateProfileAppearance(value: Appearance): void {
        applyAppearance(value);

        if (page.props.auth.user === null) {
            return;
        }

        router.patch(
            updateAppearanceRoute.url(),
            { appearance: value },
            {
                preserveState: true,
                preserveScroll: true,
            },
        );
    }

    return {
        appearance,
        resolvedAppearance,
        updateAppearance,
        updateProfileAppearance,
    };
}
