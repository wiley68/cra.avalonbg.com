<script setup lang="ts">
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue';
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import BillingNoticeBanner from '@/components/BillingNoticeBanner.vue';
import TrialNoticeBanner from '@/components/TrialNoticeBanner.vue';
import AiPlanLockedDialog from '@/components/AiPlanLockedDialog.vue';
import { Toaster } from '@/components/ui/sonner';
import type { BreadcrumbItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar" class="overflow-x-hidden">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <div class="flex flex-1 flex-col p-4 md:p-6">
                <BillingNoticeBanner />
                <TrialNoticeBanner />
                <slot />
            </div>
        </AppContent>
        <AiPlanLockedDialog />
        <Toaster />
    </AppShell>
</template>
