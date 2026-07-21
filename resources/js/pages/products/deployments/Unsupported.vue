<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import { index as deploymentsApiIndex } from '@/routes/internal/products/deployments';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { index as deploymentsIndex } from '@/routes/products/deployments';
import {
    createUnsupportedDeploymentColumnTitleMap,
    createUnsupportedDeploymentColumns,
    type UnsupportedDeploymentListItem,
} from './unsupportedColumns';

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
    {
        titleKey: 'products.deployments.index_title',
        href: deploymentsIndex(props.product.id),
    },
    {
        titleKey: 'products.deployments.unsupported_title',
        href: deploymentsIndex(props.product.id),
    },
]);
const { backHref } = useProductModuleBack(props.product.id);

const { rows, pagination, loading, search, fetch } =
    useApiTable<UnsupportedDeploymentListItem>({
        endpoint: deploymentsApiIndex(props.product.id).url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'id',
            descending: true,
            search: '',
        },
        getExtraParams: () => ({
            unsupported_only: '1',
        }),
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
    createUnsupportedDeploymentColumnTitleMap(t),
);

const columns = computed(() =>
    createUnsupportedDeploymentColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
    }),
);

const handlePaginationChange = (page: number, pageSize: number) => {
    pagination.value.page = page;
    pagination.value.rowsPerPage = pageSize;
    void fetch();
};

const handleSortingChange = (sorting: SortingState) => {
    const primary = sorting[0];

    pagination.value.sortBy = primary?.id ?? 'id';
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
    <Head :title="t('products.deployments.unsupported_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('products.deployments.unsupported_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.deployments.unsupported_subtitle') }} —
                    {{ props.product.name }}
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button as-child variant="outline">
                    <Link :href="deploymentsIndex(product.id)">
                        {{ t('products.deployments.view_all') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p class="text-sm text-muted-foreground">
            {{ t('products.deployments.unsupported_hint') }}
        </p>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="
                t('products.deployments.unsupported_search_placeholder')
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
    </div>
</template>
