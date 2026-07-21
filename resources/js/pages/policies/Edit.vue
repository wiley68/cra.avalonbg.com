<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    FileDown,
    FileUp,
    Pencil,
    Save,
    Send,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import PolicyBodyField from '@/components/PolicyBodyField.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
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
import {
    approve as approvePolicy,
    edit as policiesEdit,
    exportMethod as exportPolicy,
    index as policiesIndex,
    publishEvidence,
    retire as retirePolicy,
    submitReview,
    update,
} from '@/routes/policies';
import { edit as editEvidence } from '@/routes/products/evidence';
import { edit as editTask } from '@/routes/products/tasks';

type ProductOption = {
    id: number;
    name: string;
};

type MemberOption = {
    id: number;
    name: string;
};

type ReviewTask = {
    id: number;
    product_id: number;
    title: string;
    status: string;
};

type PolicyDetail = {
    id: number;
    policy_type: string;
    title: string;
    status: string;
    version_label: string;
    body: string;
    notes: string | null;
    supersedes_id: number | null;
    supersedes_title: string | null;
    supersedes_body: string | null;
    approved_at: string | null;
    approved_by_name: string | null;
    is_editable: boolean;
    evidence_id: number | null;
    evidence_product_id: number | null;
    evidence_title: string | null;
};

const props = defineProps<{
    policy: PolicyDetail;
    canManage: boolean;
    productOptions: ProductOption[];
    memberOptions: MemberOption[];
    reviewTask: ReviewTask | null;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.policies', href: policiesIndex() },
    {
        title: props.policy.title,
        href: policiesEdit(props.policy.id),
    },
]);

const form = useForm({
    title: props.policy.title,
    version_label: props.policy.version_label,
    body: props.policy.body,
    notes: props.policy.notes ?? '',
});

const showRetireDialog = ref(false);
const showPublishDialog = ref(false);
const showSubmitDialog = ref(false);

const publishForm = useForm({
    product_id: props.productOptions[0]?.id
        ? String(props.productOptions[0].id)
        : '',
});

const submitForm = useForm({
    product_id: props.productOptions[0]?.id
        ? String(props.productOptions[0].id)
        : '',
    assignee_user_id: '',
});

const typeLabel = computed(() => {
    const key = `policies.types.${props.policy.policy_type}`;
    const translated = t(key);

    return translated === key ? props.policy.policy_type : translated;
});

const statusLabel = computed(() => {
    const key = `policies.statuses.${props.policy.status}`;
    const translated = t(key);

    return translated === key ? props.policy.status : translated;
});

const canSubmit = computed(
    () => props.canManage && props.policy.status === 'draft',
);
const canApprove = computed(
    () => props.canManage && props.policy.status === 'under_review',
);
const canRetire = computed(
    () => props.canManage && props.policy.status === 'approved',
);
const canPublish = computed(
    () =>
        props.canManage &&
        props.policy.status === 'approved' &&
        props.policy.evidence_id === null,
);
const evidenceHref = computed(() => {
    if (
        props.policy.evidence_id === null ||
        props.policy.evidence_product_id === null
    ) {
        return null;
    }

    return editEvidence({
        product: props.policy.evidence_product_id,
        evidence: props.policy.evidence_id,
    }).url;
});

const exportUrl = computed(() => exportPolicy(props.policy.id).url);

const reviewTaskHref = computed(() => {
    if (!props.reviewTask) {
        return null;
    }

    return editTask({
        product: props.reviewTask.product_id,
        task: props.reviewTask.id,
    }).url;
});

const submit = () => {
    form.put(update(props.policy.id).url);
};

const openSubmitDialog = () => {
    if (!submitForm.product_id && props.productOptions[0] !== undefined) {
        submitForm.product_id = String(props.productOptions[0].id);
    }
    showSubmitDialog.value = true;
};

const doSubmitReview = () => {
    submitForm.post(submitReview(props.policy.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showSubmitDialog.value = false;
        },
    });
};

const doApprove = () => {
    router.post(
        approvePolicy(props.policy.id).url,
        {},
        { preserveScroll: true },
    );
};

