<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

const organizationLabel = computed(() => {
    const organization = page.props.organization;

    if (
        organization &&
        typeof organization === 'object' &&
        'name' in organization &&
        typeof organization.name === 'string' &&
        organization.name.trim() !== ''
    ) {
        return organization.name;
    }

    const appName = page.props.name;

    return typeof appName === 'string' && appName.trim() !== ''
        ? appName
        : 'CRA Compliance Workspace';
});
</script>

<template>
    <SidebarGroup class="px-2 py-0">
        <SidebarGroupLabel>{{ organizationLabel }}</SidebarGroupLabel>
        <SidebarMenu>
            <SidebarMenuItem v-for="item in items" :key="item.title">
                <SidebarMenuButton
                    as-child
                    :is-active="isCurrentOrParentUrl(item.href)"
                    :tooltip="item.title"
                >
                    <Link :href="item.href">
                        <component :is="item.icon" />
                        <span>{{ item.title }}</span>
                    </Link>
                </SidebarMenuButton>
            </SidebarMenuItem>
        </SidebarMenu>
    </SidebarGroup>
</template>
