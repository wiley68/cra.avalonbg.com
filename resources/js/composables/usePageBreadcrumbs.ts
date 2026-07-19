import { setLayoutProps } from '@inertiajs/vue3';
import { watchEffect } from 'vue';
import { useTranslations } from '@/composables/useTranslations';
import type { BreadcrumbItem } from '@/types';

export type BreadcrumbDef = {
    title?: string;
    titleKey?: string;
    href: BreadcrumbItem['href'];
};

/**
 * Sets translated breadcrumbs on the current Inertia layout.
 * Pass a getter so titles react to locale and prop changes.
 */
export function usePageBreadcrumbs(items: () => BreadcrumbDef[]): void {
    const { t } = useTranslations();

    watchEffect(() => {
        setLayoutProps({
            breadcrumbs: items().map((item) => ({
                title: item.titleKey ? t(item.titleKey) : (item.title ?? ''),
                href: item.href,
            })),
        });
    });
}
