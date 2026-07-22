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
import { index as policiesApiIndex } from '@/routes/internal/policies';
import { create, destroy, index as policiesIndex } from '@/routes/policies';
import {
    createPolicyColumnTitleMap,
    createPolicyColumns,
    type PolicyListItem,
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
        policy_type: string | null;
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [{ titleKey: 'nav.policies', href: policiesIndex() }]);

const showDeleteDialog = ref(false);
const policyToDelete = ref<number | null>(null);
const policyTypeFilter = ref(props.filters?.policy_type ?? null);

const { rows, pagination, loading, search, fetch } =
    useApiTable<PolicyListItem>({
        endpoint: policiesApiIndex().url,
        initial: {
            page: 1,
            rowsPerPage: 10,
            sortBy: 'updated_at',
            descending: true,
            search: '',
        },
        getExtraParams: () => {
            const params: Record<string, string> = {};

            if (policyTypeFilter.value) {
                params.policy_type = policyTypeFilter.value;
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

const columnTitleMap = computed(() => createPolicyColumnTitleMap(t));

const activeTypeLabel = computed(() => {
    if (!policyTypeFilter.value) {
        return null;
    }

    const key = `policies.types.${policyTypeFilter.value}`;
    const translated = t(key);

    return translated === key ? policyTypeFilter.value : translated;
});

const requestDeletePolicy = (policyId: number): void => {
    policyToDelete.value = policyId;
    showDeleteDialog.value = true;
};

const columns = computed(() =>
    createPolicyColumns({
        t,
        canManage: props.canManage,
        onDelete: requestDeletePolicy,
    }),
);

const cancelDelete = (): void => {
    policyToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (policyToDelete.value === null) {
        return;
    }

    const policyId = policyToDelete.value;
    policyToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(policyId).url, {
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

const clearTypeFilter = () => {
    router.get(
        policiesIndex().url,
        {},
        { replace: true, preserveScroll: true },
    );
};

onMounted(() => {
    void fetch();
});
</script>

<template>
    <Head :title="t('policies.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('policies.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('policies.subtitle') }}
                </p>
            </div>

            <Button v-if="canManage" as-child>
                <Link :href="create()" class="inline-flex items-center gap-2">
                    <Plus class="h-4 w-4" />
                    {{ t('policies.create') }}
                </Link>
            </Button>
        </div>

        <div
            v-if="activeTypeLabel"
            class="flex flex-wrap items-center justify-between gap-3 rounded-lg border px-4 py-3 text-sm"
        >
            <p>
                {{
                    t('policies.filter_type_active', {
                        type: activeTypeLabel,
                    })
                }}
            </p>
            <Button
                type="button"
                variant="outline"
                size="sm"
                @click="clearTypeFilter"
            >
                <X class="h-4 w-4" />
                {{ t('policies.clear_type_filter') }}
            </Button>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('policies.search_placeholder')"
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
            :description="t('policies.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
