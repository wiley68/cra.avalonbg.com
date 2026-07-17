<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { Plus } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import {
    edit as editOrganization,
    index as organizationsIndex,
} from '@/routes/admin/organizations';
import { create } from '@/routes/admin/organizations/users';
import {
    createOrganizationUserColumnTitleMap,
    createOrganizationUserColumns,
} from './columns';
import type { OrganizationUserListItem } from './columns';
import { index as organizationUsersApiIndex } from '@/routes/admin/internal/organizations/users';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
}>();

const { t } = useTranslations();

const { rows, pagination, loading, search, fetch } =
    useApiTable<OrganizationUserListItem>({
        endpoint: organizationUsersApiIndex(props.organization.id).url,
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

const columnTitleMap = computed(() => createOrganizationUserColumnTitleMap(t));
const columns = computed(() =>
    createOrganizationUserColumns(t, props.organization.id),
);

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
    <Head :title="t('admin.users.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    <Link
                        :href="organizationsIndex()"
                        class="hover:underline"
                        >{{ t('nav.organizations') }}</Link
                    >
                    /
                    <Link
                        :href="editOrganization(props.organization.id)"
                        class="hover:underline"
                        >{{ props.organization.name }}</Link
                    >
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('admin.users.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('admin.users.subtitle') }}
                </p>
            </div>

            <Button as-child>
                <Link
                    :href="create(props.organization.id)"
                    class="inline-flex items-center gap-2"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('admin.users.create') }}
                </Link>
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('admin.users.search_placeholder')"
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
