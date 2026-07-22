<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, RefreshCw } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { create, destroy, refreshStarter } from '@/routes/controls';
import { index as controlsApiIndex } from '@/routes/internal/controls';
import { createControlColumnTitleMap, createControlColumns } from './columns';
import type { ControlListItem } from './columns';
import { index as controlsIndex } from '@/routes/controls';

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

usePageBreadcrumbs(() => [{ titleKey: 'nav.controls', href: controlsIndex() }]);

const showDeleteDialog = ref(false);
const controlToDelete = ref<number | null>(null);
const refreshingStarter = ref(false);

const refreshStarterControls = (): void => {
    refreshingStarter.value = true;

    router.post(
        refreshStarter().url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                refreshingStarter.value = false;
                void fetch();
            },
        },
    );
};

const { rows, pagination, loading, search, fetch } =
    useApiTable<ControlListItem>({
        endpoint: controlsApiIndex().url,
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

const columnTitleMap = computed(() => createControlColumnTitleMap(t));

const requestDeleteControl = (controlId: number): void => {
    controlToDelete.value = controlId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createControlColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDeleteControl,
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

    const controlId = controlToDelete.value;
    controlToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(controlId).url, {
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
    <Head :title="t('controls.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ t('controls.title') }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('controls.subtitle') }} —
                    {{ props.organization.name }}
                </p>
            </div>

            <div
                v-if="canManage"
                class="flex flex-wrap items-center justify-end gap-2"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="refreshingStarter"
                    :title="t('controls.refresh_starter_help')"
                    @click="refreshStarterControls"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': refreshingStarter }"
                    />
                    {{ t('controls.refresh_starter') }}
                </Button>
                <Button as-child>
                    <Link
                        :href="create()"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('controls.create') }}
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
            :search-placeholder="t('controls.search_placeholder')"
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
            :description="t('controls.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
