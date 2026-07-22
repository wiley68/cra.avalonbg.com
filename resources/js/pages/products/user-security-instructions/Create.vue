<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
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
    create as instructionsCreate,
    index as instructionsIndex,
    store,
} from '@/routes/products/security-instructions';

type ProductSummary = { id: number; name: string; slug: string };

const props = defineProps<{
    product: ProductSummary;
    options: {
        locales: string[];
        statuses: string[];
        section_keys: string[];
        default_locale: string;
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.user_security_instructions.index_title',
        href: instructionsIndex(props.product.id),
    },
    {
        titleKey: 'products.user_security_instructions.create_title',
        href: instructionsCreate(props.product.id),
    },
]);

const form = useForm({
    title: '',
    version_label: '1.0',
    locale: props.options.default_locale || props.options.locales[0] || 'en',
    notes: '',
});

const localeLabel = (value: string): string => {
    const key = `products.user_security_instructions.locales.${value}`;
    const translated = t(key);

    return translated === key ? value.toUpperCase() : translated;
};

const submit = () => {
    form.post(store(props.product.id).url);
};
</script>

<template>
    <Head :title="t('products.user_security_instructions.create_title')" />

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.user_security_instructions.create_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.user_security_instructions.create_help') }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="instructionsIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="title"
                    :help="t('products.user_security_instructions.help.title')"
                    required
                >
                    {{ t('products.user_security_instructions.fields.title') }}
                </FieldLabel>
                <Input id="title" v-model="form.title" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="version_label"
                    :help="
                        t(
                            'products.user_security_instructions.help.version_label',
                        )
                    "
                    required
                >
                    {{
                        t(
                            'products.user_security_instructions.fields.version_label',
                        )
                    }}
                </FieldLabel>
                <Input
                    id="version_label"
                    v-model="form.version_label"
                    required
                />
                <InputError :message="form.errors.version_label" />
            </div>

            <div class="grid gap-2">
                <Label>{{
                    t('products.user_security_instructions.fields.locale')
                }}</Label>
                <Select v-model="form.locale">
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem
                            v-for="locale in options.locales"
                            :key="locale"
                            :value="locale"
                        >
                            {{ localeLabel(locale) }}
                        </SelectItem>
                    </SelectContent>
                </Select>
                <InputError :message="form.errors.locale" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="notes"
                    :help="t('products.user_security_instructions.help.notes')"
                >
                    {{ t('products.user_security_instructions.fields.notes') }}
                </FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('products.user_security_instructions.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
