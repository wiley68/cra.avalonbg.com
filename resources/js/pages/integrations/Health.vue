<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as healthApiIndex } from '@/routes/internal/integrations/health';
import { index as healthIndex } from '@/routes/integrations/health';
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

const props = defineProps<{
    organization: OrganizationSummary;
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'integrations.health.index_title', href: healthIndex() },
]);

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

            <Button variant="outline" as-child>
                <Link :href="editIntegrations()">
                    {{ t('integrations.health.open_settings') }}
                </Link>
            </Button>
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
