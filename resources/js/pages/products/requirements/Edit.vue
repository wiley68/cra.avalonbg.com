<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import {
    index as requirementsIndex,
    update,
} from '@/routes/products/requirements';

type Member = {
    id: number;
    name: string;
    email: string;
};

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductRequirementPayload = {
    id: number;
    code: string;
    article_ref: string | null;
    status: string;
    plain_language: string | null;
    requirement_text: string | null;
    suggested_controls_text: string | null;
    required_evidence_text: string | null;
    version: number | null;
    rationale: string | null;
    owner_user_id: number | null;
};

type HistoryItem = {
    id: number;
    from_status: string | null;
    to_status: string;
    rationale: string | null;
    created_at: string | null;
};

const props = defineProps<{
    product: ProductSummary;
    productRequirement: ProductRequirementPayload;
    histories: HistoryItem[];
    canManage: boolean;
    options: { statuses: string[] };
    members: Member[];
}>();

const { t } = useTranslations();

const form = useForm({
    status: props.productRequirement.status,
    rationale: props.productRequirement.rationale ?? '',
    owner_user_id: (props.productRequirement.owner_user_id ?? '') as
        number | '',
});

const statusLabel = (value: string): string => {
    const key = `products.requirements.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        owner_user_id: data.owner_user_id || null,
    })).put(
        update({
            product: props.product.id,
            requirement: props.productRequirement.id,
        }).url,
    );
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.requirements.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }} ·
                    {{ props.productRequirement.code }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.requirements.edit_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="requirementsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <section class="space-y-3 rounded-lg border p-6 text-sm">
            <p>
                <span class="font-medium">
                    {{ t('products.requirements.fields.article_ref') }}:
                </span>
                {{ props.productRequirement.article_ref ?? '—' }}
            </p>
            <p v-if="props.productRequirement.version != null">
                <span class="font-medium">
                    {{ t('products.requirements.fields.version') }}:
                </span>
                v{{ props.productRequirement.version }}
            </p>
            <div>
                <p class="font-medium">
                    {{ t('products.requirements.fields.requirement_text') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ props.productRequirement.requirement_text }}
                </p>
            </div>
            <div v-if="props.productRequirement.plain_language">
                <p class="font-medium">
                    {{ t('products.requirements.fields.plain_language') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ props.productRequirement.plain_language }}
                </p>
            </div>
            <div v-if="props.productRequirement.suggested_controls_text">
                <p class="font-medium">
                    {{ t('products.requirements.fields.suggested_controls') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ props.productRequirement.suggested_controls_text }}
                </p>
            </div>
            <div v-if="props.productRequirement.required_evidence_text">
                <p class="font-medium">
                    {{ t('products.requirements.fields.required_evidence') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ props.productRequirement.required_evidence_text }}
                </p>
            </div>
        </section>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="status"
                    required
                    :help="t('products.requirements.help.status')"
                >
                    {{ t('products.requirements.fields.status') }}
                </FieldLabel>
                <select
                    id="status"
                    v-model="form.status"
                    class="h-9 rounded-md border bg-background px-3"
                    :disabled="!canManage"
                    required
                >
                    <option
                        v-for="value in options.statuses"
                        :key="value"
                        :value="value"
                    >
                        {{ statusLabel(value) }}
                    </option>
                </select>
                <InputError :message="form.errors.status" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="owner_user_id"
                    :help="t('products.requirements.help.owner')"
                >
                    {{ t('products.requirements.fields.owner') }}
                </FieldLabel>
                <select
                    id="owner_user_id"
                    v-model="form.owner_user_id"
                    class="h-9 rounded-md border bg-background px-3"
                    :disabled="!canManage"
                >
                    <option value="">{{ t('products.none') }}</option>
                    <option
                        v-for="member in members"
                        :key="member.id"
                        :value="member.id"
                    >
                        {{ member.name }} ({{ member.email }})
                    </option>
                </select>
                <InputError :message="form.errors.owner_user_id" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="rationale"
                    :help="t('products.requirements.help.rationale')"
                >
                    {{ t('products.requirements.fields.rationale') }}
                </FieldLabel>
                <textarea
                    id="rationale"
                    v-model="form.rationale"
                    rows="4"
                    :disabled="!canManage"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.rationale" />
            </div>

            <Button v-if="canManage" type="submit" :disabled="form.processing">
                <Save class="h-4 w-4" />
                {{ t('common.save') }}
            </Button>
        </form>

        <section class="space-y-3 rounded-lg border p-6">
            <h2
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ t('products.requirements.history_title') }}
            </h2>
            <p
                v-if="histories.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('products.requirements.no_history') }}
            </p>
            <ul v-else class="space-y-3 text-sm">
                <li
                    v-for="item in histories"
                    :key="item.id"
                    class="border-b pb-2 last:border-0"
                >
                    <p>
                        {{
                            item.from_status
                                ? statusLabel(item.from_status)
                                : '—'
                        }}
                        → {{ statusLabel(item.to_status) }}
                    </p>
                    <p class="text-muted-foreground">
                        {{
                            item.created_at
                                ? new Date(item.created_at).toLocaleString()
                                : '—'
                        }}
                    </p>
                    <p v-if="item.rationale" class="mt-1 whitespace-pre-wrap">
                        {{ item.rationale }}
                    </p>
                </li>
            </ul>
        </section>
    </div>
</template>
