<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { HardDriveDownload, LayoutGrid, Mail, User } from '@lucide/vue';
import { computed } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';

const page = usePage();
const { t } = useTranslations();

const mainNavItems = computed<NavItem[]>(() => [
    {
        title: t('common.dashboard'),
        href: dashboard(),
        icon: LayoutGrid,
    },
]);

function resolveRoleLabel(
    user: NonNullable<typeof page.props.auth.user>,
): string {
    if (user.is_system_admin) {
        return t('admin.users.system_admin');
    }

    if (user.role) {
        const key = `roles.${user.role}`;
        const translated = t(key);

        if (translated !== key) {
            return translated;
        }
    }

    if (typeof user.role_label === 'string' && user.role_label.trim() !== '') {
        return user.role_label;
    }

    return t('roles.user');
}

const footerNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;

    if (!user) {
        return [];
    }

    return [
        {
            title: resolveRoleLabel(user),
            href: '',
            icon: User,
        },
        {
            title: user.email,
            href: '',
            icon: Mail,
        },
        {
            title: String(page.props.version ?? '1.0.0'),
            href: '',
            icon: HardDriveDownload,
        },
    ];
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="dashboard()">
                            <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <NavMain :items="mainNavItems" />
        </SidebarContent>

        <SidebarFooter>
            <NavFooter :items="footerNavItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <slot />
</template>
