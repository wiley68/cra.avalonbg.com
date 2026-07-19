<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    createProductRiskColumnTitleMap,
    createProductRiskColumns,
} from './columns';
import type { ProductRiskListItem } from './columns';
import { index as productRisksApiIndex } from '@/routes/internal/products/risks';
import {
    create as createProductRisk,
    destroy as destroyProductRisk,
} from '@/routes/products/risks';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { index as productRisksIndex } from '@/routes/products/risks';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.risks.index_title', href: productRisksIndex(props.product.id) },
]);
const { backHref } = useProductModuleBack(props.product.id);

const showDeleteDialog = ref(false);
const riskToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<ProductRiskListItem>({
        endpoint: productRisksApiIndex(props.product.id).url,
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

const columnTitleMap = computed(() => createProductRiskColumnTitleMap(t));

const requestDelete = (riskId: number): void => {
    riskToDelete.value = riskId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createProductRiskColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    riskToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (riskToDelete.value === null) {
        return;
    }

    const id = riskToDelete.value;
    riskToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyProductRisk({
            product: props.product.id,
            risk: id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: async () => {
                rows.value = rows.value.filter((row) => row.id !== id);
                pagination.value.rowsNumber = Math.max(
                    0,
                    pagination.value.rowsNumber - 1,
                );

                if (rows.value.length === 0 && pagination.value.page > 1) {
                    pagination.value.page--;
                    await fetch();
                }
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
    <Head :title="t('products.risks.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.risks.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.risks.subtitle') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child>
                    <Link
                        :href="createProductRisk(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.risks.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('products.risks.search_placeholder')"
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
            :description="t('products.risks.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
