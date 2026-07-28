<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { Loader2, Plus } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import OptionInfoTooltip from '@/components/OptionInfoTooltip.vue';
import ProductCard from '@/components/products/ProductCard.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useApiLoadMore } from '@/composables/useApiLoadMore';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import type { ProductListItem } from '@/pages/products/columns';
import { index as productsApiIndex } from '@/routes/internal/products';
import { create, destroy } from '@/routes/products';
import { index as productsIndex } from '@/routes/products';
import { edit as editBilling } from '@/routes/settings/billing';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductQuota = {
    plan: string;
    billing_status: string;
    max_products: number | null;
    used: number;
    can_create: boolean;
};

const props = defineProps<{
    organization: OrganizationSummary;
    canManage: boolean;
    productQuota: ProductQuota;
}>();

const { t } = useTranslations();

const billingStatus = computed(() => props.productQuota.billing_status);

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
        return t('products.plan_pending_payment');
    }

    if (isPastDue.value) {
        return t('products.plan_past_due');
    }

    if (isCancelled.value) {
        return t('products.plan_cancelled');
    }

    const planName = t(`billing.plans.${props.productQuota.plan}`);

    if (props.productQuota.max_products === null) {
        return t('products.plan_quota_unlimited', {
            used: String(props.productQuota.used),
            plan: planName,
        });
    }

    return t('products.plan_quota', {
        used: String(props.productQuota.used),
        max: String(props.productQuota.max_products),
        plan: planName,
    });
});

const canCreateProduct = computed(
    () => props.canManage && props.productQuota.can_create,
);

const createDisabledTitle = computed(() => {
    if (isPendingPayment.value) {
        return t('products.create_disabled_pending');
    }

    if (isPastDue.value) {
        return t('products.create_disabled_past_due');
    }

    if (isCancelled.value) {
        return t('products.create_disabled_cancelled');
    }

    return t('products.create_disabled_limit');
});

const moduleColorHelpItems = computed(() => [
    {
        label: t('products.module_colors.critical_label'),
        value: t('products.module_colors.critical'),
    },
    {
        label: t('products.module_colors.attention_label'),
        value: t('products.module_colors.attention'),
    },
    {
        label: t('products.module_colors.complete_label'),
        value: t('products.module_colors.complete'),
    },
    {
        label: t('products.module_colors.empty_label'),
        value: t('products.module_colors.empty'),
    },
]);

usePageBreadcrumbs(() => [{ titleKey: 'nav.products', href: productsIndex() }]);

const showDeleteDialog = ref(false);
const productToDelete = ref<number | null>(null);

const { rows, loading, loadingMore, search, total, hasMore, fetch, loadMore } =
    useApiLoadMore<ProductListItem>({
        endpoint: productsApiIndex().url,
        perPage: 12,
        sortBy: 'name',
        sortDesc: false,
        onError: (message) => {
            toast.error(message);
        },
        autoload: false,
    });

const requestDeleteProduct = (productId: number): void => {
    productToDelete.value = productId;
    showDeleteDialog.value = true;
};

const cancelDelete = (): void => {
    productToDelete.value = null;
    showDeleteDialog.value = false;
};

const confirmDelete = (): void => {
    if (productToDelete.value === null) {
        return;
    }

    const productId = productToDelete.value;
    productToDelete.value = null;
    showDeleteDialog.value = false;

    router.delete(destroy(productId).url, {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
            void fetch();
        },
    });
};

onMounted(() => {
    void fetch();
});
</script>

<template>
    <Head :title="t('products.index_title')" />

    <div class="space-y-6">
        <div
            class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"
        >
            <div>
                <div class="flex items-center gap-2">
                    <h1 class="text-xl font-semibold">
                        {{ t('products.title') }}
                        <span
                            v-if="!loading"
                            class="font-normal text-muted-foreground"
                            >({{ total }})</span
                        >
                    </h1>
                    <OptionInfoTooltip
                        :items="moduleColorHelpItems"
                        side="bottom"
                        content-class="max-w-md"
                    />
                </div>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.subtitle') }} —
                    {{ props.organization.name }}
                </p>
                <p class="text-xs text-muted-foreground">
                    {{ quotaLabel }}
                    <template v-if="showBillingLink">
                        —
                        <Link
                            :href="editBilling()"
                            class="underline underline-offset-2 hover:text-foreground"
                        >
                            {{ t('products.view_billing') }}
                        </Link>
                    </template>
                </p>
            </div>

            <div
                class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-center"
            >
                <Input
                    v-model="search"
                    type="search"
                    :placeholder="t('products.search_placeholder')"
                    class="w-full sm:w-72"
                />
                <Button v-if="canCreateProduct" as-child class="shrink-0">
                    <Link
                        :href="create()"
                        class="inline-flex items-center gap-2"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.create') }}
                    </Link>
                </Button>
                <Button
                    v-else-if="canManage"
                    class="shrink-0"
                    disabled
                    :title="createDisabledTitle"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('products.create') }}
                </Button>
            </div>
        </div>

        <div
            v-if="loading"
            class="flex items-center justify-center gap-2 py-16 text-muted-foreground"
        >
            <Loader2 class="size-5 animate-spin" />
            {{ t('common.table.loading') }}
        </div>

        <p
            v-else-if="rows.length === 0"
            class="py-16 text-center text-muted-foreground"
        >
            {{ t('products.empty') }}
        </p>

        <template v-else>
            <div
                class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4"
            >
                <ProductCard
                    v-for="product in rows"
                    :key="product.id"
                    :product="product"
                    :can-manage="canManage"
                    @delete="requestDeleteProduct"
                />
            </div>

            <div v-if="hasMore" class="flex justify-center pt-2">
                <Button
                    variant="outline"
                    class="min-w-40"
                    :disabled="loadingMore"
                    @click="loadMore"
                >
                    <Loader2
                        v-if="loadingMore"
                        class="mr-2 size-4 animate-spin"
                    />
                    {{ t('products.show_more') }}
                </Button>
            </div>
        </template>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="cancelDelete"
        />
    </div>
</template>
