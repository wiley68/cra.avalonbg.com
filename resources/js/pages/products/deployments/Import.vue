<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, Upload } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    importMethod as deploymentsImport,
    index as deploymentsIndex,
} from '@/routes/products/deployments';
import {
    store as importStore,
    template as importTemplate,
} from '@/routes/products/deployments/import';

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

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
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
        titleKey: 'products.deployments.import_title',
        href: deploymentsImport(props.product.id),
    },
]);

const form = useForm({
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

const templateUrl = importTemplate(props.product.id).url;
</script>

<template>
    <Head :title="t('products.deployments.import_title')" />

    <div class="mx-auto max-w-xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.deployments.import_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.deployments.import_subtitle') }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="deploymentsIndex(product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <div class="rounded-lg border p-4 text-sm text-muted-foreground">
            <p>{{ t('products.deployments.import.columns_hint') }}</p>
            <Button as-child variant="link" class="mt-2 h-auto px-0">
                <a :href="templateUrl" download>
                    <Download class="h-4 w-4" />
                    {{ t('products.deployments.import.download_template') }}
                </a>
            </Button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="file"
                    required
                    :help="t('products.deployments.import.help.file')"
                >
                    {{ t('products.deployments.import.fields.file') }}
                </FieldLabel>
                <Input
                    id="file"
                    type="file"
                    accept=".csv,text/csv"
                    required
                    @change="onFileChange"
                />
                <InputError :message="form.errors.file" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing || !form.file">
                    <Upload class="h-4 w-4" />
                    {{ t('products.deployments.import.submit') }}
                </Button>
            </div>
        </form>
    </div>
</template>
