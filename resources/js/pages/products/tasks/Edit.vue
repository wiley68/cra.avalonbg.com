<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Check, Save, Send, X } from '@lucide/vue';
import { computed, ref } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    approve as approveTask,
    index as productTasksIndex,
    reject as rejectTask,
    submitApproval,
    update,
} from '@/routes/products/tasks';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { edit as productTasksEdit } from '@/routes/products/tasks';

type Member = { id: number; name: string; email: string };
type SubjectOption = { id: number; label: string };
type ProductSummary = { id: number; name: string; slug: string };

type TaskPayload = {
    id: number;
    title: string;
    description: string | null;
    status: string;
    priority: string;
    assignee_user_id: number | null;
    due_at: string | null;
    subject_type: string | null;
    subject_id: number | null;
    subject_label: string | null;
    approval_status: string;
    approved_by: number | null;
    approver_name: string | null;
    approved_at: string | null;
    approval_comment: string | null;
    created_by: number | null;
    creator_name: string | null;
};

const props = defineProps<{
    product: ProductSummary;
    task: TaskPayload;
    members: Member[];
    subjects: {
        risks: SubjectOption[];
        vulnerabilities: SubjectOption[];
        evidence: SubjectOption[];
    };
    options: {
        statuses: string[];
        priorities: string[];
        approval_statuses: string[];
        subject_types: string[];
    };
    canManage: boolean;
    canApprove: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.tasks.index_title', href: productTasksIndex(props.product.id) },
    {
        title: props.task.title,
        href: productTasksEdit({ product: props.product.id, task: props.task.id }),
    },
]);

const approvalComment = ref(props.task.approval_comment ?? '');

const form = useForm({
    title: props.task.title,
    description: props.task.description ?? '',
    status: props.task.status,
    priority: props.task.priority,
    assignee_user_id: (props.task.assignee_user_id ?? '') as number | '',
    due_at: props.task.due_at ?? '',
    subject_type: props.task.subject_type ?? '',
    subject_id: (props.task.subject_id ?? '') as number | '',
    approval_status: props.task.approval_status,
});

const approvalLocked = computed(
    () =>
        props.task.approval_status === 'approved' ||
        props.task.approval_status === 'rejected',
);

const canSubmitForApproval = computed(
    () =>
        props.canManage &&
        props.task.approval_status !== 'pending' &&
        props.task.approval_status !== 'approved',
);

const canDecideApproval = computed(
    () => props.canApprove && props.task.approval_status === 'pending',
);

const subjectOptions = computed((): SubjectOption[] => {
    if (form.subject_type === 'risk') {
        return props.subjects.risks;
    }

    if (form.subject_type === 'vulnerability') {
        return props.subjects.vulnerabilities;
    }

    if (form.subject_type === 'evidence') {
        return props.subjects.evidence;
    }

    return [];
});

