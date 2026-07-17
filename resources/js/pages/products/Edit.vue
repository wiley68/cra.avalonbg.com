<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, GitBranch, Save, Trash2 } from '@lucide/vue';
import { ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { destroy, index as productsIndex, update } from '@/routes/products';
import { index as versionsIndex } from '@/routes/products/versions';

type Member = {
    id: number;
    name: string;
    email: string;
};

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type Options = {
    product_types: string[];
    licensing_models: string[];
    scope_statuses: string[];
    classification_statuses: string[];
};

type EditableProduct = {
    id: number;
    name: string;
    slug: string;
    product_line: string | null;
    description: string | null;
    intended_purpose: string | null;
    product_type: string;
    manufacturer: string | null;
    trademark: string | null;
    licensing_model: string;
    has_remote_data_processing: boolean;
    has_network_connectivity: boolean;
    deployment_model: string | null;
    support_period_notes: string | null;
    end_of_support_policy: string | null;
    product_owner_user_id: number | null;
    security_contact_user_id: number | null;
    scope_status: string;
    scope_rationale: string | null;
    classification_status: string;
    classification_rationale: string | null;
    classification_next_review_at: string | null;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: EditableProduct;
    members: Member[];
    options: Options;
}>();

const { t } = useTranslations();
const showDeleteDialog = ref(false);

const form = useForm({
    name: props.product.name,
    slug: props.product.slug,
    product_line: props.product.product_line ?? '',
    description: props.product.description ?? '',
    intended_purpose: props.product.intended_purpose ?? '',
    product_type: props.product.product_type,
    manufacturer: props.product.manufacturer ?? '',
    trademark: props.product.trademark ?? '',
    licensing_model: props.product.licensing_model,
    has_remote_data_processing: props.product.has_remote_data_processing,
    has_network_connectivity: props.product.has_network_connectivity,
    deployment_model: props.product.deployment_model ?? '',
    support_period_notes: props.product.support_period_notes ?? '',
    end_of_support_policy: props.product.end_of_support_policy ?? '',
    product_owner_user_id: (props.product.product_owner_user_id ?? '') as
        number | '',
    security_contact_user_id: (props.product.security_contact_user_id ?? '') as
        number | '',
    scope_status: props.product.scope_status,
    scope_rationale: props.product.scope_rationale ?? '',
    classification_status: props.product.classification_status,
    classification_rationale: props.product.classification_rationale ?? '',
    classification_next_review_at:
        props.product.classification_next_review_at ?? '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_owner_user_id: data.product_owner_user_id || null,
        security_contact_user_id: data.security_contact_user_id || null,
        classification_next_review_at:
            data.classification_next_review_at || null,
    })).put(update(props.product.id).url);
};

const confirmDelete = () => {
    showDeleteDialog.value = false;
    router.delete(destroy(props.product.id).url);
};