const doRetire = () => {
    showRetireDialog.value = false;
    router.post(
        retirePolicy(props.policy.id).url,
        {},
        { preserveScroll: true },
    );
};

const openPublishDialog = () => {
    if (!publishForm.product_id && props.productOptions[0] !== undefined) {
        publishForm.product_id = String(props.productOptions[0].id);
    }
    showPublishDialog.value = true;
};

const doPublishEvidence = () => {
    publishForm.post(publishEvidence(props.policy.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showPublishDialog.value = false;
        },
    });
};
</script>

<template>
    <Head :title="t('policies.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-xl font-semibold">
                    {{ t('policies.edit_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ typeLabel }} · {{ statusLabel }} ·
                    {{ policy.version_label }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <a :href="exportUrl" target="_blank" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{ t('policies.export') }}
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="policiesIndex()">
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
                v-if="canSubmit"
                type="button"
                variant="outline"
                @click="openSubmitDialog"
            >
                <Send class="h-4 w-4" />
                {{ t('policies.submit_review') }}
            </Button>
            <Button v-if="reviewTaskHref" as-child variant="outline">
                <Link :href="reviewTaskHref">
                    <Pencil class="h-4 w-4" />
                    {{ t('policies.view_review_task') }}
                </Link>
            </Button>
            <Button
                v-if="canApprove"
                type="button"
                variant="outline"
                @click="doApprove"
            >
                <CheckCircle2 class="h-4 w-4" />
                {{ t('policies.approve') }}
            </Button>
            <Button
                v-if="canPublish"
                type="button"
                variant="outline"
                @click="openPublishDialog"
            >
                <FileUp class="h-4 w-4" />
                {{ t('policies.publish_evidence') }}
            </Button>
            <Button v-if="evidenceHref" as-child variant="outline">
                <Link :href="evidenceHref">
                    <Pencil class="h-4 w-4" />
                    {{ t('policies.view_evidence') }}
                </Link>
            </Button>
            <Button
                v-if="canRetire"
                type="button"
                variant="outline"
                @click="showRetireDialog = true"
            >
                <Archive class="h-4 w-4" />
                {{ t('policies.retire') }}
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <fieldset
                :disabled="!canManage || !policy.is_editable"
                class="space-y-5"
            >
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="title"
                        :help="t('policies.help.title')"
                        required
                    >
                        {{ t('policies.fields.title') }}
                    </FieldLabel>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="version_label"
                        :help="t('policies.help.version_label')"
                        required
                    >
                        {{ t('policies.fields.version_label') }}
                    </FieldLabel>
                    <Input
                        id="version_label"
                        v-model="form.version_label"
                        required
                    />
                    <InputError :message="form.errors.version_label" />
                </div>

                <div
                    v-if="policy.supersedes_title"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('policies.fields.supersedes') }}:
                    {{ policy.supersedes_title }}
                </div>

                <div
                    v-if="policy.approved_at"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('policies.fields.approved_at') }}:
                    {{ new Date(policy.approved_at).toLocaleString() }}
                    <span v-if="policy.approved_by_name">
                        ({{ policy.approved_by_name }})
                    </span>
                </div>

                <div
                    v-if="policy.evidence_title"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('policies.fields.evidence') }}:
                    {{ policy.evidence_title }}
                </div>

                <div class="grid gap-2">
                    <Label for="notes">{{ t('policies.fields.notes') }}</Label>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </fieldset>

            <PolicyBodyField
                v-model="form.body"
                :previous-body="policy.supersedes_body"
                :previous-label="policy.supersedes_title"
                :current-label="form.version_label"
                :disabled="!canManage || !policy.is_editable"
                :error="form.errors.body"
                required
            />

            <div
                v-if="canManage && policy.is_editable"
                class="flex justify-end"
            >
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showRetireDialog"
            :title="t('policies.confirm_retire_title')"
            :description="t('policies.confirm_retire')"
            @confirm="doRetire"
            @cancel="showRetireDialog = false"
        />

        <Dialog
            :open="showSubmitDialog"
            @update:open="
                (open) => {
                    if (!open) {
                        showSubmitDialog = false;
                    }
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('policies.submit_review_title') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t('policies.submit_review_help') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="doSubmitReview">
                    <div
                        v-if="productOptions.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('policies.submit_no_products') }}
                    </div>

                    <template v-else>
                        <div class="grid min-w-0 gap-2">
                            <FieldLabel
                                html-for="submit_product_id"
                                :help="t('policies.help.submit_product_id')"
                                required
                            >
                                {{ t('policies.fields.product') }}
                            </FieldLabel>
                            <Select v-model="submitForm.product_id">
                                <SelectTrigger
                                    id="submit_product_id"
                                    class="w-full max-w-full min-w-0 overflow-hidden *:data-[slot=select-value]:min-w-0 *:data-[slot=select-value]:truncate"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t('policies.select_product')
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent
                                    class="w-(--reka-select-trigger-width) max-w-(--reka-select-trigger-width)"
                                >
                                    <SelectItem
                                        v-for="product in productOptions"
                                        :key="product.id"
                                        :value="String(product.id)"
                                        class="max-w-full"
                                    >
                                        <span
                                            class="block truncate"
                                            :title="product.name"
                                        >
                                            {{ product.name }}
                                        </span>
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="submitForm.errors.product_id"
                            />
                        </div>

                        <div class="grid min-w-0 gap-2">
                            <FieldLabel
                                html-for="assignee_user_id"
                                :help="t('policies.help.assignee_user_id')"
                            >
                                {{ t('policies.fields.assignee') }}
                            </FieldLabel>
                            <Select v-model="submitForm.assignee_user_id">
                                <SelectTrigger
                                    id="assignee_user_id"
                                    class="w-full"
                                >
                                    <SelectValue
                                        :placeholder="
                                            t('policies.select_assignee')
                                        "
                                    />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="member in memberOptions"
                                        :key="member.id"
                                        :value="String(member.id)"
                                    >
                                        {{ member.name }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError
                                :message="submitForm.errors.assignee_user_id"
                            />
                        </div>
                    </template>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showSubmitDialog = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                submitForm.processing ||
                                productOptions.length === 0
                            "
                        >
                            <Send class="h-4 w-4" />
                            {{ t('policies.submit_review') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog
            :open="showPublishDialog"
            @update:open="
                (open) => {
                    if (!open) {
                        showPublishDialog = false;
                    }
                }
            "
        >
            <DialogContent class="sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>
                        {{ t('policies.publish_evidence_title') }}
                    </DialogTitle>
                    <DialogDescription>
                        {{ t('policies.publish_evidence_help') }}
                    </DialogDescription>
                </DialogHeader>

                <form class="space-y-4" @submit.prevent="doPublishEvidence">
                    <div
                        v-if="productOptions.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('policies.publish_no_products') }}
                    </div>

                    <div v-else class="grid min-w-0 gap-2">
                        <FieldLabel
                            html-for="product_id"
                            :help="t('policies.help.product_id')"
                            required
                        >
                            {{ t('policies.fields.product') }}
                        </FieldLabel>
                        <Select v-model="publishForm.product_id">
                            <SelectTrigger
                                id="product_id"
                                class="w-full max-w-full min-w-0 overflow-hidden *:data-[slot=select-value]:min-w-0 *:data-[slot=select-value]:truncate"
                            >
                                <SelectValue
                                    :placeholder="t('policies.select_product')"
                                />
                            </SelectTrigger>
                            <SelectContent
                                class="w-(--reka-select-trigger-width) max-w-(--reka-select-trigger-width)"
                            >
                                <SelectItem
                                    v-for="product in productOptions"
                                    :key="product.id"
                                    :value="String(product.id)"
                                    class="max-w-full"
                                >
                                    <span
                                        class="block truncate"
                                        :title="product.name"
                                    >
                                        {{ product.name }}
                                    </span>
                                </SelectItem>
                            </SelectContent>
                        </Select>
                        <InputError :message="publishForm.errors.product_id" />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            @click="showPublishDialog = false"
                        >
                            {{ t('common.cancel') }}
                        </Button>
                        <Button
                            type="submit"
                            :disabled="
                                publishForm.processing ||
                                productOptions.length === 0
                            "
                        >
                            <FileUp class="h-4 w-4" />
                            {{ t('policies.publish_evidence') }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
