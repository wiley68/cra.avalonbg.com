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
import { index as sdlIndex } from '@/routes/sdl';
import { index as sdlApiIndex } from '@/routes/internal/sdl';
import { destroy as destroySdlRun } from '@/routes/products/sdl';
import {
    createOrgSdlRunColumnTitleMap,
    createOrgSdlRunColumns,
    type OrgSdlRunListItem,
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

usePageBreadcrumbs(() => [{ titleKey: 'nav.sdl', href: sdlIndex() }]);

const showDeleteDialog = ref(false);
const runToDelete = ref<{ id: number; productId: number } | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<OrgSdlRunListItem>({
        endpoint: sdlApiIndex().url,
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

const columnTitleMap = computed(() => createOrgSdlRunColumnTitleMap(t));

const requestDelete = (runId: number, productId: number): void => {
    runToDelete.value = { id: runId, productId };
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createOrgSdlRunColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    runToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (runToDelete.value === null) {
        return;
    }

    const { id, productId } = runToDelete.value;
    runToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroySdlRun({
            product: productId,
            sdlRun: id,
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
    <Head :title="t('sdl.index_title')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">
                {{ t('sdl.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('sdl.subtitle') }}
            </p>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('sdl.search_placeholder')"
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
            :description="t('products.sdl.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
