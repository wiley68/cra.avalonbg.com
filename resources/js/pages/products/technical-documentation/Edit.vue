<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, RefreshCcw, Save } from '@lucide/vue';
import { computed } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import PolicyBodyField from '@/components/PolicyBodyField.vue';
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
    edit as packagesEdit,
    index as packagesIndex,
    refreshGenerated,
    update,
} from '@/routes/products/technical-documentation';

type ProductSummary = { id: number; name: string; slug: string };
type VersionOption = { id: number; version_number: string };

type SectionPayload = {
    id: number;
    section_key: string;
    source: string;
    body_markdown: string | null;
    generated_payload: Record<string, unknown> | unknown[] | null;
    sort_order: number;
    is_applicable: boolean;
    override_reason: string | null;
    changed_since_parent: boolean;
};

type PackageDetail = {
    id: number;
    title: string;
    status: string;
    version_label: string;
    locale: string;
    notes: string | null;
    is_editable: boolean;
    published_at: string | null;
    published_by_name: string | null;
    product_version_id: number | null;
    product_version_number: string | null;
    supersedes_id: number | null;
    supersedes_title: string | null;
    sections: SectionPayload[];
};

const props = defineProps<{
    product: ProductSummary;
    package: PackageDetail;
    versions: VersionOption[];
    options: {
        locales: string[];
        statuses: string[];
        section_keys: string[];
        default_locale: string;
    };
    canManage: boolean;
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
        titleKey: 'products.technical_documentation.edit_title',
        href: packagesEdit({
            product: props.product.id,
            package: props.package.id,
        }),
    },
]);

const form = useForm({
    title: props.package.title,
    version_label: props.package.version_label,
    locale: props.package.locale,
    notes: props.package.notes ?? '',
    product_version_id: (props.package.product_version_id ?? '') as number | '',
    sections: props.package.sections.map((section) => ({
        section_key: section.section_key,
        source: section.source,
        body_markdown: section.body_markdown ?? '',
        sort_order: section.sort_order,
        is_applicable: section.is_applicable,
        override_reason: section.override_reason ?? '',
    })),
});

const generatedPayloadByKey = computed(() => {
    const map: Record<string, Record<string, unknown> | unknown[] | null> = {};

    for (const section of props.package.sections) {
        map[section.section_key] = section.generated_payload;
    }

    return map;
});

const generatedMarkdown = (sectionKey: string): string => {
    const payload = generatedPayloadByKey.value[sectionKey];

    if (
        payload !== null &&
        !Array.isArray(payload) &&
        typeof payload === 'object' &&
        typeof payload.markdown === 'string'
    ) {
        return payload.markdown;
    }

    return '';
};

const generatedAtLabel = (sectionKey: string): string | null => {
    const payload = generatedPayloadByKey.value[sectionKey];

    if (
        payload !== null &&
        !Array.isArray(payload) &&
        typeof payload === 'object' &&
        typeof payload.generated_at === 'string'
    ) {
        return payload.generated_at;
    }

    return null;
};

const readOnly = computed(() => !props.package.is_editable || !props.canManage);

const localeLabel = (value: string): string => {
    const key = `products.technical_documentation.locales.${value}`;
    const translated = t(key);

    return translated === key ? value.toUpperCase() : translated;
};

