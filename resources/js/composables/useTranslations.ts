import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import { translate } from '@/i18n/catalog';

export function useTranslations() {
    const page = usePage();

    const locale = computed(() => page.props.locale as string);
    const locales = computed(
        () => page.props.locales as Array<{ code: string; label: string }>,
    );

    function t(key: string, replace: Record<string, string> = {}): string {
        return translate(key, replace);
    }

    return {
        locale,
        locales,
        t,
    };
}
