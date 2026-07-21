<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Download, Upload } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    importMethod as customersImport,
    index as customersIndex,
} from '@/routes/customers';
import {
    store as importStore,
    template as importTemplate,
} from '@/routes/customers/import';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.customers', href: customersIndex() },
    {
        titleKey: 'customers.import_title',
        href: customersImport(),
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
    form.post(importStore().url, {
        forceFormData: true,
    });
};

const templateUrl = importTemplate().url;
</script>

<template>
    <Head :title="t('customers.import_title')" />

    <div class="mx-auto max-w-xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.organization.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('customers.import_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('customers.import_subtitle') }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="customersIndex()">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <div class="rounded-lg border p-4 text-sm text-muted-foreground">
            <p>{{ t('customers.import.columns_hint') }}</p>
            <Button as-child variant="link" class="mt-2 h-auto px-0">
                <a :href="templateUrl" download>
                    <Download class="h-4 w-4" />
                    {{ t('customers.import.download_template') }}
                </a>
            </Button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="file"
                    required
                    :help="t('customers.import.help.file')"
                >
                    {{ t('customers.import.fields.file') }}
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
                    {{ t('customers.import.submit') }}
                </Button>
            </div>
        </form>
    </div>
</template>