const labelFor = (group: string, value: string): string => {
    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none';
</script>

<template>
    <Head :title="t('products.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.organization.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.edit_title') }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="versionsIndex(props.product.id)">
                        <GitBranch class="h-4 w-4" />
                        {{ t('products.versions_link') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="productsIndex()">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <form class="space-y-8 rounded-lg border p-6" @submit.prevent="submit">
            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.identity') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel html-for="name" required :help="t('products.help.name')">{{ t('common.name') }}</FieldLabel>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="slug" :help="t('products.help.slug')">{{
                            t('products.fields.slug')
                        }}</FieldLabel>
                        <Input id="slug" v-model="form.slug" />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="product_line" :help="t('products.help.product_line')">{{
                            t('products.fields.product_line')
                        }}</FieldLabel>
                        <Input id="product_line" v-model="form.product_line" />
                        <InputError :message="form.errors.product_line" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel html-for="description" :help="t('products.help.description')">{{
                            t('products.fields.description')
                        }}</FieldLabel>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            :class="textareaClass"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel html-for="intended_purpose" :help="t('products.help.intended_purpose')">{{
                            t('products.fields.intended_purpose')
                        }}</FieldLabel>
                        <textarea
                            id="intended_purpose"
                            v-model="form.intended_purpose"
                            rows="2"
                            :class="textareaClass"
                        />
                        <InputError :message="form.errors.intended_purpose" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="product_type" required :help="t('products.help.product_type')">{{
                            t('products.fields.product_type')
                        }}</FieldLabel>
                        <select
                            id="product_type"
                            v-model="form.product_type"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.product_types"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('types', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.product_type" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="licensing_model" required :help="t('products.help.licensing_model')">{{
                            t('products.fields.licensing_model')
                        }}</FieldLabel>
                        <select
                            id="licensing_model"
                            v-model="form.licensing_model"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.licensing_models"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('licensing', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.licensing_model" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="manufacturer" :help="t('products.help.manufacturer')">{{
                            t('products.fields.manufacturer')
                        }}</FieldLabel>
                        <Input id="manufacturer" v-model="form.manufacturer" />
                        <InputError :message="form.errors.manufacturer" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="trademark" :help="t('products.help.trademark')">{{
                            t('products.fields.trademark')
                        }}</FieldLabel>
                        <Input id="trademark" v-model="form.trademark" />
                        <InputError :message="form.errors.trademark" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.technical') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-center gap-3">
                        <Switch
                            id="has_remote_data_processing"
                            v-model="form.has_remote_data_processing"
                            class="cursor-pointer"
                        />
                        <FieldLabel
                            html-for="has_remote_data_processing"
                            :help="t('products.help.remote_processing')"
                        >
                            {{ t('products.fields.remote_processing') }}
                        </FieldLabel>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch
                            id="has_network_connectivity"
                            v-model="form.has_network_connectivity"
                            class="cursor-pointer"
                        />
                        <FieldLabel
                            html-for="has_network_connectivity"
                            :help="t('products.help.network_connectivity')"
                        >
                            {{ t('products.fields.network_connectivity') }}
                        </FieldLabel>
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel html-for="deployment_model" :help="t('products.help.deployment_model')">{{
                            t('products.fields.deployment_model')
                        }}</FieldLabel>
                        <Input
                            id="deployment_model"
                            v-model="form.deployment_model"
                        />
                        <InputError :message="form.errors.deployment_model" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.support') }}
                </h2>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <FieldLabel html-for="support_period_notes" :help="t('products.help.support_period_notes')">{{
                            t('products.fields.support_period_notes')
                        }}</FieldLabel>
                        <textarea
                            id="support_period_notes"
                            v-model="form.support_period_notes"
                            rows="2"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="form.errors.support_period_notes"
                        />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="end_of_support_policy" :help="t('products.help.end_of_support_policy')">{{
                            t('products.fields.end_of_support_policy')
                        }}</FieldLabel>
                        <textarea
                            id="end_of_support_policy"
                            v-model="form.end_of_support_policy"
                            rows="2"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="form.errors.end_of_support_policy"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.scope') }}
                </h2>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <FieldLabel html-for="scope_status" required :help="t('products.help.scope_status')">{{
                            t('products.fields.scope_status')
                        }}</FieldLabel>
                        <select
                            id="scope_status"
                            v-model="form.scope_status"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.scope_statuses"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('scope', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.scope_status" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="scope_rationale" :help="t('products.help.scope_rationale')">{{
                            t('products.fields.scope_rationale')
                        }}</FieldLabel>
                        <textarea
                            id="scope_rationale"
                            v-model="form.scope_rationale"
                            rows="3"
                            :class="textareaClass"
                        />
                        <InputError :message="form.errors.scope_rationale" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.classification') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel html-for="classification_status" required :help="t('products.help.classification_status')">{{
                            t('products.fields.classification_status')
                        }}</FieldLabel>
                        <select
                            id="classification_status"
                            v-model="form.classification_status"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.classification_statuses"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('classification', value) }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.classification_status"
                        />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel html-for="classification_rationale" :help="t('products.help.classification_rationale')">{{
                            t('products.fields.classification_rationale')
                        }}</FieldLabel>
                        <textarea
                            id="classification_rationale"
                            v-model="form.classification_rationale"
                            rows="3"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="form.errors.classification_rationale"
                        />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel html-for="classification_next_review_at" :help="t('products.help.next_review')">{{
                            t('products.fields.next_review')
                        }}</FieldLabel>
                        <Input
                            id="classification_next_review_at"
                            v-model="form.classification_next_review_at"
                            type="date"
                        />
                        <InputError
                            :message="form.errors.classification_next_review_at"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.contacts') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-2">
                        <FieldLabel html-for="product_owner_user_id" :help="t('products.help.product_owner')">{{
                            t('products.fields.product_owner')
                        }}</FieldLabel>
                        <select
                            id="product_owner_user_id"
                            v-model="form.product_owner_user_id"
                            class="h-9 w-full max-w-full min-w-0 rounded-md border bg-background px-3"
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
                        <InputError
                            :message="form.errors.product_owner_user_id"
                        />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <FieldLabel html-for="security_contact_user_id" :help="t('products.help.security_contact')">{{
                            t('products.fields.security_contact')
                        }}</FieldLabel>
                        <select
                            id="security_contact_user_id"
                            v-model="form.security_contact_user_id"
                            class="h-9 w-full max-w-full min-w-0 rounded-md border bg-background px-3"
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
                        <InputError
                            :message="form.errors.security_contact_user_id"
                        />
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="showDeleteDialog = true"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('common.delete') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.confirm_delete')"
            @confirm="confirmDelete"
        />
    </div>
</template>
