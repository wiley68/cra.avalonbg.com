<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import Heading from '@/components/Heading.vue';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { useTranslations } from '@/composables/useTranslations';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { index as integrationHealthIndex } from '@/routes/integrations/health';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editBilling } from '@/routes/settings/billing';
import { edit as editIntegrations } from '@/routes/settings/integrations';
import { edit as editSso } from '@/routes/settings/sso';
import type { NavItem } from '@/types';

const page = usePage();
const { t } = useTranslations();

const sidebarNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;
    const items: NavItem[] = [
        {
            title: t('settings.nav.profile'),
            href: editProfile(),
        },
        {
            title: t('settings.nav.security'),
            href: editSecurity(),
        },
        {
            title: t('settings.nav.appearance'),
            href: editAppearance(),
        },
    ];

    if (!user?.is_platform_admin) {
        items.push({
            title: t('settings.nav.billing'),
            href: editBilling(),
        });

        const plan = String(
            page.props.organization?.subscription_plan ?? '',
        ).toLowerCase();

        if (plan === 'enterprise' || plan === 'standard') {
            items.push({
                title: t('settings.nav.sso'),
                href: editSso(),
            });
        }
    }

    const canAccessIntegrations =
        Boolean(user?.can_view_products || user?.can_manage_products) &&
        !user?.is_platform_admin;

    if (canAccessIntegrations) {
        items.push(
            {
                title: t('settings.nav.integrations'),
                href: editIntegrations(),
            },
            {
                title: t('settings.nav.integration_health'),
                href: integrationHealthIndex({
                    query: { from: page.url },
                }),
            },
        );
    }

    return items;
});

const { isCurrentOrParentUrl } = useCurrentUrl();
</script>

<template>
    <div class="px-4 py-6">
        <Heading
            :title="t('settings.nav.heading')"
            :description="t('settings.nav.description')"
        />

        <div class="flex flex-col lg:flex-row lg:space-x-12">
            <aside class="w-full max-w-xl lg:w-48">
                <nav
                    class="flex flex-col space-y-1 space-x-0"
                    aria-label="Settings"
                >
                    <Button
                        v-for="item in sidebarNavItems"
                        :key="toUrl(item.href)"
                        variant="ghost"
                        :class="[
                            'w-full justify-start',
                            { 'bg-muted': isCurrentOrParentUrl(item.href) },
                        ]"
                        as-child
                    >
                        <Link :href="item.href">
                            <component
                                :is="item.icon"
                                v-if="item.icon"
                                class="h-4 w-4"
                            />
                            {{ item.title }}
                        </Link>
                    </Button>
                </nav>
            </aside>

            <Separator class="my-6 lg:hidden" />

            <div class="flex-1 md:max-w-2xl">
                <section class="max-w-xl space-y-12">
                    <slot />
                </section>
            </div>
        </div>
    </div>
</template>
