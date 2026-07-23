<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle2, Info, Package } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { dashboard as dashboardRoute } from '@/routes';
import { index as organizationsIndex } from '@/routes/admin/organizations';
import { index as productsIndex } from '@/routes/products';

type DashboardActionItem = {
    id: number;
    title: string;
    href: string;
};

type DashboardAction = {
    key: string;
    severity: 'info' | 'warn' | 'fail';
    title_key: string;
    count: number;
    href: string | null;
    items?: DashboardActionItem[];
};

type DashboardPayload = {
    mode: 'platform' | 'organization' | 'empty';
    organization: { id: number; name: string; slug: string } | null;
    counts: Record<string, number>;
    actions: DashboardAction[];
};

defineProps<{
    dashboard: DashboardPayload;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'common.dashboard', href: dashboardRoute() },
]);

const severityClass = (severity: string): string => {
    if (severity === 'fail') {
        return 'border-red-200 bg-red-50 text-red-900 dark:border-red-900/50 dark:bg-red-950/40 dark:text-red-100';
    }

    if (severity === 'warn') {
        return 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100';
    }

    return 'border-sky-200 bg-sky-50 text-sky-900 dark:border-sky-900/50 dark:bg-sky-950/40 dark:text-sky-100';
};
</script>

<template>
    <Head :title="t('dashboard.title')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">{{ t('dashboard.title') }}</h1>
            <p class="text-sm text-muted-foreground">
                <template v-if="dashboard.mode === 'organization'">
                    {{ dashboard.organization?.name }} —
                    {{ t('dashboard.subtitle_org') }}
                </template>
                <template v-else-if="dashboard.mode === 'platform'">
                    {{ t('dashboard.subtitle_platform') }}
                </template>
                <template v-else>
                    {{ t('dashboard.subtitle_empty') }}
                </template>
            </p>
        </div>

        <div
            v-if="dashboard.mode === 'organization'"
            class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4"
        >
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.products') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.products ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.critical_vulnerabilities') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.critical_vulnerabilities ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.open_incidents') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.open_incidents ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.unclassified_incidents') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.unclassified_incidents ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.expired_evidence') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.expired_evidence ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.open_tasks') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.open_tasks ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.risks') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.risks ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.overdue_reporting') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.overdue_reporting ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.sdl_approved') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.sdl_approved ?? 0 }}
                </p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-sm text-muted-foreground">
                    {{ t('dashboard.counts.sdl_pending_monitoring') }}
                </p>
                <p class="text-2xl font-semibold">
                    {{ dashboard.counts.sdl_pending_monitoring ?? 0 }}
                </p>
            </div>
        </div>

        <div class="space-y-3">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-medium">
                    {{ t('dashboard.actions_title') }}
                </h2>
                <Button
                    v-if="dashboard.mode === 'organization'"
                    as-child
                    variant="outline"
                >
                    <Link :href="productsIndex()">
                        <Package class="h-4 w-4" />
                        {{ t('nav.products') }}
                    </Link>
                </Button>
                <Button
                    v-else-if="dashboard.mode === 'platform'"
                    as-child
                    variant="outline"
                >
                    <Link :href="organizationsIndex()">
                        {{ t('nav.organizations') }}
                    </Link>
                </Button>
            </div>

            <div
                v-if="dashboard.actions.length === 0"
                class="flex items-start gap-3 rounded-lg border p-4 text-sm"
            >
                <CheckCircle2
                    class="mt-0.5 h-5 w-5 shrink-0 text-emerald-600"
                />
                <div>
                    <p class="font-medium">
                        {{ t('dashboard.all_clear_title') }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ t('dashboard.all_clear_body') }}
                    </p>
                </div>
            </div>

            <div
                v-for="action in dashboard.actions"
                :key="action.key"
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
                :class="severityClass(action.severity)"
            >
                <div class="flex min-w-0 flex-1 items-start gap-3">
                    <AlertTriangle
                        v-if="action.severity !== 'info'"
                        class="mt-0.5 h-5 w-5 shrink-0"
                    />
                    <Info v-else class="mt-0.5 h-5 w-5 shrink-0" />
                    <div class="min-w-0 space-y-2">
                        <div>
                            <p class="font-medium">
                                {{ t(action.title_key) }}
                            </p>
                            <p class="text-sm opacity-80">
                                {{
                                    t('dashboard.count_label', {
                                        count: String(action.count),
                                    })
                                }}
                            </p>
                        </div>
                        <ul
                            v-if="action.items?.length"
                            class="space-y-1 text-sm"
                        >
                            <li
                                v-for="item in action.items"
                                :key="item.id"
                                class="truncate"
                            >
                                <Link
                                    :href="item.href"
                                    class="underline underline-offset-2 hover:opacity-80"
                                >
                                    {{ item.title }}
                                </Link>
                            </li>
                        </ul>
                    </div>
                </div>
                <Button
                    v-if="action.href"
                    as-child
                    size="sm"
                    variant="secondary"
                    class="shrink-0"
                >
                    <Link :href="action.href">{{ t('dashboard.open') }}</Link>
                </Button>
            </div>
        </div>
    </div>
</template>
