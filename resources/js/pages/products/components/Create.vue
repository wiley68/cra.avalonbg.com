<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    index as productComponentsIndex,
    store,
} from '@/routes/products/components';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { create as productComponentsCreate } from '@/routes/products/components';

type VersionOption = { id: number; version_number: string };
type ProductSummary = { id: number; name: string; slug: string };

const props = defineProps<{
    product: ProductSummary;
    versions: VersionOption[];
    options: {
        ecosystems: string[];
        support_statuses: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.components.index_title', href: productComponentsIndex(props.product.id) },
    { titleKey: 'products.components.create_title', href: productComponentsCreate(props.product.id) },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    product_version_id: (props.versions[0]?.id ?? '') as number | '',
    name: '',
    supplier: '',
    package_ecosystem: props.options.ecosystems[0] ?? 'composer',
    version: '',
    licence: '',
    purl: '',
    hash: '',
    is_direct: true,
    is_dev: false,
    usage_context: '',
    support_status: props.options.support_statuses[0] ?? 'unknown',
    notes: '',
});

const submit = () => {
    form.post(store(props.product.id).url);
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.components.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};
</script>

<template>
    <Head :title="t('products.components.create_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.components.create_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productComponentsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="product_version_id"
                        required
                        :help="t('products.components.help.product_version')"
                    >
                        {{ t('products.components.fields.product_version') }}
                    </FieldLabel>
                    <select
                        id="product_version_id"
                        v-model="form.product_version_id"
                        :class="selectClass"
                        required
                    >
                        <option disabled value="">
                            {{ t('common.select') }}
                        </option>
                        <option
                            v-for="version in versions"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.version_number }}
                        </option>
                    </select>
                    <InputError :message="form.errors.product_version_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="name"
                        required
                        :help="t('products.components.help.name')"
                    >
                        {{ t('products.components.fields.name') }}
                    </FieldLabel>
                    <Input id="name" v-model="form.name" required />
                    <InputError :message="form.errors.name" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="supplier"
                        :help="t('products.components.help.supplier')"
                    >
                        {{ t('products.components.fields.supplier') }}
                    </FieldLabel>
                    <Input id="supplier" v-model="form.supplier" />
                    <InputError :message="form.errors.supplier" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="package_ecosystem"
                        required
                        :help="t('products.components.help.package_ecosystem')"
                    >
                        {{ t('products.components.fields.package_ecosystem') }}
                    </FieldLabel>
                    <select
                        id="package_ecosystem"
                        v-model="form.package_ecosystem"
                        :class="selectClass"
                        required
                    >
                        <option
                            v-for="ecosystem in options.ecosystems"
                            :key="ecosystem"
                            :value="ecosystem"
                        >
                            {{ enumLabel('ecosystems', ecosystem) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.package_ecosystem" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="version"
                        :help="t('products.components.help.version')"
                    >
                        {{ t('products.components.fields.version') }}
                    </FieldLabel>
                    <Input id="version" v-model="form.version" />
                    <InputError :message="form.errors.version" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="licence"
                        :help="t('products.components.help.licence')"
                    >
                        {{ t('products.components.fields.licence') }}
                    </FieldLabel>
                    <Input id="licence" v-model="form.licence" />
                    <InputError :message="form.errors.licence" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="purl"
                        :help="t('products.components.help.purl')"
                    >
                        {{ t('products.components.fields.purl') }}
                    </FieldLabel>
                    <Input id="purl" v-model="form.purl" />
                    <InputError :message="form.errors.purl" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="hash"
                        :help="t('products.components.help.hash')"
                    >
                        {{ t('products.components.fields.hash') }}
                    </FieldLabel>
                    <Input id="hash" v-model="form.hash" />
                    <InputError :message="form.errors.hash" />
                </div>

                <div class="flex items-center gap-3">
                    <Switch
                        id="is_direct"
                        v-model="form.is_direct"
                        class="cursor-pointer"
                    />
                    <FieldLabel
                        html-for="is_direct"
                        :help="t('products.components.help.is_direct')"
                    >
                        {{ t('products.components.fields.is_direct') }}
                    </FieldLabel>
                </div>

                <div class="flex items-center gap-3">
                    <Switch
                        id="is_dev"
                        v-model="form.is_dev"
                        class="cursor-pointer"
                    />
                    <FieldLabel
                        html-for="is_dev"
                        :help="t('products.components.help.is_dev')"
                    >
                        {{ t('products.components.fields.is_dev') }}
                    </FieldLabel>
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="usage_context"
                        :help="t('products.components.help.usage_context')"
                    >
                        {{ t('products.components.fields.usage_context') }}
                    </FieldLabel>
                    <Input id="usage_context" v-model="form.usage_context" />
                    <InputError :message="form.errors.usage_context" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="support_status"
                        required
                        :help="t('products.components.help.support_status')"
                    >
                        {{ t('products.components.fields.support_status') }}
                    </FieldLabel>
                    <select
                        id="support_status"
                        v-model="form.support_status"
                        :class="selectClass"
                        required
                    >
                        <option
                            v-for="status in options.support_statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ enumLabel('support_statuses', status) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.support_status" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="notes"
                        :help="t('products.components.help.notes')"
                    >
                        {{ t('products.components.fields.notes') }}
                    </FieldLabel>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        :class="textareaClass"
                        rows="3"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('products.components.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
