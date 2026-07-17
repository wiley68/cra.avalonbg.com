<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { ChevronDown } from '@lucide/vue';
import { computed } from 'vue';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import type { NavItem } from '@/types';

defineProps<{
    items: NavItem[];
}>();

const page = usePage();
const { isCurrentOrParentUrl } = useCurrentUrl();

function isNavItemActive(item: NavItem): boolean {
    if (item.href && isCurrentOrParentUrl(item.href)) {
        return true;
    }

    return (
        item.children?.some((child) => isCurrentOrParentUrl(child.href)) ??
        false
    );
}

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
            <template v-for="item in items" :key="item.title">
                <SidebarMenuItem
                    v-if="!item.children || item.children.length === 0"
                >
                    <SidebarMenuButton
                        as-child
                        :is-active="isNavItemActive(item)"
                        :tooltip="item.title"
                    >
                        <Link :href="item.href">
                            <component :is="item.icon" />
                            <span>{{ item.title }}</span>
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>

                <Collapsible
                    v-else
                    as-child
                    :default-open="isNavItemActive(item)"
                >
                    <SidebarMenuItem>
                        <CollapsibleTrigger as-child>
                            <SidebarMenuButton
                                :is-active="isNavItemActive(item)"
                                :tooltip="item.title"
                            >
                                <component :is="item.icon" />
                                <span>{{ item.title }}</span>
                                <ChevronDown
                                    class="ml-auto transition-transform group-data-[state=open]/collapsible:rotate-180"
                                />
                            </SidebarMenuButton>
                        </CollapsibleTrigger>
                        <CollapsibleContent>
                            <SidebarMenuSub>
                                <SidebarMenuSubItem
                                    v-for="child in item.children"
                                    :key="child.title"
                                >
                                    <SidebarMenuSubButton
                                        as-child
                                        :is-active="
                                            isCurrentOrParentUrl(child.href)
                                        "
                                    >
                                        <Link :href="child.href">
                                            <component
                                                v-if="child.icon"
                                                :is="child.icon"
                                            />
                                            <span>{{ child.title }}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            </SidebarMenuSub>
                        </CollapsibleContent>
                    </SidebarMenuItem>
                </Collapsible>
            </template>
        </SidebarMenu>
    </SidebarGroup>
</template>
