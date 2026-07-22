<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardList, Plus } from '@lucide/vue';
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
import { createEvidenceColumnTitleMap, createEvidenceColumns } from './columns';
import type { EvidenceListItem } from './columns';
import { create as packagesCreate } from '@/routes/auditor/packages';
import { index as evidenceApiIndex } from '@/routes/internal/products/evidence';
import {
    create as createEvidence,
    destroy as destroyEvidence,
} from '@/routes/products/evidence';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { index as evidenceIndex } from '@/routes/products/evidence';

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
    canCreateReviewPackage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.evidence.index_title',
        href: evidenceIndex(props.product.id),
    },
]);
const { backHref } = useProductModuleBack(props.product.id);

const showDeleteDialog = ref(false);
const evidenceToDelete = ref<number | null>(null);
const selectedIds = ref<number[]>([]);

const { rows, pagination, loading, search, fetch } =
    useApiTable<EvidenceListItem>({
        endpoint: evidenceApiIndex(props.product.id).url,
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

const columnTitleMap = computed(() => createEvidenceColumnTitleMap(t));

const createPackageHref = computed(() =>
    packagesCreate.url({
        query: {
            product_id: props.product.id,
            evidence_ids: selectedIds.value.join(','),
        },
    }),
);

const requestDelete = (evidenceId: number): void => {
    evidenceToDelete.value = evidenceId;
    showDeleteDialog.value = true;
};

const toggleSelect = (evidenceId: number, checked: boolean): void => {
    if (checked) {
        if (!selectedIds.value.includes(evidenceId)) {
            selectedIds.value = [...selectedIds.value, evidenceId];
        }

        return;
    }

    selectedIds.value = selectedIds.value.filter((id) => id !== evidenceId);
};

const columns = computed(() =>
    createEvidenceColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
        canCreateReviewPackage: props.canCreateReviewPackage,
        selectedIds: selectedIds.value,
        onToggleSelect: toggleSelect,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    evidenceToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (evidenceToDelete.value === null) {
        return;
    }

    const id = evidenceToDelete.value;
    evidenceToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyEvidence({
            product: props.product.id,
            evidence: id,
        }).url,
        {
            preserveScroll: true,
            onSuccess: async () => {
                rows.value = rows.value.filter((row) => row.id !== id);
                selectedIds.value = selectedIds.value.filter(
                    (selectedId) => selectedId !== id,
                );
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
    <Head :title="t('products.evidence.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.evidence.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.evidence.subtitle') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button
                    v-if="canCreateReviewPackage && selectedIds.length > 0"
                    as-child
                >
                    <Link
                        :href="createPackageHref"
                        class="inline-flex items-center gap-2"
                    >
                        <ClipboardList class="h-4 w-4" />
                        {{
                            t('products.evidence.create_review_package', {
                                count: String(selectedIds.length),
                            })
                        }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child>
                    <Link
                        :href="createEvidence(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.evidence.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p v-if="canCreateReviewPackage" class="text-sm text-muted-foreground">
            {{ t('products.evidence.select_for_package_help') }}
        </p>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('products.evidence.search_placeholder')"
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
            :description="t('products.evidence.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
