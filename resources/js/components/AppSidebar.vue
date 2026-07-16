<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import {
    Building2,
    HardDriveDownload,
    LayoutGrid,
    Mail,
    User,
    Users,
} from '@lucide/vue';
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
import { index as usersIndex } from '@/routes/users';
import type { NavItem } from '@/types';
import { index as organizationsIndex } from '@/routes/admin/organizations';

const page = usePage();
const { t } = useTranslations();

const mainNavItems = computed<NavItem[]>(() => {
    const user = page.props.auth.user;
    const items: NavItem[] = [
        {
            title: t('common.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
    ];

    if (!user) {
        return items;
    }

    if (user.can_manage_organizations || user.is_system_admin) {
        items.push({
            title: t('nav.organizations'),
            href: organizationsIndex(),
            icon: Building2,
        });
    }

    if (user.can_manage_users) {
        items.push({
            title: t('nav.users'),
            href: usersIndex(),
            icon: Users,
        });
    }

    return items;
});

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
