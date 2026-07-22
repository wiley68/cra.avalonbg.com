<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, Upload } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    create,
    destroy,
    importMethod as customersImport,
    index as customersIndex,
} from '@/routes/customers';
import { index as customersApiIndex } from '@/routes/internal/customers';
import {
    createCustomerColumnTitleMap,
    createCustomerColumns,
    type CustomerListItem,
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
    { titleKey: 'nav.customers', href: customersIndex() },
]);

const showDeleteDialog = ref(false);
const customerToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<CustomerListItem>({
        endpoint: customersApiIndex().url,
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

const columnTitleMap = computed(() => createCustomerColumnTitleMap(t));

const requestDeleteCustomer = (customerId: number): void => {
    customerToDelete.value = customerId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createCustomerColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDeleteCustomer,
    }),
);

const cancelDelete = (): void => {
    customerToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (customerToDelete.value === null) {
        return;
    }

    const customerId = customerToDelete.value;
    customerToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(customerId).url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            void fetch();
        },
    });
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
    <Head :title="t('customers.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('customers.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('customers.subtitle') }} —
                    {{ props.organization.name }}
                </p>
            </div>

            <div v-if="canManage" class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link
                        :href="customersImport()"
                        class="inline-flex items-center gap-2"
                    >
                        <Upload class="h-4 w-4" />
                        {{ t('customers.import') }}
                    </Link>
                </Button>
                <Button as-child>
                    <Link
                        :href="create()"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('customers.create') }}
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
            :search-placeholder="t('customers.search_placeholder')"
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
            :description="t('customers.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
