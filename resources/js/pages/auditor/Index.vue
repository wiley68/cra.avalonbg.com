<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Plus, X } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as auditorIndex } from '@/routes/auditor';
import {
    create as packagesCreate,
    destroy as packagesDestroy,
} from '@/routes/auditor/packages';
import { index as packagesApiIndex } from '@/routes/internal/auditor/packages';
import {
    createAuditorColumnTitleMap,
    createAuditorColumns,
    type AuditorPackageListItem,
} from './columns';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    canManage: boolean;
    filters?: {
        status: string | null;
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [{ titleKey: 'nav.auditor', href: auditorIndex() }]);

const showDeleteDialog = ref(false);
const packageToDelete = ref<number | null>(null);
const statusFilter = ref(props.filters?.status ?? null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<AuditorPackageListItem>({
        endpoint: packagesApiIndex().url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'updated_at',
            descending: true,
            search: '',
        },
        getExtraParams: () => {
            const params: Record<string, string> = {};

            if (statusFilter.value) {
                params.status = statusFilter.value;
            }

            return params;
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

const columnTitleMap = computed(() => createAuditorColumnTitleMap(t));

const activeStatusLabel = computed(() => {
    if (!statusFilter.value) {
        return null;
    }

    const key = `auditor.statuses.${statusFilter.value}`;
    const translated = t(key);

    return translated === key ? statusFilter.value : translated;
});

const requestDeletePackage = (packageId: number): void => {
    packageToDelete.value = packageId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createAuditorColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDeletePackage,
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

    const packageId = packageToDelete.value;
    packageToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(packagesDestroy(packageId).url, {
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

    pagination.value.sortBy = primary?.id ?? 'updated_at';
    pagination.value.descending = primary?.desc ?? true;
    void fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

const clearStatusFilter = () => {
    router.get(auditorIndex().url, {}, { replace: true, preserveScroll: true });
};

onMounted(() => {
    void fetch();
});
</script>

<template>
    <Head :title="t('auditor.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('auditor.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('auditor.subtitle') }}
                </p>
            </div>

            <Button v-if="canManage" as-child>
                <Link
                    :href="packagesCreate()"
                    class="inline-flex items-center gap-2"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('auditor.create') }}
                </Link>
            </Button>
        </div>

        <div
            v-if="activeStatusLabel"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm"
        >
            <p>
                {{ activeStatusLabel }}
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="clearStatusFilter"
            >
                <X class="h-4 w-4" />
                {{ t('common.cancel') }}
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('auditor.search_placeholder')"
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
            :description="t('auditor.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
