<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import DataTable from '@/components/DataTable.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import {
    createAuditLogColumnTitleMap,
    createAuditLogColumns,
    formatAuditDetailValue,
    getAuditFieldLabel,
} from './columns';
import type { AuditLog, AuditLogDetail } from './columns';
import { index as auditLogsApiIndex } from '@/routes/admin/internal/audit-logs';

const { t } = useTranslations();

const showDetailsDialog = ref(false);
const selectedLog = ref<AuditLog | null>(null);

const { rows, pagination, loading, search, fetch } = useApiTable<AuditLog>({
    endpoint: auditLogsApiIndex().url,
    initial: {
        page: 1,
        rowsPerPage: 10,
        sortBy: 'occurred_at',
        descending: true,
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

const columnTitleMap = computed(() => createAuditLogColumnTitleMap(t));

const handleViewDetails = (log: AuditLog) => {
    selectedLog.value = log;
    showDetailsDialog.value = true;
};

const columns = computed(() =>
    createAuditLogColumns({
        t,
        onViewDetails: handleViewDetails,
    }),
);

const handlePaginationChange = (page: number, pageSize: number) => {
    pagination.value.page = page;
    pagination.value.rowsPerPage = pageSize;
    void fetch();
};

const handleSortingChange = (sorting: SortingState) => {
    const primary = sorting[0];

    pagination.value.sortBy = primary?.id ?? 'occurred_at';
    pagination.value.descending = primary?.desc ?? true;
    void fetch();
};

const updateSearch = (value: string) => {
    search.value = value;
};

const hasChangeValues = (detail: AuditLogDetail) =>
    detail.initial_value !== undefined || detail.final_value !== undefined;

onMounted(() => {
    void fetch();
});
</script>

<template>
    <Head :title="t('audit_logs.title')" />

    <div class="space-y-6">
        <div>
            <h1 class="text-xl font-semibold">
                {{ t('audit_logs.title') }}
            </h1>
            <p class="text-sm text-muted-foreground">
                {{ t('audit_logs.subtitle') }}
            </p>
        </div>

        <DataTable
            :columns="columns"
            :data="rows"
            :loading="loading"
            :search="search"
            :column-title-map="columnTitleMap"
            :search-placeholder="t('audit_logs.search_placeholder')"
            :empty-message="t('audit_logs.empty')"
            :loading-message="t('audit_logs.loading')"
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

        <Dialog
            :open="showDetailsDialog"
            @update:open="(open: boolean) => (showDetailsDialog = open)"
        >
            <DialogContent class="max-h-[85vh] max-w-3xl overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{{
                        t('audit_logs.details_title')
                    }}</DialogTitle>
                    <DialogDescription v-if="selectedLog">
                        {{ selectedLog.event_type_label }} ·
                        {{ selectedLog.occurred_at }} ·
                        {{ selectedLog.user_name }} ·
                        {{ selectedLog.event_source_label }}
                    </DialogDescription>
                </DialogHeader>

                <div v-if="selectedLog" class="space-y-3">
                    <div class="grid gap-2 text-sm sm:grid-cols-2">
                        <div>
                            <span class="text-muted-foreground"
                                >{{ t('audit_logs.result') }}:</span
                            >
                            {{
                                selectedLog.is_success
                                    ? t('audit_logs.success')
                                    : t('audit_logs.failure')
                            }}
                        </div>
                        <div class="truncate" :title="selectedLog.user_email">
                            <span class="text-muted-foreground"
                                >{{ t('common.email') }}:</span
                            >
                            {{ selectedLog.user_email }}
                        </div>
                    </div>

                    <div class="overflow-x-auto rounded-md border">
                        <table class="w-full text-sm">
                            <thead class="bg-muted/50">
                                <tr>
                                    <th class="px-3 py-2 text-left font-medium">
                                        {{ t('audit_logs.field') }}
                                    </th>
                                    <th
                                        v-if="
                                            selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        {{ t('audit_logs.initial_value') }}
                                    </th>
                                    <th
                                        v-if="
                                            selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        {{ t('audit_logs.final_value') }}
                                    </th>
                                    <th
                                        v-if="
                                            !selectedLog.details.some(
                                                hasChangeValues,
                                            )
                                        "
                                        class="px-3 py-2 text-left font-medium"
                                    >
                                        {{ t('audit_logs.value') }}
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="(
                                        detail, index
                                    ) in selectedLog.details"
                                    :key="`${selectedLog.id}-${index}`"
                                    class="border-t"
                                >
                                    <td class="px-3 py-2 align-top">
                                        {{
                                            getAuditFieldLabel(t, detail.field)
                                        }}
                                    </td>
                                    <template v-if="hasChangeValues(detail)">
                                        <td
                                            class="px-3 py-2 align-top break-all"
                                        >
                                            {{
                                                formatAuditDetailValue(
                                                    t,
                                                    detail.field,
                                                    detail.initial_value,
                                                )
                                            }}
                                        </td>
                                        <td
                                            class="px-3 py-2 align-top break-all"
                                        >
                                            {{
                                                formatAuditDetailValue(
                                                    t,
                                                    detail.field,
                                                    detail.final_value,
                                                )
                                            }}
                                        </td>
                                    </template>
                                    <td
                                        v-else
                                        class="px-3 py-2 align-top break-all"
                                    >
                                        {{
                                            formatAuditDetailValue(
                                                t,
                                                detail.field,
                                                detail.value,
                                            )
                                        }}
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </div>
</template>
