<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { index as productsIndex, store } from '@/routes/products';

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

const props = defineProps<{
    organization: OrganizationSummary;
    members: Member[];
    options: Options;
}>();

const { t } = useTranslations();

const form = useForm({
    name: '',
    slug: '',
    product_line: '',
    description: '',
    intended_purpose: '',
    product_type: props.options.product_types[0] ?? 'software',
    manufacturer: '',
    trademark: '',
    licensing_model: props.options.licensing_models[0] ?? 'unknown',
    has_remote_data_processing: false,
    has_network_connectivity: false,
    deployment_model: '',
    support_period_notes: '',
    end_of_support_policy: '',
    product_owner_user_id: '' as number | '',
    security_contact_user_id: '' as number | '',
    scope_status: props.options.scope_statuses[0] ?? 'insufficient_information',
    scope_rationale: '',
    classification_status:
        props.options.classification_statuses.find(
            (s) => s === 'unclassified',
        ) ??
        props.options.classification_statuses[0] ??
        'unclassified',
    classification_rationale: '',
    classification_next_review_at: '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_owner_user_id: data.product_owner_user_id || null,
        security_contact_user_id: data.security_contact_user_id || null,
        classification_next_review_at:
            data.classification_next_review_at || null,
    })).post(store().url);
};

const labelFor = (group: string, value: string): string => {
    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};
</script>

<template>
    <Head :title="t('products.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.organization.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.create_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productsIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
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
                        <Label for="name">{{ t('common.name') }}</Label>
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="slug">{{
                            t('products.fields.slug')
                        }}</Label>
                        <Input id="slug" v-model="form.slug" />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="product_line">{{
                            t('products.fields.product_line')
                        }}</Label>
                        <Input id="product_line" v-model="form.product_line" />
                        <InputError :message="form.errors.product_line" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="description">{{
                            t('products.fields.description')
                        }}</Label>
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="intended_purpose">{{
                            t('products.fields.intended_purpose')
                        }}</Label>
                        <textarea
                            id="intended_purpose"
                            v-model="form.intended_purpose"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError :message="form.errors.intended_purpose" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="product_type">{{
                            t('products.fields.product_type')
                        }}</Label>
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
                        <Label for="licensing_model">{{
                            t('products.fields.licensing_model')
                        }}</Label>
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
                        <Label for="manufacturer">{{
                            t('products.fields.manufacturer')
                        }}</Label>
                        <Input id="manufacturer" v-model="form.manufacturer" />
                        <InputError :message="form.errors.manufacturer" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="trademark">{{
                            t('products.fields.trademark')
                        }}</Label>
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
                        <Label
                            for="has_remote_data_processing"
                            class="cursor-pointer"
                        >
                            {{ t('products.fields.remote_processing') }}
                        </Label>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch
                            id="has_network_connectivity"
                            v-model="form.has_network_connectivity"
                            class="cursor-pointer"
                        />
                        <Label
                            for="has_network_connectivity"
                            class="cursor-pointer"
                        >
                            {{ t('products.fields.network_connectivity') }}
                        </Label>
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <Label for="deployment_model">{{
                            t('products.fields.deployment_model')
                        }}</Label>
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
                        <Label for="support_period_notes">{{
                            t('products.fields.support_period_notes')
                        }}</Label>
                        <textarea
                            id="support_period_notes"
                            v-model="form.support_period_notes"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError
                            :message="form.errors.support_period_notes"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="end_of_support_policy">{{
                            t('products.fields.end_of_support_policy')
                        }}</Label>
                        <textarea
                            id="end_of_support_policy"
                            v-model="form.end_of_support_policy"
                            rows="2"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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
                        <Label for="scope_status">{{
                            t('products.fields.scope_status')
                        }}</Label>
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
                        <Label for="scope_rationale">{{
                            t('products.fields.scope_rationale')
                        }}</Label>
                        <textarea
                            id="scope_rationale"
                            v-model="form.scope_rationale"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
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
                        <Label for="classification_status">{{
                            t('products.fields.classification_status')
                        }}</Label>
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
                        <Label for="classification_rationale">{{
                            t('products.fields.classification_rationale')
                        }}</Label>
                        <textarea
                            id="classification_rationale"
                            v-model="form.classification_rationale"
                            rows="3"
                            class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                        />
                        <InputError
                            :message="form.errors.classification_rationale"
                        />
                    </div>
                    <div class="grid gap-2">
                        <Label for="classification_next_review_at">{{
                            t('products.fields.next_review')
                        }}</Label>
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
                    <div class="grid gap-2">
                        <Label for="product_owner_user_id">{{
                            t('products.fields.product_owner')
                        }}</Label>
                        <select
                            id="product_owner_user_id"
                            v-model="form.product_owner_user_id"
                            class="h-9 rounded-md border bg-background px-3"
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
                    <div class="grid gap-2">
                        <Label for="security_contact_user_id">{{
                            t('products.fields.security_contact')
                        }}</Label>
                        <select
                            id="security_contact_user_id"
                            v-model="form.security_contact_user_id"
                            class="h-9 rounded-md border bg-background px-3"
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

            <Button type="submit" :disabled="form.processing">
                <Plus class="h-4 w-4" />
                {{ t('common.create') }}
            </Button>
        </form>
    </div>
</template>
