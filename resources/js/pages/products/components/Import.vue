<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Upload } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { store as importStore } from '@/routes/products/components/import';
import { index as productComponentsIndex } from '@/routes/products/components';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { importMethod as productComponentsImport } from '@/routes/products/components';

type VersionOption = { id: number; version_number: string };
type ProductSummary = { id: number; name: string; slug: string };

const props = defineProps<{
    product: ProductSummary;
    versions: VersionOption[];
    options: {
        formats: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.components.index_title', href: productComponentsIndex(props.product.id) },
    {
        titleKey: 'products.components.import_title',
        href: productComponentsImport(props.product.id),
    },
]);

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    product_version_id: (props.versions[0]?.id ?? '') as number | '',
    format: '' as string,
    file: null as File | null,
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
};

const submit = () => {
    form.post(importStore(props.product.id).url, {
        forceFormData: true,
    });
};

const enumLabel = (value: string): string => {
    const key = `products.components.formats.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};
</script>

<template>
    <Head :title="t('products.components.import_title')" />

    <div class="mx-auto max-w-xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.components.import_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.components.import_subtitle') }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="productComponentsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4">
                <div class="grid gap-2">
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

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="format"
                        :help="t('products.components.help.format')"
                    >
                        {{ t('products.components.fields.format') }}
                    </FieldLabel>
                    <select
                        id="format"
                        v-model="form.format"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('products.components.format_auto') }}
                        </option>
                        <option
                            v-for="format in options.formats"
                            :key="format"
                            :value="format"
                        >
                            {{ enumLabel(format) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.format" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="file"
                        required
                        :help="t('products.components.help.file')"
                    >
                        {{ t('products.components.fields.file') }}
                    </FieldLabel>
                    <Input
                        id="file"
                        type="file"
                        accept=".json,.lock,application/json"
                        required
                        @change="onFileChange"
                    />
                    <InputError :message="form.errors.file" />
                </div>
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing || !form.file">
                    <Upload class="h-4 w-4" />
                    {{ t('products.components.import_submit') }}
                </Button>
            </div>
        </form>
    </div>
</template>
