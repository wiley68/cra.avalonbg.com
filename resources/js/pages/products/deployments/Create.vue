<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import HeaderActionButton from '@/components/HeaderActionButton.vue';
import InputError from '@/components/InputError.vue';
import PageFormHeader from '@/components/PageFormHeader.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    create as deploymentsCreate,
    index as deploymentsIndex,
    store,
} from '@/routes/products/deployments';

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

type CustomerOption = {
    id: number;
    name: string;
    criticality: string;
    is_active: boolean;
};

type VersionOption = {
    id: number;
    version_number: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    customers: CustomerOption[];
    versions: VersionOption[];
    options: { environments: string[] };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.deployments.index_title',
        href: deploymentsIndex(props.product.id),
    },
    {
        titleKey: 'products.deployments.create_title',
        href: deploymentsCreate(props.product.id),
    },
]);

const form = useForm({
    customer_id: '' as number | '',
    product_version_id: '' as number | '',
    environment: props.options.environments.includes('production')
        ? 'production'
        : (props.options.environments[0] ?? 'production'),
    installation_date: '',
    internet_exposure: false,
    update_channel: '',
    last_confirmed_at: '',
    custom_modifications: false,
    end_of_support_exception: false,
    notes: '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        customer_id: data.customer_id === '' ? null : data.customer_id,
        product_version_id:
            data.product_version_id === '' ? null : data.product_version_id,
        installation_date: data.installation_date || null,
        last_confirmed_at: data.last_confirmed_at || null,
        update_channel: data.update_channel || null,
        notes: data.notes || null,
    })).post(store(props.product.id).url);
};

const environmentLabel = (value: string): string => {
    const key = `products.deployments.environments.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.deployments.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <PageFormHeader>
            <h1 class="text-xl font-semibold">
                {{ t('products.deployments.create_title') }}
            </h1>
            <template #actions>
                <HeaderActionButton
                    is-back
                    :label="t('common.back')"
                    :href="deploymentsIndex(product.id)"
                >
                    <ArrowLeft class="h-4 w-4" />
                </HeaderActionButton>
            </template>
        </PageFormHeader>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="customer_id"
                    :help="t('products.deployments.help.customer')"
                    required
                >
                    {{ t('products.deployments.fields.customer') }}
                </FieldLabel>
                <Select
                    :model-value="
                        form.customer_id === ''
                            ? undefined
                            : String(form.customer_id)
                    "
                    @update:model-value="
                        (value) => {
                            form.customer_id =
                                typeof value === 'string' && value !== ''
                                    ? Number(value)
                                    : '';
                        }
                    "
                >
                    <SelectTrigger id="customer_id" class="w-full">
                        <SelectValue
                            :placeholder="
                                t('products.deployments.customer_placeholder')
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="customer in customers"
                            :key="customer.id"
                            :value="String(customer.id)"
                        >
                            {{ customer.name }}
                            <span
                                v-if="!customer.is_active"
                                class="text-muted-foreground"
                            >
                                ({{ t('customers.inactive') }})
                            </span>
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.customer_id" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="environment"
                    :help="t('products.deployments.help.environment')"
                    required
                >
                    {{ t('products.deployments.fields.environment') }}
                </FieldLabel>
                <Select v-model="form.environment">
                    <SelectTrigger id="environment" class="w-full max-w-xs">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="value in options.environments"
                            :key="value"
                            :value="value"
                        >
                            {{ environmentLabel(value) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.environment" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="product_version_id"
                    :help="t('products.deployments.help.version')"
                >
                    {{ t('products.deployments.fields.version') }}
                </FieldLabel>
                <Select
                    :model-value="
                        form.product_version_id === ''
                            ? '__none__'
                            : String(form.product_version_id)
                    "
                    @update:model-value="
                        (value) => {
                            form.product_version_id =
                                value === '__none__' ||
                                value === undefined ||
                                value === null
                                    ? ''
                                    : Number(value);
                        }
                    "
                >
                    <SelectTrigger
                        id="product_version_id"
                        class="w-full max-w-xs"
                    >
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__none__">
                            {{ t('products.deployments.version_none') }}
                        </SelectItem>
                        <SelectItem
                            v-for="version in versions"
                            :key="version.id"
                            :value="String(version.id)"
                        >
                            {{ version.version_number }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.product_version_id" />
            </div>

            <div class="grid gap-2 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="installation_date"
                        :help="t('products.deployments.help.installation_date')"
                    >
                        {{ t('products.deployments.fields.installation_date') }}
                    </FieldLabel>
                    <Input
                        id="installation_date"
                        v-model="form.installation_date"
                        type="date"
                    />
                    <InputError :message="form.errors.installation_date" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="last_confirmed_at"
                        :help="t('products.deployments.help.last_confirmed_at')"
                    >
                        {{ t('products.deployments.fields.last_confirmed_at') }}
                    </FieldLabel>
                    <Input
                        id="last_confirmed_at"
                        v-model="form.last_confirmed_at"
                        type="date"
                    />
                    <InputError :message="form.errors.last_confirmed_at" />
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="update_channel"
                    :help="t('products.deployments.help.update_channel')"
                >
                    {{ t('products.deployments.fields.update_channel') }}
                </FieldLabel>
                <Input id="update_channel" v-model="form.update_channel" />
                <InputError :message="form.errors.update_channel" />
            </div>

            <div
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <FieldLabel
                    html-for="internet_exposure"
                    :help="t('products.deployments.help.internet_exposure')"
                >
                    {{ t('products.deployments.fields.internet_exposure') }}
                </FieldLabel>
                <Switch
                    id="internet_exposure"
                    v-model="form.internet_exposure"
                />
            </div>

            <div
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <FieldLabel
                    html-for="custom_modifications"
                    :help="t('products.deployments.help.custom_modifications')"
                >
                    {{ t('products.deployments.fields.custom_modifications') }}
                </FieldLabel>
                <Switch
                    id="custom_modifications"
                    v-model="form.custom_modifications"
                />
            </div>

            <div
                class="flex items-center justify-between gap-4 rounded-lg border p-4"
            >
                <FieldLabel
                    html-for="end_of_support_exception"
                    :help="
                        t('products.deployments.help.end_of_support_exception')
                    "
                >
                    {{
                        t(
                            'products.deployments.fields.end_of_support_exception',
                        )
                    }}
                </FieldLabel>
                <Switch
                    id="end_of_support_exception"
                    v-model="form.end_of_support_exception"
                />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="notes"
                    :help="t('products.deployments.help.notes')"
                >
                    {{ t('products.deployments.fields.notes') }}
                </FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Plus class="h-4 w-4" />
                {{ t('products.deployments.create') }}
            </Button>
        </form>
    </div>
</template>
