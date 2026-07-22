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
import { index as instructionsApiIndex } from '@/routes/internal/products/security-instructions';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    create as createInstruction,
    destroy as destroyInstruction,
    index as instructionsIndex,
} from '@/routes/products/security-instructions';
import {
    createUserSecurityInstructionColumnTitleMap,
    createUserSecurityInstructionColumns,
    type UserSecurityInstructionListItem,
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
        titleKey: 'products.user_security_instructions.index_title',
        href: instructionsIndex(props.product.id),
    },
]);
const { backHref } = useProductModuleBack(props.product.id);

const showDeleteDialog = ref(false);
const instructionToDelete = ref<number | null>(null);
const versionFilter = ref('__all__');

const { rows, pagination, loading, search, fetch } =
    useApiTable<UserSecurityInstructionListItem>({
        endpoint: instructionsApiIndex(props.product.id).url,
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
    createUserSecurityInstructionColumnTitleMap(t),
);

const requestDelete = (instructionId: number): void => {
    instructionToDelete.value = instructionId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createUserSecurityInstructionColumns({
        t,
        productId: props.product.id,
        canManage: props.canManage,
        onDelete: requestDelete,
    }),
);

const cancelDelete = (): void => {
    instructionToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (instructionToDelete.value === null) {
        return;
    }

    const id = instructionToDelete.value;
    instructionToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(
        destroyInstruction({
            product: props.product.id,
            instruction: id,
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
    <Head :title="t('products.user_security_instructions.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.user_security_instructions.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.user_security_instructions.subtitle') }}
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
                        :href="createInstruction(props.product.id)"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.user_security_instructions.create') }}
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
                            t(
                                'products.user_security_instructions.filter_version',
                            )
                        "
                    />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="__all__">
                        {{
                            t(
                                'products.user_security_instructions.filter_all_versions',
                            )
                        }}
                    </SelectItem>
                    <SelectItem value="__none__">
                        {{
                            t(
                                'products.user_security_instructions.filter_product_wide',
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
                t('products.user_security_instructions.search_placeholder')
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
            :description="
                t('products.user_security_instructions.confirm_delete')
            "
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
