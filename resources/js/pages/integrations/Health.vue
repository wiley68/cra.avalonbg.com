<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { FileDown } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as healthApiIndex } from '@/routes/internal/integrations/health';
import {
    exportMethod as healthExport,
    index as healthIndex,
} from '@/routes/integrations/health';
import { edit as editIntegrations } from '@/routes/settings/integrations';
import {
    createIntegrationHealthColumnTitleMap,
    createIntegrationHealthColumns,
} from './columns';
import type { IntegrationHealthListItem } from './columns';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type OpsQueueHint = {
    level: 'warn' | 'fail';
    code: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    canManage: boolean;
    opsQueueHint: OpsQueueHint | null;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'integrations.health.index_title', href: healthIndex() },
]);

const opsHintMessage = computed(() => {
    if (!props.opsQueueHint) {
        return null;
    }

    const key = `integrations.health.ops_hints.${props.opsQueueHint.code}`;
    const translated = t(key);

    return translated === key ? props.opsQueueHint.code : translated;
});

const { rows, pagination, loading, search, fetch } =
    useApiTable<IntegrationHealthListItem>({
        endpoint: healthApiIndex().url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'health',
            descending: false,
            search: '',
        },
        onError: (message) => {
            toast.error(message);
        },
        autoload: false,
        searchDebounceMs: 400,
    });

const totalPages = computed(() =>
    Math.max(
        1,
        Math.ceil(pagination.value.rowsNumber / pagination.value.rowsPerPage),
    ),
);

const columnTitleMap = computed(() => createIntegrationHealthColumnTitleMap(t));
const columns = computed(() => createIntegrationHealthColumns(t));

const handlePaginationChange = (page: number, pageSize: number) => {
    pagination.value.page = page;
    pagination.value.rowsPerPage = pageSize;
    void fetch();
};

const handleSortingChange = (sorting: SortingState) => {
    const primary = sorting[0];

    pagination.value.sortBy = primary?.id ?? 'health';
    pagination.value.descending = primary?.desc ?? false;
    void fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

onMounted(() => {
    void fetch();
});
</script>

<template>
    <Head :title="t('integrations.health.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('integrations.health.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('integrations.health.subtitle') }} —
                    {{ props.organization.name }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child>
                    <a :href="healthExport('markdown').url">
                        <FileDown class="h-4 w-4" />
                        {{ t('integrations.health.export_markdown') }}
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <a :href="healthExport('pdf').url">
                        <FileDown class="h-4 w-4" />
                        {{ t('integrations.health.export_pdf') }}
                    </a>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="editIntegrations()">
                        {{ t('integrations.health.open_settings') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="opsHintMessage"
            class="rounded-lg border px-4 py-3 text-sm"
            :class="
                props.opsQueueHint?.level === 'fail'
                    ? 'border-destructive/30 bg-destructive/5 text-destructive'
                    : 'border-amber-200 bg-amber-50 text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100'
            "
        >
            {{ opsHintMessage }}
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('integrations.health.search_placeholder')"
            server-side
            :show-pagination="true"
            :show-column-toggle="true"
            :page-size="pagination.rowsPerPage"
            :current-page="pagination.page"
            :total-pages="totalPages"
            :total-items="pagination.rowsNumber"
            @search-change="updateSearch"
            @pagination-change="handlePaginationChange"
            @sorting-change="handleSortingChange"
        />
    </div>
</template>
