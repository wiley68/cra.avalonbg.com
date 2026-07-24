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
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    create as packagesCreate,
    index as packagesIndex,
    store,
} from '@/routes/products/technical-documentation';

type ProductSummary = { id: number; name: string; slug: string };
type VersionOption = { id: number; version_number: string };
type PublishedUsiOption = {
    id: number;
    title: string;
    version_label: string;
    locale: string;
    product_version_id: number | null;
    version_number: string | null;
};
type SdlRunOption = {
    id: number;
    title: string;
    status: string;
    product_version_id: number | null;
    version_number: string | null;
};

const props = defineProps<{
    product: ProductSummary;
    versions: VersionOption[];
    options: {
        locales: string[];
        statuses: string[];
        section_keys: string[];
        default_locale: string;
    };
    hasPublishedPrevious: boolean;
    published_usi: PublishedUsiOption[];
    sdl_runs: SdlRunOption[];
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.technical_documentation.index_title',
        href: packagesIndex(props.product.id),
    },
    {
        titleKey: 'products.technical_documentation.create_title',
        href: packagesCreate(props.product.id),
    },
]);

const form = useForm({
    title: '',
    version_label: '1.0',
    locale: props.options.default_locale || props.options.locales[0] || 'en',
    notes: '',
    product_version_id: '' as number | '',
    user_security_instruction_id: '' as number | '',
    sdl_run_id: '' as number | '',
    inherit_from_previous: props.hasPublishedPrevious,
});

const localeLabel = (value: string): string => {
    const key = `products.technical_documentation.locales.${value}`;
    const translated = t(key);

    return translated === key ? value.toUpperCase() : translated;
};

const usiOptionLabel = (item: PublishedUsiOption): string => {
    const version = item.version_number
        ? item.version_number
        : t('products.technical_documentation.product_wide');

    return `${item.title} (${item.version_label}, ${item.locale.toUpperCase()}, ${version})`;
};

const sdlOptionLabel = (item: SdlRunOption): string => {
    const statusKey = `products.sdl.statuses.${item.status}`;
    const status = t(statusKey) === statusKey ? item.status : t(statusKey);
    const version = item.version_number
        ? item.version_number
        : t('products.technical_documentation.product_wide');

    return `${item.title} (${status}, ${version})`;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_version_id:
            data.product_version_id === '' ? null : data.product_version_id,
        user_security_instruction_id:
            data.user_security_instruction_id === ''
                ? null
                : data.user_security_instruction_id,
        sdl_run_id: data.sdl_run_id === '' ? null : data.sdl_run_id,
        inherit_from_previous: props.hasPublishedPrevious
            ? data.inherit_from_previous
            : false,
    })).post(store(props.product.id).url);
};
</script>

<template>
    <Head :title="t('products.technical_documentation.create_title')" />

    <div class="mx-auto max-w-2xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.technical_documentation.create_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.technical_documentation.create_help') }}
                </p>
            </div>
            <Button as-child variant="outline">
                <Link :href="packagesIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <div
                v-if="hasPublishedPrevious"
                class="flex items-center justify-between gap-4 rounded-md border px-3 py-2"
            >
                <div>
                    <Label for="inherit_from_previous">{{
                        t(
                            'products.technical_documentation.fields.inherit_from_previous',
                        )
                    }}</Label>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'products.technical_documentation.help.inherit_from_previous',
                            )
                        }}
                    </p>
                </div>
                <Switch
                    id="inherit_from_previous"
                    v-model="form.inherit_from_previous"
                />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="title"
                    :help="t('products.technical_documentation.help.title')"
                    required
                >
                    {{ t('products.technical_documentation.fields.title') }}
                </FieldLabel>
                <Input id="title" v-model="form.title" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="version_label"
                    :help="
                        t('products.technical_documentation.help.version_label')
                    "
                    required
                >
                    {{
                        t(
                            'products.technical_documentation.fields.version_label',
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
                <FieldLabel
                    html-for="product_version_id"
                    :help="
                        t(
                            'products.technical_documentation.help.product_version',
                        )
                    "
                >
                    {{
                        t(
                            'products.technical_documentation.fields.product_version',
                        )
                    }}
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
                    <SelectTrigger id="product_version_id" class="w-full">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="__none__">
                            {{
                                t(
                                    'products.technical_documentation.product_wide',
                                )
                            }}
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

            <div class="grid gap-2">
                <Label>{{
                    t('products.technical_documentation.fields.locale')
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
                    :help="t('products.technical_documentation.help.notes')"
                >
                    {{ t('products.technical_documentation.fields.notes') }}
                </FieldLabel>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                />
                <InputError :message="form.errors.notes" />
            </div>

            <div class="space-y-3 rounded-md border p-3">
                <div>
                    <h2 class="text-sm font-medium">
                        {{
                            t(
                                'products.technical_documentation.documentation_links_heading',
                            )
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'products.technical_documentation.documentation_links_help',
                            )
                        }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="user_security_instruction_id"
                        :help="
                            t(
                                'products.technical_documentation.help.linked_usi',
                            )
                        "
                    >
                        {{
                            t(
                                'products.technical_documentation.fields.linked_usi',
                            )
                        }}
                    </FieldLabel>
                    <Select
                        :model-value="
                            form.user_security_instruction_id === ''
                                ? '__none__'
                                : String(form.user_security_instruction_id)
                        "
                        @update:model-value="
                            (value) => {
                                form.user_security_instruction_id =
                                    value === '__none__' ||
                                    value === undefined ||
                                    value === null
                                        ? ''
                                        : Number(value);
                            }
                        "
                    >
                        <SelectTrigger
                            id="user_security_instruction_id"
                            class="w-full"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">
                                {{
                                    t(
                                        'products.technical_documentation.none_selected',
                                    )
                                }}
                            </SelectItem>
                            <SelectItem
                                v-for="item in published_usi"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ usiOptionLabel(item) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError
                        :message="form.errors.user_security_instruction_id"
                    />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="sdl_run_id"
                        :help="
                            t(
                                'products.technical_documentation.help.linked_sdl',
                            )
                        "
                    >
                        {{
                            t(
                                'products.technical_documentation.fields.linked_sdl',
                            )
                        }}
                    </FieldLabel>
                    <Select
                        :model-value="
                            form.sdl_run_id === ''
                                ? '__none__'
                                : String(form.sdl_run_id)
                        "
                        @update:model-value="
                            (value) => {
                                form.sdl_run_id =
                                    value === '__none__' ||
                                    value === undefined ||
                                    value === null
                                        ? ''
                                        : Number(value);
                            }
                        "
                    >
                        <SelectTrigger id="sdl_run_id" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="__none__">
                                {{
                                    t(
                                        'products.technical_documentation.none_selected',
                                    )
                                }}
                            </SelectItem>
                            <SelectItem
                                v-for="item in sdl_runs"
                                :key="item.id"
                                :value="String(item.id)"
                            >
                                {{ sdlOptionLabel(item) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="form.errors.sdl_run_id" />
                </div>
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('products.technical_documentation.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
