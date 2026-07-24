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
import { index as techDocIndex } from '@/routes/technical-documentation';
import { index as techDocApiIndex } from '@/routes/internal/technical-documentation';
import { destroy as destroyPackage } from '@/routes/products/technical-documentation';
import {
    createOrgTechnicalDocumentationColumnTitleMap,
    createOrgTechnicalDocumentationColumns,
    type OrgTechnicalDocumentationListItem,
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
    { titleKey: 'nav.technical_documentation', href: techDocIndex() },
]);

const showDeleteDialog = ref(false);
const packageToDelete = ref<{ id: number; productId: number } | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<OrgTechnicalDocumentationListItem>({
        endpoint: techDocApiIndex().url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'updated_at',
            descending: true,
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

const columnTitleMap = computed(() =>
    createOrgTechnicalDocumentationColumnTitleMap(t),
);

const requestDelete = (packageId: number, productId: number): void => {
    packageToDelete.value = { id: packageId, productId };
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createOrgTechnicalDocumentationColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    packageToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (packageToDelete.value === null) {
        return;
    }

    const { id, productId } = packageToDelete.value;
    packageToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyPackage({
            product: productId,
            package: id,
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

    pagination.value.sortBy = primary?.id ?? 'updated_at';
    pagination.value.descending = primary?.desc ?? true;
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
    <Head :title="t('technical_documentation.index_title')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">
                {{ t('technical_documentation.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('technical_documentation.subtitle') }}
            </p>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="
                t('technical_documentation.search_placeholder')
            "
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
            :description="t('products.technical_documentation.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
