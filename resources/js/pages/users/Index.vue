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
import { index as usersApiIndex } from '@/routes/internal/users';
import { create, destroy } from '@/routes/users';
import { createUserColumnTitleMap, createUserColumns } from './columns';
import type { UserListItem } from './columns';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
}>();

const { t } = useTranslations();

const showDeleteDialog = ref(false);
const userToDelete = ref<number | null>(null);

const { rows, pagination, loading, search, fetch } = useApiTable<UserListItem>({
    endpoint: usersApiIndex().url,
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

const columnTitleMap = computed(() => createUserColumnTitleMap(t));

const requestDeleteUser = (userId: number): void => {
    userToDelete.value = userId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createUserColumns({
        t,
        onDelete: requestDeleteUser,
    }),
);

const cancelDelete = (): void => {
    userToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (userToDelete.value === null) {
        return;
    }

    const userId = userToDelete.value;
    userToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(userId).url, {
        preserveScroll: true,
        onSuccess: async () => {
            rows.value = rows.value.filter((row) => row.id !== userId);
            pagination.value.rowsNumber = Math.max(
                0,
                pagination.value.rowsNumber - 1,
            );

            if (rows.value.length === 0 && pagination.value.page > 1) {
                pagination.value.page--;
                await fetch();
            }
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
    <Head :title="t('users.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ t('users.title') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('users.subtitle') }} — {{ props.organization.name }}
                </p>
            </div>

            <Button as-child>
                <Link :href="create()" class="inline-flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('users.create') }}
                </Link>
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('users.search_placeholder')"
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
            :description="t('users.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
