<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
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
    edit as campaignsEdit,
    index as campaignsIndex,
    show as campaignsShow,
    update,
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

type CampaignForm = {
    id: number;
    title: string;
    target_version_id: number;
    product_vulnerability_id: number | null;
    notes: string | null;
    status: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    campaign: CampaignForm;
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
        titleKey: 'products.campaigns.edit_title',
        href: campaignsEdit({
            product: props.product.id,
            campaign: props.campaign.id,
        }),
    },
]);

const form = useForm({
    title: props.campaign.title,
    target_version_id: props.campaign.target_version_id as number | '',
    product_vulnerability_id: (props.campaign.product_vulnerability_id ??
        '') as number | '',
    notes: props.campaign.notes ?? '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        target_version_id:
            data.target_version_id === '' ? null : data.target_version_id,
        product_vulnerability_id:
            data.product_vulnerability_id === ''
                ? null
                : data.product_vulnerability_id,
        notes: data.notes || null,
    })).put(
        update({
            product: props.product.id,
            campaign: props.campaign.id,
        }).url,
    );
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
    <Head :title="t('products.campaigns.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-semibold">
                {{ t('products.campaigns.edit_title') }}
            </h1>
            <Button as-child variant="outline">
                <Link
                    :href="
                        campaignsShow({
                            product: product.id,
                            campaign: campaign.id,
                        })
                    "
                >
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
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
                        <SelectValue />
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

            <Button type="submit" :disabled="form.processing">
                <Save class="h-4 w-4" />
                {{ t('common.save') }}
            </Button>
        </form>
    </div>
</template>
