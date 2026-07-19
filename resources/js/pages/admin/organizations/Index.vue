<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { index as organizationsApiIndex } from '@/routes/admin/internal/organizations';
import { create, destroy } from '@/routes/admin/organizations';
import {
    createOrganizationColumnTitleMap,
    createOrganizationColumns,
} from './columns';
import type { OrganizationListItem } from './columns';
import { index as organizationsIndex } from '@/routes/admin/organizations';

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.organizations', href: organizationsIndex() },
]);

const showDeleteDialog = ref(false);
const organizationToDelete = ref<OrganizationListItem | null>(null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<OrganizationListItem>({
        endpoint: organizationsApiIndex().url,
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

const requestDelete = (organization: OrganizationListItem): void => {
    organizationToDelete.value = organization;
    showDeleteDialog.value = true;
};

const columnTitleMap = computed(() => createOrganizationColumnTitleMap(t));
const columns = computed(() => createOrganizationColumns(t, requestDelete));

const cancelDelete = (): void => {
    organizationToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (organizationToDelete.value === null) {
        return;
    }

    const organizationId = organizationToDelete.value.id;
    organizationToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(organizationId).url, {
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
    <Head :title="t('admin.organizations.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('admin.organizations.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.organizations.subtitle') }}
                </p>
            </div>

            <Button as-child>
                <Link :href="create()" class="inline-flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('admin.organizations.create') }}
                </Link>
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('admin.organizations.search_placeholder')"
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
            :title="t('admin.organizations.confirm_delete_title')"
            :description="t('admin.organizations.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
