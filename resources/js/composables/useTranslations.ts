import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useTranslations() {
    const page = usePage();

    const locale = computed(() => page.props.locale as string);
    const locales = computed(
        () => page.props.locales as Array<{ code: string; label: string }>,
    );

    function t(key: string, replace: Record<string, string> = {}): string {
        const parts = key.split('.');
        let value: unknown = page.props.translations;

        for (const part of parts) {
            if (
                typeof value !== 'object' ||
                value === null ||
                !(part in value)
            ) {
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

    return {
        locale,
        locales,
        t,
    };
}
