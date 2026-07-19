<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import { computed } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { index as productTasksIndex, store } from '@/routes/products/tasks';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { create as productTasksCreate } from '@/routes/products/tasks';

type Member = { id: number; name: string; email: string };
type SubjectOption = { id: number; label: string };
type ProductSummary = { id: number; name: string; slug: string };

const props = defineProps<{
    product: ProductSummary;
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
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.tasks.index_title', href: productTasksIndex(props.product.id) },
    { titleKey: 'products.tasks.create_title', href: productTasksCreate(props.product.id) },
]);

const form = useForm({
    title: '',
    description: '',
    status: props.options.statuses[0] ?? 'open',
    priority: props.options.priorities[1] ?? 'medium',
    assignee_user_id: '' as number | '',
    due_at: '',
    subject_type: '' as string,
    subject_id: '' as number | '',
    approval_status: props.options.approval_statuses[0] ?? 'not_required',
});

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
    form.transform((data) => ({
        ...data,
        assignee_user_id: data.assignee_user_id || null,
        due_at: data.due_at || null,
        subject_type: data.subject_type || null,
        subject_id:
            data.subject_type && data.subject_id ? data.subject_id : null,
    })).post(store(props.product.id).url);
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.tasks.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const onSubjectTypeChange = () => {
    form.subject_id = '';
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.tasks.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.tasks.create_title') }}
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

            <div class="grid gap-2">
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

            <div class="flex justify-end gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('products.tasks.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
