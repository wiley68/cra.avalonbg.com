<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref, watch } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import { index as packagesApiIndex } from '@/routes/internal/products/technical-documentation';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    create as createPackage,
    destroy as destroyPackage,
    index as packagesIndex,
} from '@/routes/products/technical-documentation';
import {
    createTechnicalDocumentationColumnTitleMap,
    createTechnicalDocumentationColumns,
    type TechnicalDocumentationListItem,
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

type VersionOption = { id: number; version_number: string };

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    versions: VersionOption[];
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.technical_documentation.index_title',
        href: packagesIndex(props.product.id),
    },
]);
const { backHref } = useProductModuleBack(props.product.id);

const showDeleteDialog = ref(false);
const packageToDelete = ref<number | null>(null);
const versionFilter = ref('__all__');

const { rows, pagination, loading, search, fetch } =
    useApiTable<TechnicalDocumentationListItem>({
        endpoint: packagesApiIndex(props.product.id).url,
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
        getExtraParams: (): Record<string, string> => {
            if (versionFilter.value === '__all__') {
                return {};
            }

            if (versionFilter.value === '__none__') {
                return { product_wide: '1' };
            }

            return { product_version_id: versionFilter.value };
        },
    });

watch(versionFilter, () => {
    pagination.value.page = 1;
    void fetch();
});

const totalPages = computed(() =>
    Math.max(
        1,
        Math.ceil(pagination.value.rowsNumber / pagination.value.rowsPerPage),
    ),
);

const columnTitleMap = computed(() =>
    createTechnicalDocumentationColumnTitleMap(t),
);

const requestDelete = (packageId: number): void => {
    packageToDelete.value = packageId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createTechnicalDocumentationColumns({
        t,
        productId: props.product.id,
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

    const id = packageToDelete.value;
    packageToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyPackage({
            product: props.product.id,
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
    <Head :title="t('products.technical_documentation.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.technical_documentation.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.technical_documentation.subtitle') }}
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
                        :href="createPackage(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.technical_documentation.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <Select
                :model-value="versionFilter"
                @update:model-value="
                    (value) => {
                        versionFilter =
                            value === undefined || value === null
                                ? '__all__'
                                : String(value);
                    }
                "
            >
                <SelectTrigger class="w-55">
                    <SelectValue
                        :placeholder="
                            t('products.technical_documentation.filter_version')
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="__all__">
                        {{
                            t(
                                'products.technical_documentation.filter_all_versions',
                            )
                        }}
                    </SelectItem>
                    <SelectItem value="__none__">
                        {{
                            t(
                                'products.technical_documentation.filter_product_wide',
                            )
                        }}
                    </SelectItem>
                    <SelectItem
                        v-for="version in versions"
                        :key="version.id"
                        :value="String(version.id)"
                    >
                        {{ version.version_number }}
                    </SelectItem>
                </SelectContent>
            </Select>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="
                t('products.technical_documentation.search_placeholder')
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
