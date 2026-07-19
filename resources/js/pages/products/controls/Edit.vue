<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Save } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    index as productControlsIndex,
    update,
} from '@/routes/products/controls';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { edit as productControlsEdit } from '@/routes/products/controls';

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductControlPayload = {
    id: number;
    status: string;
    notes: string | null;
    reviewed_at: string | null;
    control: {
        id: number;
        code: string;
        name: string;
        description: string | null;
        implementation_guidance: string | null;
        requirement_codes: string[];
    };
};

const props = defineProps<{
    product: ProductSummary;
    productControl: ProductControlPayload;
    canManage: boolean;
    options: { statuses: string[] };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.controls.index_title', href: productControlsIndex(props.product.id) },
    {
        title: props.productControl.control.code,
        href: productControlsEdit({
            product: props.product.id,
            product_control: props.productControl.id,
        }),
    },
]);

const form = useForm({
    status: props.productControl.status,
    notes: props.productControl.notes ?? '',
});

const submit = () => {
    form.put(
        update({
            product: props.product.id,
            product_control: props.productControl.id,
        }).url,
    );
};

const statusLabel = (value: string): string => {
    const key = `products.controls.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.controls.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }} ·
                    {{ props.productControl.control.code }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.controls.edit_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productControlsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <section class="space-y-3 rounded-lg border p-6 text-sm">
            <p>
                <span class="font-medium"> {{ t('common.name') }}: </span>
                {{ props.productControl.control.name }}
            </p>
            <div v-if="props.productControl.control.description">
                <p class="font-medium">
                    {{ t('controls.fields.description') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ props.productControl.control.description }}
                </p>
            </div>
            <div v-if="props.productControl.control.implementation_guidance">
                <p class="font-medium">
                    {{ t('controls.fields.implementation_guidance') }}
                </p>
                <p class="mt-1 whitespace-pre-wrap text-muted-foreground">
                    {{ props.productControl.control.implementation_guidance }}
                </p>
            </div>
            <div
                v-if="props.productControl.control.requirement_codes.length > 0"
            >
                <p class="font-medium">
                    {{ t('controls.fields.requirements') }}
                </p>
                <p class="mt-1 text-muted-foreground">
                    {{
                        props.productControl.control.requirement_codes.join(
                            ', ',
                        )
                    }}
                </p>
            </div>
        </section>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="status"
                    required
                    :help="t('products.controls.help.status')"
                >
                    {{ t('products.controls.fields.status') }}
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
                    html-for="notes"
                    :help="t('products.controls.help.notes')"
                >
                    {{ t('products.controls.fields.notes') }}
                </FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="4"
                    :disabled="!canManage"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <Button v-if="canManage" type="submit" :disabled="form.processing">
                <Save class="h-4 w-4" />
                {{ t('common.save') }}
            </Button>
        </form>
    </div>
</template>
