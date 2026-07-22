<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, AlertTriangle, Plus, Upload } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import { index as deploymentsApiIndex } from '@/routes/internal/products/deployments';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    create,
    destroy,
    importMethod as deploymentsImport,
    index as deploymentsIndex,
    unsupported as deploymentsUnsupported,
} from '@/routes/products/deployments';
import {
    createDeploymentColumnTitleMap,
    createDeploymentColumns,
    type ProductDeploymentListItem,
} from './columns';

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
    unsupportedCount: number;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.deployments.index_title',
        href: deploymentsIndex(props.product.id),
    },
]);
const { backHref } = useProductModuleBack(props.product.id);

const showDeleteDialog = ref(false);
const deploymentToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<ProductDeploymentListItem>({
        endpoint: deploymentsApiIndex(props.product.id).url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'id',
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

const columnTitleMap = computed(() => createDeploymentColumnTitleMap(t));

const requestDeleteDeployment = (deploymentId: number): void => {
    deploymentToDelete.value = deploymentId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createDeploymentColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
        onDelete: requestDeleteDeployment,
    }),
);

const cancelDelete = (): void => {
    deploymentToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (deploymentToDelete.value === null) {
        return;
    }

    const deploymentId = deploymentToDelete.value;
    deploymentToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroy({
            product: props.product.id,
            deployment: deploymentId,
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
    <Head :title="t('products.deployments.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('products.deployments.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.deployments.subtitle') }} —
                    {{ props.product.name }}
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button v-if="unsupportedCount > 0" as-child variant="outline">
                    <Link
                        :href="deploymentsUnsupported(product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <AlertTriangle class="h-4 w-4" />
                        {{ t('products.deployments.unsupported_link') }}
                        ({{ unsupportedCount }})
                    </Link>
                </Button>
                <Button v-if="canManage" as-child variant="outline">
                    <Link
                        :href="deploymentsImport(product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Upload class="h-4 w-4" />
                        {{ t('products.deployments.import') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child>
                    <Link
                        :href="create(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.deployments.create') }}
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
            :search-placeholder="t('products.deployments.search_placeholder')"
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
            :description="t('products.deployments.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