const statusLabel = (value: string): string => {
    const key = `products.technical_documentation.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const sectionLabel = (value: string): string => {
    const key = `products.technical_documentation.sections.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const sourceLabel = (value: string): string => {
    const key = `products.technical_documentation.sources.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const sectionError = (index: number, field: string): string | undefined => {
    const key = `sections.${index}.${field}`;
    const errors = form.errors as Record<string, string | undefined>;

    return errors[key];
};

const generatedPayloadPreview = (
    payload: Record<string, unknown> | unknown[] | null,
): string => {
    if (payload === null) {
        return '';
    }

    try {
        return JSON.stringify(payload, null, 2);
    } catch {
        return String(payload);
    }
};

const doRefreshGenerated = () => {
    if (readOnly.value) {
        return;
    }

    router.post(
        refreshGenerated({
            product: props.product.id,
            package: props.package.id,
        }).url,
        {},
        { preserveScroll: true },
    );
};

const submit = () => {
    if (readOnly.value) {
        return;
    }

    form.transform((data) => ({
        ...data,
        product_version_id:
            data.product_version_id === '' ? null : data.product_version_id,
        sections: data.sections.map((section) => ({
            section_key: section.section_key,
            body_markdown: section.body_markdown || null,
            is_applicable: section.is_applicable,
            override_reason: section.is_applicable
                ? null
                : section.override_reason || null,
            sort_order: section.sort_order,
        })),
    })).put(
        update({
            product: props.product.id,
            package: props.package.id,
        }).url,
    );
};
</script>

<template>
    <Head :title="t('products.technical_documentation.edit_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.technical_documentation.edit_title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ statusLabel(props.package.status) }}
                    · {{ props.package.version_label }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button
                    v-if="!readOnly"
                    type="button"
                    variant="outline"
                    @click="doRefreshGenerated"
                >
                    <RefreshCcw class="h-4 w-4" />
                    {{
                        t('products.technical_documentation.refresh_generated')
                    }}
                </Button>
                <Button as-child variant="outline">
                    <Link :href="packagesIndex(props.product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p
            v-if="readOnly"
            class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900 dark:border-amber-900/50 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{ t('products.technical_documentation.read_only_notice') }}
        </p>

        <form class="space-y-8" @submit.prevent="submit">
            <div class="space-y-4">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="title"
                        :help="t('products.technical_documentation.help.title')"
                        required
                    >
                        {{ t('products.technical_documentation.fields.title') }}
                    </FieldLabel>
                    <Input
                        id="title"
                        v-model="form.title"
                        :disabled="readOnly"
                        required
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="version_label"
                            :help="
                                t(
                                    'products.technical_documentation.help.version_label',
                                )
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
                            :disabled="readOnly"
                            required
                        />
                        <InputError :message="form.errors.version_label" />
                    </div>

                    <div class="grid gap-2">
                        <Label>{{
                            t('products.technical_documentation.fields.locale')
                        }}</Label>
                        <Select v-model="form.locale" :disabled="readOnly">
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
                        :disabled="readOnly"
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

                <div
                    v-if="props.package.supersedes_title"
                    class="rounded-md border px-3 py-2 text-sm text-muted-foreground"
                >
                    {{
                        t('products.technical_documentation.fields.supersedes')
                    }}:
                    {{ props.package.supersedes_title }}
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
                        :disabled="readOnly"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-50"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-medium">
                        {{
                            t(
                                'products.technical_documentation.sections_heading',
                            )
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t('products.technical_documentation.sections_help')
                        }}
                    </p>
                </div>

                <div
                    v-for="(section, index) in form.sections"
                    :key="section.section_key"
                    class="space-y-4 border-t border-border pt-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="font-medium">
                                {{ sectionLabel(section.section_key) }}
                            </h3>
                            <p class="text-xs text-muted-foreground">
                                {{ section.section_key }}
                                ·
                                {{ sourceLabel(section.source) }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Label
                                :for="`applicable-${section.section_key}`"
                                class="text-sm"
                            >
                                {{
                                    t(
                                        'products.technical_documentation.fields.is_applicable',
                                    )
                                }}
                            </Label>
                            <Switch
                                :id="`applicable-${section.section_key}`"
                                v-model="section.is_applicable"
                                :disabled="readOnly"
                            />
                        </div>
                    </div>

                    <div v-if="!section.is_applicable" class="grid gap-2">
                        <FieldLabel
                            :html-for="`override-${section.section_key}`"
                            :help="
                                t(
                                    'products.technical_documentation.help.override_reason',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.technical_documentation.fields.override_reason',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            :id="`override-${section.section_key}`"
                            v-model="section.override_reason"
                            :disabled="readOnly"
                        />
                        <InputError
                            :message="sectionError(index, 'override_reason')"
                        />
                    </div>

                    <template v-else>
                        <PolicyBodyField
                            v-if="section.source === 'authored'"
                            v-model="section.body_markdown"
                            :input-id="`section-body-${section.section_key}`"
                            :label="
                                t(
                                    'products.technical_documentation.fields.body',
                                )
                            "
                            :help="
                                t('products.technical_documentation.help.body')
                            "
                            :disabled="readOnly"
                            :error="sectionError(index, 'body_markdown')"
                        />

                        <div
                            v-else
                            class="space-y-3 rounded-md border border-dashed px-3 py-3"
                        >
                            <p class="text-sm text-muted-foreground">
                                {{
                                    section.source === 'linked'
                                        ? t(
                                              'products.technical_documentation.linked_placeholder',
                                          )
                                        : t(
                                              'products.technical_documentation.generated_placeholder',
                                          )
                                }}
                            </p>

                            <p
                                v-if="generatedAtLabel(section.section_key)"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    t(
                                        'products.technical_documentation.generated_at',
                                    )
                                }}:
                                {{
                                    new Date(
                                        generatedAtLabel(
                                            section.section_key,
                                        ) as string,
                                    ).toLocaleString()
                                }}
                            </p>

                            <MarkdownPreview
                                v-if="
                                    section.source === 'generated' &&
                                    generatedMarkdown(section.section_key)
                                "
                                :source="generatedMarkdown(section.section_key)"
                                :empty-label="
                                    t(
                                        'products.technical_documentation.generated_empty',
                                    )
                                "
                            />

                            <pre
                                v-else-if="
                                    generatedPayloadByKey[section.section_key]
                                "
                                class="max-h-48 overflow-auto rounded-md bg-muted/40 p-3 font-mono text-xs"
                                >{{
                                    generatedPayloadPreview(
                                        generatedPayloadByKey[
                                            section.section_key
                                        ] ?? null,
                                    )
                                }}</pre>

                            <PolicyBodyField
                                v-model="section.body_markdown"
                                :input-id="`section-notes-${section.section_key}`"
                                :label="
                                    t(
                                        'products.technical_documentation.fields.supplemental_notes',
                                    )
                                "
                                :help="
                                    t(
                                        'products.technical_documentation.help.supplemental_notes',
                                    )
                                "
                                :disabled="readOnly"
                                :error="sectionError(index, 'body_markdown')"
                            />
                        </div>
                    </template>
                </div>

                <InputError :message="form.errors.sections" />
            </div>

            <div v-if="!readOnly" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>
    </div>
</template>
