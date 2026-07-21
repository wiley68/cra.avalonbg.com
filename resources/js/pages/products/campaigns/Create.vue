<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Play, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    create as campaignsCreate,
    index as campaignsIndex,
    store,
} from '@/routes/products/campaigns';

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

type VersionOption = {
    id: number;
    version_number: string;
};

type VulnerabilityOption = {
    id: number;
    title: string;
    cve_id: string | null;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    versions: VersionOption[];
    vulnerabilities: VulnerabilityOption[];
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.campaigns.index_title',
        href: campaignsIndex(props.product.id),
    },
    {
        titleKey: 'products.campaigns.create_title',
        href: campaignsCreate(props.product.id),
    },
]);

const form = useForm({
    title: '',
    target_version_id: '' as number | '',
    product_vulnerability_id: '' as number | '',
    notes: '',
    activate: false,
});

const submit = (activate: boolean) => {
    form.activate = activate;
    form.transform((data) => ({
        ...data,
        target_version_id:
            data.target_version_id === '' ? null : data.target_version_id,
        product_vulnerability_id:
            data.product_vulnerability_id === ''
                ? null
                : data.product_vulnerability_id,
        notes: data.notes || null,
        activate,
    })).post(store(props.product.id).url);
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';

const vulnerabilityLabel = (item: VulnerabilityOption): string => {
    if (item.cve_id) {
        return `${item.title} (${item.cve_id})`;
    }

    return item.title;
};
</script>

<template>
    <Head :title="t('products.campaigns.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('products.campaigns.create_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link :href="campaignsIndex(product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent>
            <div class="grid gap-2">
                <FieldLabel
                    html-for="title"
                    :help="t('products.campaigns.help.title')"
                    required
                >
                    {{ t('products.campaigns.fields.title') }}
                </FieldLabel>
                <Input id="title" v-model="form.title" />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="target_version_id"
                    :help="t('products.campaigns.help.target_version')"
                    required
                >
                    {{ t('products.campaigns.fields.target_version') }}
                </FieldLabel>
                <Select
                    :model-value="
                        form.target_version_id === ''
                            ? undefined
                            : String(form.target_version_id)
                    "
                    @update:model-value="
                        (value) => {
                            form.target_version_id =
                                typeof value === 'string' && value !== ''
                                    ? Number(value)
                                    : '';
                        }
                    "
                >
                    <SelectTrigger
                        id="target_version_id"
                        class="w-full max-w-xs"
                    >
                        <SelectValue
                            :placeholder="
                                t('products.campaigns.version_placeholder')
                            "
                        />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="version in versions"
                            :key="version.id"
                            :value="String(version.id)"
                        >
                            {{ version.version_number }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.target_version_id" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="product_vulnerability_id"
                    :help="t('products.campaigns.help.vulnerability')"
                >
                    {{ t('products.campaigns.fields.vulnerability') }}
                </FieldLabel>
                <Select
                    :model-value="
                        form.product_vulnerability_id === ''
                            ? '__none__'
                            : String(form.product_vulnerability_id)
                    "
                    @update:model-value="
                        (value) => {
                            form.product_vulnerability_id =
                                value === '__none__' ||
                                value === undefined ||
                                value === null
                                    ? ''
                                    : Number(value);
                        }
                    "
                >
                    <SelectTrigger id="product_vulnerability_id" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__none__">
                            {{ t('products.campaigns.vulnerability_none') }}
                        </SelectItem>
                        <SelectItem
                            v-for="item in vulnerabilities"
                            :key="item.id"
                            :value="String(item.id)"
                        >
                            {{ vulnerabilityLabel(item) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.product_vulnerability_id" />
            </div>

            <div class="grid gap-2">
                <Label for="notes">{{
                    t('products.campaigns.fields.notes')
                }}</Label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <p class="text-sm text-muted-foreground">
                {{ t('products.campaigns.seed_hint') }}
            </p>

            <div class="flex flex-wrap gap-2">
                <Button
                    type="button"
                    :disabled="form.processing"
                    @click="submit(false)"
                >
                    <Plus class="h-4 w-4" />
                    {{ t('products.campaigns.save_draft') }}
                </Button>
                <Button
                    type="button"
                    variant="secondary"
                    :disabled="form.processing"
                    @click="submit(true)"
                >
                    <Play class="h-4 w-4" />
                    {{ t('products.campaigns.create_and_activate') }}
                </Button>
            </div>
        </form>
    </div>
</template>
