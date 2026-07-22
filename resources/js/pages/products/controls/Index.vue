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
    createProductControlColumnTitleMap,
    createProductControlColumns,
} from './columns';
import type { ProductControlListItem } from './columns';
import { index as productControlsApiIndex } from '@/routes/internal/products/controls';
import {
    create as createProductControl,
    destroy as destroyProductControl,
} from '@/routes/products/controls';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { index as productControlsIndex } from '@/routes/products/controls';

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
        titleKey: 'products.controls.index_title',
        href: productControlsIndex(props.product.id),
    },
]);
const { backHref } = useProductModuleBack(props.product.id);

const showDeleteDialog = ref(false);
const controlToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<ProductControlListItem>({
        endpoint: productControlsApiIndex(props.product.id).url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'name',
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

const columnTitleMap = computed(() => createProductControlColumnTitleMap(t));

const requestDelete = (productControlId: number): void => {
    controlToDelete.value = productControlId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createProductControlColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    controlToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (controlToDelete.value === null) {
        return;
    }

    const id = controlToDelete.value;
    controlToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyProductControl({
            product: props.product.id,
            product_control: id,
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

    pagination.value.sortBy = primary?.id ?? 'name';
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
    <Head :title="t('products.controls.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.controls.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.controls.subtitle') }}
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
                        :href="createProductControl(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.controls.assign') }}
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
            :search-placeholder="t('products.controls.search_placeholder')"
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
            :description="t('products.controls.confirm_remove')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
