<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Eye, Lock, Save, Share2, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as auditorIndex } from '@/routes/auditor';
import {
    close as packagesClose,
    destroy as packagesDestroy,
    edit as packagesEdit,
    share as packagesShare,
    show as packagesShow,
    update as packagesUpdate,
} from '@/routes/auditor/packages';

type EvidenceOption = {
    id: number;
    title: string;
    type: string;
};

type PackageDetail = {
    id: number;
    title: string;
    status: string;
    notes: string | null;
    product_id: number;
    product_name: string;
    product_slug: string;
    shared_at: string | null;
    closed_at: string | null;
    created_by_name: string | null;
    evidence_ids: number[];
    evidence: EvidenceOption[];
    is_editable: boolean;
};

const props = defineProps<{
    package: PackageDetail;
    evidenceOptions: EvidenceOption[];
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.auditor', href: auditorIndex() },
    {
        title: props.package.title,
        href: packagesEdit(props.package.id),
    },
]);

const form = useForm({
    title: props.package.title,
    notes: props.package.notes ?? '',
    evidence_ids: [...props.package.evidence_ids],
});

const showDeleteDialog = ref(false);
const showShareDialog = ref(false);
const showCloseDialog = ref(false);

const statusLabel = computed(() => {
    const key = `auditor.statuses.${props.package.status}`;
    const translated = t(key);

    return translated === key ? props.package.status : translated;
});

const statusVariant = computed(() => {
    if (props.package.status === 'shared') {
        return 'default' as const;
    }

    if (props.package.status === 'closed') {
        return 'secondary' as const;
    }

    return 'outline' as const;
});

const canShare = computed(
    () => props.canManage && props.package.status === 'draft',
);
const canClose = computed(
    () => props.canManage && props.package.status === 'shared',
);
const canDelete = computed(
    () => props.canManage && props.package.status === 'draft',
);

const evidenceTypeLabel = (value: string): string => {
    const key = `products.evidence.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const toggleEvidence = (evidenceId: number, checked: boolean): void => {
    if (checked) {
        if (!form.evidence_ids.includes(evidenceId)) {
            form.evidence_ids.push(evidenceId);
        }

        return;
    }

    form.evidence_ids = form.evidence_ids.filter((id) => id !== evidenceId);
};

const submit = () => {
    form.put(packagesUpdate(props.package.id).url);
};

const doShare = () => {
    showShareDialog.value = false;
    router.post(
        packagesShare(props.package.id).url,
        {},
        { preserveScroll: true },
    );
};

const doClose = () => {
    showCloseDialog.value = false;
    router.post(
        packagesClose(props.package.id).url,
        {},
        { preserveScroll: true },
    );
};

const doDelete = () => {
    showDeleteDialog.value = false;
    router.delete(packagesDestroy(props.package.id).url);
};
</script>

<template>
    <Head :title="t('auditor.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <h1 class="text-xl font-semibold">
                    {{ t('auditor.edit_title') }}
                </h1>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge :variant="statusVariant">
                        {{ statusLabel }}
                    </Badge>
                    <span class="text-muted-foreground">
                        {{ package.product_name }}
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button as-child variant="outline">
                    <Link :href="packagesShow(package.id)">
                        <Eye class="h-4 w-4" />
                        {{ t('auditor.open_review') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="auditorIndex()">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="canManage"
            class="flex flex-wrap gap-2 rounded-lg border p-3"
        >
            <Button
                v-if="canShare"
                type="button"
                variant="outline"
                @click="showShareDialog = true"
            >
                <Share2 class="h-4 w-4" />
                {{ t('auditor.share') }}
            </Button>
            <Button
                v-if="canClose"
                type="button"
                variant="outline"
                @click="showCloseDialog = true"
            >
                <Lock class="h-4 w-4" />
                {{ t('auditor.close') }}
            </Button>
            <Button
                v-if="canDelete"
                type="button"
                variant="outline"
                @click="showDeleteDialog = true"
            >
                <Trash2 class="h-4 w-4" />
                {{ t('common.delete') }}
            </Button>
        </div>

        <div
            class="space-y-2 rounded-lg border p-4 text-sm text-muted-foreground"
        >
            <p>
                {{ t('auditor.fields.status') }}:
                <span class="text-foreground">{{ statusLabel }}</span>
            </p>
            <p>
                {{ t('auditor.fields.product') }}:
                <span class="text-foreground">{{ package.product_name }}</span>
            </p>
            <p v-if="package.created_by_name">
                {{ package.created_by_name }}
            </p>
            <p v-if="package.shared_at">
                {{ new Date(package.shared_at).toLocaleString() }}
            </p>
            <p v-if="package.closed_at">
                {{ new Date(package.closed_at).toLocaleString() }}
            </p>
        </div>

        <form
            v-if="package.is_editable"
            class="space-y-5 rounded-lg border p-6"
            @submit.prevent="submit"
        >
            <fieldset :disabled="!canManage" class="space-y-5">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="title"
                        :help="t('auditor.help.title')"
                        required
                    >
                        {{ t('auditor.fields.title') }}
                    </FieldLabel>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="notes"
                        :help="t('auditor.help.notes')"
                    >
                        {{ t('auditor.fields.notes') }}
                    </FieldLabel>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel :help="t('auditor.help.evidence')">
                        {{ t('auditor.fields.evidence') }}
                    </FieldLabel>
                    <div
                        v-if="evidenceOptions.length"
                        class="max-h-56 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <label
                            v-for="item in evidenceOptions"
                            :key="item.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="form.evidence_ids.includes(item.id)"
                                @change="
                                    toggleEvidence(
                                        item.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>
                                <span class="font-medium">{{
                                    item.title
                                }}</span>
                                <span class="text-muted-foreground">
                                    — {{ evidenceTypeLabel(item.type) }}
                                </span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.evidence_ids" />
                </div>
            </fieldset>

            <div v-if="canManage" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <div v-else class="space-y-5 rounded-lg border p-6">
            <div class="grid gap-2">
                <p class="text-sm font-medium">
                    {{ t('auditor.fields.title') }}
                </p>
                <p>{{ package.title }}</p>
            </div>

            <div v-if="package.notes" class="grid gap-2">
                <p class="text-sm font-medium">
                    {{ t('auditor.fields.notes') }}
                </p>
                <p class="text-sm whitespace-pre-wrap text-muted-foreground">
                    {{ package.notes }}
                </p>
            </div>

            <div class="grid gap-2">
                <p class="text-sm font-medium">
                    {{ t('auditor.fields.evidence') }}
                </p>
                <ul v-if="package.evidence.length" class="space-y-1 text-sm">
                    <li v-for="item in package.evidence" :key="item.id">
                        {{ item.title }}
                        <span class="text-muted-foreground">
                            — {{ evidenceTypeLabel(item.type) }}
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">—</p>
            </div>
        </div>

        <AppAlertDialog
            v-model:open="showShareDialog"
            :title="t('auditor.confirm_share_title')"
            :description="t('auditor.confirm_share')"
            @confirm="doShare"
            @cancel="showShareDialog = false"
        />

        <AppAlertDialog
            v-model:open="showCloseDialog"
            :title="t('auditor.confirm_close_title')"
            :description="t('auditor.confirm_close')"
            @confirm="doClose"
            @cancel="showCloseDialog = false"
        />

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('auditor.confirm_delete')"
            @confirm="doDelete"
            @cancel="showDeleteDialog = false"
        />
    </div>
</template>
