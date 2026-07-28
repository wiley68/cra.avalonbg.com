<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { FileDown, Loader2, Plus } from '@lucide/vue';
import type { SortingState } from '@tanstack/vue-table';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import DataTable from '@/components/DataTable.vue';
import EncryptedExportDialog from '@/components/exports/EncryptedExportDialog.vue';
import OptionInfoTooltip from '@/components/OptionInfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { useApiTable } from '@/composables/useApiTable';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { downloadEncryptedExport } from '@/lib/encryptedExport';
import { index as usersApiIndex } from '@/routes/internal/users';
import { create, destroy, exportMethod } from '@/routes/users';
import { createUserColumnTitleMap, createUserColumns } from './columns';
import type { UserListItem } from './columns';
import { index as usersIndex } from '@/routes/users';
import { edit as editBilling } from '@/routes/settings/billing';

const organizationRoleSlugs = [
    'organization_owner',
    'product_owner',
    'security_owner',
    'developer',
    'compliance_reviewer',
    'release_approver',
    'auditor',
    'external_consultant',
    'read_only',
] as const;

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type SeatQuota = {
    plan: string;
    billing_status: string;
    max_seats: number | null;
    used: number;
    can_create: boolean;
};

const props = defineProps<{
    organization: OrganizationSummary;
    seatQuota: SeatQuota;
    canManage: boolean;
}>();

const { t } = useTranslations();

const billingStatus = computed(() => props.seatQuota.billing_status);

const isPendingPayment = computed(
    () => billingStatus.value === 'pending_payment',
);
const isPastDue = computed(() => billingStatus.value === 'past_due');
const isCancelled = computed(() => billingStatus.value === 'cancelled');
const showBillingLink = computed(
    () => isPendingPayment.value || isPastDue.value || isCancelled.value,
);

const quotaLabel = computed(() => {
    if (isPendingPayment.value) {
        return t('users.plan_pending_payment');
    }

    if (isPastDue.value) {
        return t('users.plan_past_due');
    }

    if (isCancelled.value) {
        return t('users.plan_cancelled');
    }

    const planName = t(`billing.plans.${props.seatQuota.plan}`);

    if (props.seatQuota.max_seats === null) {
        return t('users.plan_quota_unlimited', {
            used: String(props.seatQuota.used),
            plan: planName,
        });
    }

    return t('users.plan_quota', {
        used: String(props.seatQuota.used),
        max: String(props.seatQuota.max_seats),
        plan: planName,
    });
});

const canCreateUser = computed(
    () => props.canManage && props.seatQuota.can_create,
);

const createDisabledTitle = computed(() => {
    if (isPendingPayment.value) {
        return t('users.create_disabled_pending');
    }

    if (isPastDue.value) {
        return t('users.create_disabled_past_due');
    }

    if (isCancelled.value) {
        return t('users.create_disabled_cancelled');
    }

    return t('users.create_disabled_limit');
});

const roleHelpItems = computed(() =>
    organizationRoleSlugs.map((slug) => ({
        label: t(`roles.${slug}`),
        value: t(`roles.descriptions.${slug}`),
    })),
);

usePageBreadcrumbs(() => [{ titleKey: 'nav.users', href: usersIndex() }]);

const showDeleteDialog = ref(false);
const userToDelete = ref<number | null>(null);
const showExportDialog = ref(false);
const isExporting = ref(false);

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
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            void fetch();
        },
        onError: (errors) => {
            const message = Object.values(errors)[0];
            toast.error(
                typeof message === 'string'
                    ? message
                    : t('users.errors.cannot_delete_self'),
            );
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

const handleExport = async (
    password: string,
    passwordConfirmation: string,
): Promise<void> => {
    isExporting.value = true;

    try {
        const result = await downloadEncryptedExport(
            exportMethod().url,
            password,
            passwordConfirmation,
            {},
            {
                invalid: t('users.export.invalid'),
                error: t('users.export.error'),
            },
        );

        if (!result.ok) {
            toast.error(result.message);

            return;
        }

        showExportDialog.value = false;
        toast.success(t('users.export.success', { filename: result.filename }));
    } catch {
        toast.error(t('users.export.error'));
    } finally {
        isExporting.value = false;
    }
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
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">
                        {{ t('users.title') }}
                    </h1>
                    <OptionInfoTooltip
                        :items="roleHelpItems"
                        side="bottom"
                        content-class="max-w-md max-h-80 overflow-y-auto"
                    />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('users.subtitle') }} — {{ props.organization.name }}
                </p>
                <p class="mt-1 text-xs text-muted-foreground">
                    {{ quotaLabel }}
                    <template v-if="showBillingLink">
                        —
                        <Link
                            :href="editBilling()"
                            class="underline underline-offset-2 hover:text-foreground"
                        >
                            {{ t('billing.usage.upgrade') }}
                        </Link>
                    </template>
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button
                    variant="outline"
                    :disabled="isExporting"
                    @click="showExportDialog = true"
                >
                    <Loader2 v-if="isExporting" class="h-4 w-4 animate-spin" />
                    <FileDown v-else class="h-4 w-4" />
                    {{
                        isExporting
                            ? t('users.export.exporting')
                            : t('users.export.button')
                    }}
                </Button>
                <Button v-if="canCreateUser" as-child>
                    <Link
                        :href="create()"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('users.create') }}
                    </Link>
                </Button>
                <Button
                    v-else-if="canManage"
                    disabled
                    :title="createDisabledTitle"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('users.create') }}
                </Button>
            </div>
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

        <EncryptedExportDialog
            v-model:open="showExportDialog"
            :loading="isExporting"
            i18n-prefix="users.export"
            @confirm="handleExport"
        />
    </div>
</template>
