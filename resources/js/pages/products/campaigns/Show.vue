<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    FileDown,
    History,
    Mail,
    Pencil,
    Play,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    activate as activateCampaign,
    destroy,
    edit as editCampaign,
    exportMethod as exportCampaign,
    index as campaignsIndex,
    notify as notifyCampaign,
    show as campaignsShow,
} from '@/routes/products/campaigns';
import { update as updateTarget } from '@/routes/products/campaigns/targets';
import { edit as editProductVulnerability } from '@/routes/products/vulnerabilities';

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

type NotificationEvent = {
    id: number;
    event_type: string;
    channel: string;
    status_before: string | null;
    status_after: string | null;
    body: string | null;
    recipient: string | null;
    created_by: string | null;
    created_at: string;
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
    notification_events: NotificationEvent[];
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

const actionableStatuses = [
    'notified',
    'acknowledged',
    'updated',
    'excepted',
] as const;

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
const showNotifyDialog = ref(false);
const showStatusDialog = ref(false);
const showLogDialog = ref(false);
const selectedTarget = ref<CampaignTarget | null>(null);

const isDraft = computed(() => props.campaign.status === 'draft');
const isActive = computed(() => props.campaign.status === 'active');
const canUpdateTargets = computed(() => props.canManage && isActive.value);

const exportUrl = computed(
    () =>
        exportCampaign({
            product: props.product.id,
            campaign: props.campaign.id,
        }).url,
);

const statusForm = useForm({
    status: 'notified' as string,
    notification_note: '',
});

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

const eventTypeLabel = (value: string): string => {
    const key = `products.campaigns.notification_events.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const eventChannelLabel = (value: string): string => {
    const key = `products.campaigns.notification_events.channels.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const eventSummary = (event: NotificationEvent): string => {
    if (
        event.status_before &&
        event.status_after &&
        event.status_before !== event.status_after
    ) {
        return `${targetStatusLabel(event.status_before)} → ${targetStatusLabel(event.status_after)}`;
    }

    if (event.status_after) {
        return targetStatusLabel(event.status_after);
    }

    return eventTypeLabel(event.event_type);
};

const openLogDialog = (target: CampaignTarget): void => {
    showStatusDialog.value = false;
    selectedTarget.value = target;
    showLogDialog.value = true;
};

const closeLogDialog = (): void => {
    showLogDialog.value = false;

    if (!showStatusDialog.value) {
        selectedTarget.value = null;
    }
};

const openStatusDialog = (target: CampaignTarget): void => {
    showLogDialog.value = false;
    selectedTarget.value = target;
    statusForm.status = actionableStatuses.includes(
        target.status as (typeof actionableStatuses)[number],
    )
        ? target.status
        : 'notified';
    statusForm.notification_note = target.notification_note ?? '';
    statusForm.clearErrors();
    showStatusDialog.value = true;
};

const closeStatusDialog = (): void => {
    showStatusDialog.value = false;

    if (!showLogDialog.value) {
        selectedTarget.value = null;
    }

    statusForm.reset();
};

const submitStatusUpdate = (): void => {
    if (selectedTarget.value === null) {
        return;
    }

    statusForm
        .transform((data) => ({
            ...data,
            notification_note: data.notification_note || null,
        }))
        .put(
            updateTarget({
                product: props.product.id,
                campaign: props.campaign.id,
                target: selectedTarget.value.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeStatusDialog();
                },
            },
        );
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

const confirmNotify = (): void => {
    showNotifyDialog.value = false;
    router.post(
        notifyCampaign({
            product: props.product.id,
            campaign: props.campaign.id,
        }).url,
        {},
        { preserveScroll: true },
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

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
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
                <Button as-child variant="outline">
                    <a :href="exportUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{ t('products.campaigns.export_xlsx') }}
                    </a>
                </Button>
                <Button
                    v-if="canManage && isActive"
                    variant="outline"
                    @click="showNotifyDialog = true"
                >
                    <Mail class="h-4 w-4" />
                    {{ t('products.campaigns.queue_notifications') }}
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
                    <Link
                        v-if="campaign.product_vulnerability_id"
                        :href="
                            editProductVulnerability({
                                product: product.id,
                                vulnerability:
                                    campaign.product_vulnerability_id,
                            })
                        "
                        class="text-primary underline-offset-4 hover:underline"
                    >
                        {{ campaign.vulnerability_title ?? '—' }}
                    </Link>
                    <template v-else>—</template>
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
                    {{ t('products.campaigns.fields.completed_at') }}
                </p>
                <p class="font-medium">
                    {{ formatDate(campaign.completed_at) }}
                </p>
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
                            <th class="px-4 py-2 font-medium">
                                {{ t('products.campaigns.columns.note') }}
                            </th>
                            <th class="px-4 py-2 font-medium">
                                {{ t('common.actions') }}
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
                                <div>
                                    {{ targetStatusLabel(target.status) }}
                                </div>
                                <div
                                    v-if="target.confirmed_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDate(target.confirmed_at) }}
                                </div>
                                <div
                                    v-else-if="target.acknowledged_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDate(target.acknowledged_at) }}
                                </div>
                                <div
                                    v-else-if="target.notified_at"
                                    class="text-xs text-muted-foreground"
                                >
                                    {{ formatDate(target.notified_at) }}
                                </div>
                            </td>
                            <td
                                class="max-w-xs truncate px-4 py-2 text-muted-foreground"
                            >
                                {{ target.notification_note ?? '—' }}
                            </td>
                            <td class="px-4 py-2">
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        @click="openLogDialog(target)"
                                    >
                                        <History class="h-4 w-4" />
                                        {{
                                            t(
                                                'products.campaigns.notification_log',
                                            )
                                        }}
                                        <span
                                            v-if="
                                                target.notification_events
                                                    .length > 0
                                            "
                                            class="text-muted-foreground"
                                        >
                                            ({{
                                                target.notification_events
                                                    .length
                                            }})
                                        </span>
                                    </Button>
                                    <Button
                                        v-if="canUpdateTargets"
                                        variant="outline"
                                        size="sm"
                                        @click="openStatusDialog(target)"
                                    >
                                        {{
                                            t(
                                                'products.campaigns.update_target_status',
                                            )
                                        }}
                                    </Button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <Dialog
            :open="showStatusDialog"
            @update:open="
                (open) => {
                    if (!open) {
                        closeStatusDialog();
                    }
                }
            "
        >
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('products.campaigns.update_target_status') }}
                    </DialogTitle>
                    <DialogDescription>
                        <span v-if="selectedTarget">
                            {{ selectedTarget.customer_name }}
                            ·
                            {{ environmentLabel(selectedTarget.environment) }}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="submitStatusUpdate">
                    <div class="grid gap-2">
                        <Label for="target_status">{{
                            t('products.campaigns.columns.status')
                        }}</Label>
                        <Select v-model="statusForm.status">
                            <SelectTrigger id="target_status" class="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="value in actionableStatuses"
                                    :key="value"
                                    :value="value"
                                >
                                    {{ targetStatusLabel(value) }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="statusForm.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <Label for="notification_note">{{
                            t('products.campaigns.fields.notification_note')
                        }}</Label>
                        <textarea
                            id="notification_note"
                            v-model="statusForm.notification_note"
                            rows="3"
                            :class="textareaClass"
                            :placeholder="
                                t(
                                    'products.campaigns.notification_note_placeholder',
                                )
                            "
                        />
                        <InputError
                            :message="statusForm.errors.notification_note"
                        />
                    </div>

                    <p
                        v-if="statusForm.status === 'updated'"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('products.campaigns.updated_sync_hint') }}
                    </p>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="closeStatusDialog"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button type="submit" :disabled="statusForm.processing">
                            {{ t('common.save') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showLogDialog"
            @update:open="
                (open) => {
                    if (!open) {
                        closeLogDialog();
                    }
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('products.campaigns.notification_log_title') }}
                    </DialogTitle>
                    <DialogDescription>
                        <span v-if="selectedTarget">
                            {{ selectedTarget.customer_name }}
                            ·
                            {{ environmentLabel(selectedTarget.environment) }}
                        </span>
                    </DialogDescription>
                </DialogHeader>

                <div
                    v-if="
                        selectedTarget &&
                        selectedTarget.notification_events.length === 0
                    "
                    class="text-sm text-muted-foreground"
                >
                    {{ t('products.campaigns.notification_log_empty') }}
                </div>

                <div
                    v-else-if="selectedTarget"
                    class="max-h-80 space-y-3 overflow-y-auto"
                >
                    <div
                        v-for="event in selectedTarget.notification_events"
                        :key="event.id"
                        class="rounded-md border px-3 py-2 text-sm"
                    >
                        <div
                            class="flex flex-wrap items-center justify-between gap-2"
                        >
                            <span class="font-medium">
                                {{ eventSummary(event) }}
                            </span>
                            <span class="text-xs text-muted-foreground">
                                {{ formatDate(event.created_at) }}
                            </span>
                        </div>
                        <div
                            class="mt-1 flex flex-wrap gap-x-3 gap-y-1 text-xs text-muted-foreground"
                        >
                            <span>
                                {{ eventTypeLabel(event.event_type) }}
                            </span>
                            <span>
                                {{ eventChannelLabel(event.channel) }}
                            </span>
                            <span v-if="event.recipient">
                                {{ event.recipient }}
                            </span>
                            <span v-if="event.created_by">
                                {{ event.created_by }}
                            </span>
                        </div>
                        <p
                            v-if="event.body"
                            class="mt-2 whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ event.body }}
                        </p>
                    </div>
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="closeLogDialog"
                    >
                        {{ t('common.close') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

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

        <AppAlertDialog
            v-model:open="showNotifyDialog"
            :title="t('products.campaigns.confirm_notify_title')"
            :description="t('products.campaigns.confirm_notify')"
            variant="default"
            :confirm-label="t('products.campaigns.queue_notifications')"
            @confirm="confirmNotify"
            @cancel="showNotifyDialog = false"
        />
    </div>
</template>
