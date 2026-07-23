<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as incidentsIndex } from '@/routes/incidents';
import { index as incidentsApiIndex } from '@/routes/internal/incidents';
import { destroy as destroyProductIncident } from '@/routes/products/incidents';
import {
    createOrgIncidentColumnTitleMap,
    createOrgIncidentColumns,
    type OrgIncidentListItem,
} from './columns';

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
    { titleKey: 'nav.incidents', href: incidentsIndex() },
]);

const showDeleteDialog = ref(false);
const incidentToDelete = ref<{ id: number; productId: number } | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<OrgIncidentListItem>({
        endpoint: incidentsApiIndex().url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'title',
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

const columnTitleMap = computed(() => createOrgIncidentColumnTitleMap(t));

const requestDelete = (incidentId: number, productId: number): void => {
    incidentToDelete.value = { id: incidentId, productId };
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createOrgIncidentColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    incidentToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (incidentToDelete.value === null) {
        return;
    }

    const { id, productId } = incidentToDelete.value;
    incidentToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyProductIncident({
            product: productId,
            incident: id,
        }).url,
        {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => {
                void fetch();
            },
        },
    );
};

const handlePaginationChange = (page: number, pageSize: number) => {
    pagination.value.page = page;
    pagination.value.rowsPerPage = pageSize;
    void fetch();
};

const handleSortingChange = (sorting: SortingState) => {
    const primary = sorting[0];

    pagination.value.sortBy = primary?.id ?? 'title';
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
    <Head :title="t('incidents.index_title')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">
                {{ t('incidents.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('incidents.subtitle') }}
            </p>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('incidents.search_placeholder')"
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

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.incidents.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