const submit = () => {
    form.transform((data) => {
        const payload: Record<string, unknown> = {
            ...data,
            assignee_user_id: data.assignee_user_id || null,
            due_at: data.due_at || null,
            subject_type: data.subject_type || null,
            subject_id:
                data.subject_type && data.subject_id ? data.subject_id : null,
        };

        if (approvalLocked.value) {
            delete payload.approval_status;
        }

        return payload;
    }).put(
        update({
            product: props.product.id,
            task: props.task.id,
        }).url,
    );
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.tasks.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const onSubjectTypeChange = () => {
    form.subject_id = '';
};

const submitForApproval = () => {
    router.post(
        submitApproval({
            product: props.product.id,
            task: props.task.id,
        }).url,
        {},
        { preserveScroll: true },
    );
};

const approve = () => {
    router.post(
        approveTask({
            product: props.product.id,
            task: props.task.id,
        }).url,
        { approval_comment: approvalComment.value || null },
        { preserveScroll: true },
    );
};

const reject = () => {
    router.post(
        rejectTask({
            product: props.product.id,
            task: props.task.id,
        }).url,
        { approval_comment: approvalComment.value || null },
        { preserveScroll: true },
    );
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.tasks.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.tasks.edit_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productTasksIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <fieldset :disabled="!canManage" class="space-y-5">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="title"
                        required
                        :help="t('products.tasks.help.title')"
                    >
                        {{ t('products.tasks.fields.title') }}
                    </FieldLabel>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="description"
                        :help="t('products.tasks.help.description')"
                    >
                        {{ t('products.tasks.fields.description') }}
                    </FieldLabel>
                    <textarea
                        id="description"
                        v-model="form.description"
                        rows="4"
                        :class="textareaClass"
                    />
                    <InputError :message="form.errors.description" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="status"
                            required
                            :help="t('products.tasks.help.status')"
                        >
                            {{ t('products.tasks.fields.status') }}
                        </FieldLabel>
                        <select
                            id="status"
                            v-model="form.status"
                            class="h-9 rounded-md border bg-background px-3"
                            required
                        >
                            <option
                                v-for="value in options.statuses"
                                :key="value"
                                :value="value"
                            >
                                {{ enumLabel('statuses', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="priority"
                            required
                            :help="t('products.tasks.help.priority')"
                        >
                            {{ t('products.tasks.fields.priority') }}
                        </FieldLabel>
                        <select
                            id="priority"
                            v-model="form.priority"
                            class="h-9 rounded-md border bg-background px-3"
                            required
                        >
                            <option
                                v-for="value in options.priorities"
                                :key="value"
                                :value="value"
                            >
                                {{ enumLabel('priorities', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.priority" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="assignee_user_id"
                            :help="t('products.tasks.help.assignee')"
                        >
                            {{ t('products.tasks.fields.assignee') }}
                        </FieldLabel>
                        <select
                            id="assignee_user_id"
                            v-model="form.assignee_user_id"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option value="">
                                {{ t('products.none') }}
                            </option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.assignee_user_id" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="due_at"
                            :help="t('products.tasks.help.due_at')"
                        >
                            {{ t('products.tasks.fields.due_at') }}
                        </FieldLabel>
                        <Input id="due_at" v-model="form.due_at" type="date" />
                        <InputError :message="form.errors.due_at" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="subject_type"
                            :help="t('products.tasks.help.subject_type')"
                        >
                            {{ t('products.tasks.fields.subject_type') }}
                        </FieldLabel>
                        <select
                            id="subject_type"
                            v-model="form.subject_type"
                            class="h-9 rounded-md border bg-background px-3"
                            @change="onSubjectTypeChange"
                        >
                            <option value="">
                                {{ t('products.none') }}
                            </option>
                            <option
                                v-for="value in options.subject_types"
                                :key="value"
                                :value="value"
                            >
                                {{ enumLabel('subject_types', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.subject_type" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="subject_id"
                            :help="t('products.tasks.help.subject_id')"
                        >
                            {{ t('products.tasks.fields.subject_id') }}
                        </FieldLabel>
                        <select
                            id="subject_id"
                            v-model="form.subject_id"
                            class="h-9 rounded-md border bg-background px-3"
                            :disabled="!form.subject_type"
                        >
                            <option value="">
                                {{ t('products.none') }}
                            </option>
                            <option
                                v-for="item in subjectOptions"
                                :key="item.id"
                                :value="item.id"
                            >
                                {{ item.label }}
                            </option>
                        </select>
                        <InputError :message="form.errors.subject_id" />
                    </div>
                </div>

                <div v-if="!approvalLocked" class="grid gap-2">
                    <FieldLabel
                        html-for="approval_status"
                        :help="t('products.tasks.help.approval_status')"
                    >
                        {{ t('products.tasks.fields.approval_status') }}
                    </FieldLabel>
                    <select
                        id="approval_status"
                        v-model="form.approval_status"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option
                            v-for="value in options.approval_statuses"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('approval_statuses', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.approval_status" />
                </div>
                <div v-else class="grid gap-2">
                    <FieldLabel
                        :help="t('products.tasks.help.approval_status')"
                    >
                        {{ t('products.tasks.fields.approval_status') }}
                    </FieldLabel>
                    <p class="text-sm">
                        {{
                            enumLabel(
                                'approval_statuses',
                                props.task.approval_status,
                            )
                        }}
                        <span
                            v-if="props.task.approver_name"
                            class="text-muted-foreground"
                        >
                            — {{ props.task.approver_name }}
                        </span>
                    </p>
                </div>
            </fieldset>

            <div
                v-if="
                    props.task.approval_comment &&
                    (approvalLocked || canDecideApproval)
                "
                class="rounded-md border bg-muted/40 p-3 text-sm"
            >
                <p class="font-medium">
                    {{ t('products.tasks.fields.approval_comment') }}
                </p>
                <p class="text-muted-foreground">
                    {{ props.task.approval_comment }}
                </p>
            </div>

            <div
                v-if="canDecideApproval"
                class="grid gap-2 rounded-md border p-4"
            >
                <FieldLabel
                    html-for="approval_comment"
                    :help="t('products.tasks.help.approval_comment')"
                >
                    {{ t('products.tasks.fields.approval_comment') }}
                </FieldLabel>
                <textarea
                    id="approval_comment"
                    v-model="approvalComment"
                    rows="3"
                    :class="textareaClass"
                />
            </div>

            <div class="flex flex-wrap justify-end gap-2">
                <Button
                    v-if="canSubmitForApproval"
                    type="button"
                    variant="outline"
                    @click="submitForApproval"
                >
                    <Send class="h-4 w-4" />
                    {{ t('products.tasks.submit_for_approval') }}
                </Button>
                <Button
                    v-if="canDecideApproval"
                    type="button"
                    variant="outline"
                    @click="reject"
                >
                    <X class="h-4 w-4" />
                    {{ t('products.tasks.reject') }}
                </Button>
                <Button v-if="canDecideApproval" type="button" @click="approve">
                    <Check class="h-4 w-4" />
                    {{ t('products.tasks.approve') }}
                </Button>
                <Button
                    v-if="canManage"
                    type="submit"
                    :disabled="form.processing"
                >
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
