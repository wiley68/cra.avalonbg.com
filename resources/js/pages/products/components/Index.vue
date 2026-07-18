<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Upload } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import { index as productsIndex } from '@/routes/products';
import {
    createProductComponentColumnTitleMap,
    createProductComponentColumns,
} from './columns';
import type { ProductComponentListItem } from './columns';
import { index as productComponentsApiIndex } from '@/routes/internal/products/components';
import {
    create as createProductComponent,
    destroy as destroyProductComponent,
    importMethod as importProductComponents,
} from '@/routes/products/components';

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

type VersionOption = {
    id: number;
    version_number: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    versions: VersionOption[];
    canManage: boolean;
}>();

const { t } = useTranslations();

const showDeleteDialog = ref(false);
const componentToDelete = ref<number | null>(null);
const versionFilter = ref<string>('');

const { rows, pagination, loading, search, fetch } =
    useApiTable<ProductComponentListItem>({
        endpoint: productComponentsApiIndex(props.product.id).url,
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
        getExtraParams: (): Record<string, string> => {
            const params: Record<string, string> = {};

            if (versionFilter.value !== '') {
                params.version_id = versionFilter.value;
            }

            return params;
        },
    });

const totalPages = computed(() =>
    Math.max(
        1,
        Math.ceil(pagination.value.rowsNumber / pagination.value.rowsPerPage),
    ),
);

const columnTitleMap = computed(() => createProductComponentColumnTitleMap(t));

const requestDelete = (componentId: number): void => {
    componentToDelete.value = componentId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createProductComponentColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    componentToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (componentToDelete.value === null) {
        return;
    }

    const id = componentToDelete.value;
    componentToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyProductComponent({
            product: props.product.id,
            component: id,
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

    pagination.value.sortBy = primary?.id ?? 'name';
    pagination.value.descending = primary?.desc ?? false;
    void fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

watch(versionFilter, () => {
    pagination.value.page = 1;
    void fetch();
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const versionId = params.get('version_id');

    if (versionId) {
        versionFilter.value = versionId;
    } else {
        void fetch();
    }
});
</script>

<template>
    <Head :title="t('products.components.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.components.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.components.subtitle') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="productsIndex()">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child variant="outline">
                    <Link
                        :href="importProductComponents(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Upload class="h-4 w-4" />
                        {{ t('products.components.import') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child>
                    <Link
                        :href="createProductComponent(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.components.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div class="max-w-xs space-y-2">
            <FieldLabel
                html-for="version_filter"
                :help="t('products.components.help.filter_version')"
            >
                {{ t('products.components.filter_version') }}
            </FieldLabel>
            <select
                id="version_filter"
                v-model="versionFilter"
                class="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:ring-1 focus-visible:ring-ring focus-visible:outline-none"
            >
                <option value="">
                    {{ t('products.components.filter_version_all') }}
                </option>
                <option
                    v-for="version in versions"
                    :key="version.id"
                    :value="String(version.id)"
                >
                    {{ version.version_number }}
                </option>
            </select>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('products.components.search_placeholder')"
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
            :description="t('products.components.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
