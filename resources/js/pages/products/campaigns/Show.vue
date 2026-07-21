<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Play, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import { Button } from '@/components/ui/button';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    activate as activateCampaign,
    destroy,
    edit as editCampaign,
    index as campaignsIndex,
    show as campaignsShow,
} from '@/routes/products/campaigns';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

type CampaignTarget = {
    id: number;
    deployment_id: number;
    customer_name: string;
    environment: string;
    version_number: string | null;
    status: string;
    notified_at: string | null;
    acknowledged_at: string | null;
    confirmed_at: string | null;
    notification_note: string | null;
};

type CampaignDetail = {
    id: number;
    title: string;
    status: string;
    target_version_id: number;
    target_version_number: string | null;
    product_vulnerability_id: number | null;
    vulnerability_title: string | null;
    notes: string | null;
    started_at: string | null;
    completed_at: string | null;
    created_by: number | null;
    targets: CampaignTarget[];
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    campaign: CampaignDetail;
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.campaigns.index_title',
        href: campaignsIndex(props.product.id),
    },
    {
        title: props.campaign.title,
        href: campaignsShow({
            product: props.product.id,
            campaign: props.campaign.id,
        }),
    },
]);

const showDeleteDialog = ref(false);
const showActivateDialog = ref(false);

const isDraft = computed(() => props.campaign.status === 'draft');

const statusLabel = (value: string): string => {
    const key = `products.campaigns.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const targetStatusLabel = (value: string): string => {
    const key = `products.campaigns.target_statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const environmentLabel = (value: string): string => {
    const key = `products.deployments.environments.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const formatDate = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleString();
};

const confirmActivate = (): void => {
    showActivateDialog.value = false;
    router.post(
        activateCampaign({
            product: props.product.id,
            campaign: props.campaign.id,
        }).url,
    );
};

const confirmDelete = (): void => {
    showDeleteDialog.value = false;
    router.delete(
        destroy({
            product: props.product.id,
            campaign: props.campaign.id,
        }).url,
    );
};
</script>

<template>
    <Head :title="campaign.title" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">{{ campaign.title }}</h1>
                <p class="text-sm text-muted-foreground">
                    {{ statusLabel(campaign.status) }}
                    <span v-if="campaign.target_version_number">
                        · {{ t('products.campaigns.fields.target_version') }}:
                        {{ campaign.target_version_number }}
                    </span>
                </p>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button as-child variant="outline">
                    <Link :href="campaignsIndex(product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <template v-if="canManage && isDraft">
                    <Button as-child variant="outline">
                        <Link
                            :href="
                                editCampaign({
                                    product: product.id,
                                    campaign: campaign.id,
                                })
                            "
                        >
                            <Pencil class="h-4 w-4" />
                            {{ t('common.edit') }}
                        </Link>
                    </Button>
                    <Button @click="showActivateDialog = true">
                        <Play class="h-4 w-4" />
                        {{ t('products.campaigns.activate') }}
                    </Button>
                    <Button
                        variant="destructive"
                        @click="showDeleteDialog = true"
                    >
                        <Trash2 class="h-4 w-4" />
                        {{ t('common.delete') }}
                    </Button>
                </template>
            </div>
        </div>

        <div class="grid gap-4 rounded-lg border p-6 sm:grid-cols-2">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.campaigns.fields.target_version') }}
                </p>
                <p class="font-medium">
                    {{ campaign.target_version_number ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.campaigns.fields.vulnerability') }}
                </p>
                <p class="font-medium">
                    {{ campaign.vulnerability_title ?? '—' }}
                </p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.campaigns.fields.started_at') }}
                </p>
                <p class="font-medium">{{ formatDate(campaign.started_at) }}</p>
            </div>
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.campaigns.columns.targets') }}
                </p>
                <p class="font-medium">{{ campaign.targets.length }}</p>
            </div>
            <div v-if="campaign.notes" class="sm:col-span-2">
                <p class="text-sm text-muted-foreground">
                    {{ t('products.campaigns.fields.notes') }}
                </p>
                <p class="whitespace-pre-wrap">{{ campaign.notes }}</p>
            </div>
        </div>

        <div class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ t('products.campaigns.targets_title') }}
            </h2>
            <p v-if="isDraft" class="text-sm text-muted-foreground">
                {{ t('products.campaigns.targets_draft_hint') }}
            </p>
            <p
                v-else-if="campaign.targets.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('products.campaigns.targets_empty') }}
            </p>
            <div v-else class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left">
                        <tr>
                            <th class="px-4 py-2 font-medium">
                                {{ t('products.campaigns.columns.customer') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{
                                    t('products.campaigns.columns.environment')
                                }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{
                                    t(
                                        'products.campaigns.columns.current_version',
                                    )
                                }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ t('products.campaigns.columns.status') }}
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="target in campaign.targets"
                            :key="target.id"
                            class="border-t"
                        >
                            <td class="px-4 py-2 font-medium">
                                {{ target.customer_name }}
                            </td>
                            <td class="px-4 py-2">
                                {{ environmentLabel(target.environment) }}
                            </td>
                            <td class="px-4 py-2">
                                {{ target.version_number ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                {{ targetStatusLabel(target.status) }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.campaigns.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="showDeleteDialog = false"
        />

        <AppAlertDialog
            v-model:open="showActivateDialog"
            :title="t('products.campaigns.confirm_activate_title')"
            :description="t('products.campaigns.confirm_activate')"
            variant="default"
            :confirm-label="t('products.campaigns.activate')"
            @confirm="confirmActivate"
            @cancel="showActivateDialog = false"
        />
    </div>
</template>
