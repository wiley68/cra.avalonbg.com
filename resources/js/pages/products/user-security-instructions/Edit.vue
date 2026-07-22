<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    ChevronDown,
    Eye,
    FileDown,
    FileUp,
    Pencil,
    Save,
    Send,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import PolicyBodyField from '@/components/PolicyBodyField.vue';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
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
import { edit as editEvidence } from '@/routes/products/evidence';
import {
    edit as instructionsEdit,
    exportMethod as instructionsExport,
    index as instructionsIndex,
    publish as publishInstruction,
    publishEvidence,
    retire as retireInstruction,
    submitReview,
    update,
} from '@/routes/products/security-instructions';

type ProductSummary = { id: number; name: string; slug: string };
type VersionOption = { id: number; version_number: string };

type SectionPayload = {
    id: number;
    section_key: string;
    title_override: string | null;
    body: string;
    sort_order: number;
    is_applicable: boolean;
};

type InstructionDetail = {
    id: number;
    title: string;
    status: string;
    version_label: string;
    locale: string;
    notes: string | null;
    is_editable: boolean;
    published_at: string | null;
    published_by_name: string | null;
    evidence_id: number | null;
    evidence_title: string | null;
    product_version_id: number | null;
    product_version_number: string | null;
    sections: SectionPayload[];
};

const props = defineProps<{
    product: ProductSummary;
    instruction: InstructionDetail;
    versions: VersionOption[];
    canManage: boolean;
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
        title: props.instruction.title,
        href: instructionsEdit({
            product: props.product.id,
            instruction: props.instruction.id,
        }),
    },
]);

const form = useForm({
    title: props.instruction.title,
    version_label: props.instruction.version_label,
    locale: props.instruction.locale,
    notes: props.instruction.notes ?? '',
    product_version_id: (props.instruction.product_version_id ?? '') as
        number | '',
    sections: props.instruction.sections.map((section) => ({
        section_key: section.section_key,
        title_override: section.title_override ?? '',
        body: section.body,
        sort_order: section.sort_order,
        is_applicable: section.is_applicable,
    })),
});

const showRetireDialog = ref(false);
const showPublishEvidenceDialog = ref(false);
const showDocumentPreview = ref(false);

const canEdit = computed(
    () => props.canManage && props.instruction.is_editable,
);

const canSubmit = computed(
    () => props.canManage && props.instruction.status === 'draft',
);

const canPublish = computed(
    () =>
        props.canManage &&
        (props.instruction.status === 'draft' ||
            props.instruction.status === 'under_review'),
);

const canRetire = computed(
    () => props.canManage && props.instruction.status === 'published',
);

const canPublishEvidence = computed(
    () =>
        props.canManage &&
        props.instruction.status === 'published' &&
        props.instruction.evidence_id === null,
);

const evidenceHref = computed(() => {
    if (props.instruction.evidence_id === null) {
        return null;
    }

    return editEvidence({
        product: props.product.id,
        evidence: props.instruction.evidence_id,
    }).url;
});

const canExport = computed(
    () =>
        props.canManage ||
        props.instruction.status === 'published' ||
        props.instruction.status === 'retired',
);

const exportHtmlUrl = computed(
    () =>
        instructionsExport({
            product: props.product.id,
            instruction: props.instruction.id,
            format: 'html',
        }).url,
);

const exportPdfUrl = computed(
    () =>
        instructionsExport({
            product: props.product.id,
            instruction: props.instruction.id,
            format: 'pdf',
        }).url,
);

const exportReadmeUrl = computed(
    () =>
        instructionsExport({
            product: props.product.id,
            instruction: props.instruction.id,
            format: 'readme',
        }).url,
);

const exportReleaseUrl = computed(
    () =>
        instructionsExport({
            product: props.product.id,
            instruction: props.instruction.id,
            format: 'release',
        }).url,
);

const statusLabel = (value: string): string => {
    const key = `products.user_security_instructions.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const localeLabel = (value: string): string => {
    const key = `products.user_security_instructions.locales.${value}`;
    const translated = t(key);

    return translated === key ? value.toUpperCase() : translated;
};

const sectionLabel = (key: string): string => {
    const translationKey = `products.user_security_instructions.sections.${key}`;
    const translated = t(translationKey);

    return translated === translationKey ? key : translated;
};

const documentPreviewMarkdown = computed((): string => {
    const lines: string[] = [];
    const title = form.title.trim() || props.instruction.title;
    lines.push(`# ${title}`);
    lines.push('');

    for (const section of form.sections) {
        const heading =
            section.title_override.trim() || sectionLabel(section.section_key);
        lines.push(`## ${heading}`);
        lines.push('');

        if (!section.is_applicable) {
            lines.push(
                `*${t('products.user_security_instructions.export.not_applicable')}*`,
            );
        } else if (section.body.trim() === '') {
            lines.push(
                `*${t('products.user_security_instructions.export.empty_section')}*`,
            );
        } else {
            lines.push(section.body.trim());
        }

        lines.push('');
    }

    return `${lines.join('\n').trim()}\n`;
});

const sectionError = (index: number, field: string): string | undefined => {
    const key = `sections.${index}.${field}`;
    const errors = form.errors as Record<string, string | undefined>;

    return errors[key];
};

const routeArgs = {
    product: props.product.id,
    instruction: props.instruction.id,
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_version_id:
            data.product_version_id === '' ? null : data.product_version_id,
        sections: data.sections.map((section) => ({
            ...section,
            title_override: section.title_override || null,
        })),
    })).put(update(routeArgs).url);
};

const doSubmitReview = () => {
    router.post(submitReview(routeArgs).url, {}, { preserveScroll: true });
};

const doPublish = () => {
    router.post(
        publishInstruction(routeArgs).url,
        {},
        { preserveScroll: true },
    );
};

const doRetire = () => {
    showRetireDialog.value = false;
    router.post(retireInstruction(routeArgs).url, {}, { preserveScroll: true });
};

const doPublishEvidence = () => {
    showPublishEvidenceDialog.value = false;
    router.post(publishEvidence(routeArgs).url, {}, { preserveScroll: true });
};
</script>

<template>
    <Head :title="instruction.title" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ instruction.title }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ statusLabel(instruction.status) }}
                    ·
                    {{ instruction.version_label }}
                    ·
                    {{ localeLabel(instruction.locale) }}
                </p>
                <p
                    v-if="instruction.published_at"
                    class="text-sm text-muted-foreground"
                >
                    {{
                        t(
                            'products.user_security_instructions.fields.published_at',
                        )
                    }}:
                    {{ new Date(instruction.published_at).toLocaleString() }}
                    <span v-if="instruction.published_by_name">
                        ({{ instruction.published_by_name }})
                    </span>
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button v-if="canExport" as-child variant="outline">
                    <a :href="exportHtmlUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{
                            t('products.user_security_instructions.export_html')
                        }}
                    </a>
                </Button>
                <Button v-if="canExport" as-child variant="outline">
                    <a :href="exportPdfUrl" target="_blank" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{
                            t('products.user_security_instructions.export_pdf')
                        }}
                    </a>
                </Button>
                <Button v-if="canExport" as-child variant="outline">
                    <a :href="exportReadmeUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{
                            t(
                                'products.user_security_instructions.export_readme',
                            )
                        }}
                    </a>
                </Button>
                <Button v-if="canExport" as-child variant="outline">
                    <a :href="exportReleaseUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{
                            t(
                                'products.user_security_instructions.export_release',
                            )
                        }}
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="instructionsIndex(props.product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div
            v-if="canManage"
            class="flex flex-wrap gap-2 rounded-lg border p-3"
        >
            <Button
                v-if="canSubmit"
                type="button"
                variant="outline"
                @click="doSubmitReview"
            >
                <Send class="h-4 w-4" />
                {{ t('products.user_security_instructions.submit_review') }}
            </Button>
            <Button
                v-if="canPublish"
                type="button"
                variant="outline"
                @click="doPublish"
            >
                <CheckCircle2 class="h-4 w-4" />
                {{ t('products.user_security_instructions.publish') }}
            </Button>
            <Button
                v-if="canPublishEvidence"
                type="button"
                variant="outline"
                @click="showPublishEvidenceDialog = true"
            >
                <FileUp class="h-4 w-4" />
                {{ t('products.user_security_instructions.publish_evidence') }}
            </Button>
            <Button v-if="evidenceHref" as-child variant="outline">
                <Link :href="evidenceHref">
                    <Pencil class="h-4 w-4" />
                    {{ t('products.user_security_instructions.view_evidence') }}
                </Link>
            </Button>
            <Button
                v-if="canRetire"
                type="button"
                variant="outline"
                @click="showRetireDialog = true"
            >
                <Archive class="h-4 w-4" />
                {{ t('products.user_security_instructions.retire') }}
            </Button>
        </div>

        <p
            v-if="!canEdit"
            class="rounded-md border border-border px-3 py-2 text-sm text-muted-foreground"
        >
            {{ t('products.user_security_instructions.read_only_notice') }}
        </p>

        <form class="space-y-8" @submit.prevent="submit">
            <div class="space-y-4">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="title"
                        :help="
                            t('products.user_security_instructions.help.title')
                        "
                        required
                    >
                        {{
                            t(
                                'products.user_security_instructions.fields.title',
                            )
                        }}
                    </FieldLabel>
                    <Input
                        id="title"
                        v-model="form.title"
                        :disabled="!canEdit"
                        required
                    />
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
                        :disabled="!canEdit"
                        required
                    />
                    <InputError :message="form.errors.version_label" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="product_version_id"
                        :help="
                            t(
                                'products.user_security_instructions.help.product_version',
                            )
                        "
                    >
                        {{
                            t(
                                'products.user_security_instructions.fields.product_version',
                            )
                        }}
                    </FieldLabel>
                    <Select
                        :model-value="
                            form.product_version_id === ''
                                ? '__none__'
                                : String(form.product_version_id)
                        "
                        :disabled="!canEdit"
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
                                        'products.user_security_instructions.product_wide',
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
                        t('products.user_security_instructions.fields.locale')
                    }}</Label>
                    <Select v-model="form.locale" :disabled="!canEdit">
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
                        :help="
                            t('products.user_security_instructions.help.notes')
                        "
                    >
                        {{
                            t(
                                'products.user_security_instructions.fields.notes',
                            )
                        }}
                    </FieldLabel>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        rows="3"
                        :disabled="!canEdit"
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm disabled:opacity-60"
                    />
                    <InputError :message="form.errors.notes" />
                </div>
            </div>

            <div class="space-y-6">
                <div>
                    <h2 class="text-lg font-medium">
                        {{
                            t(
                                'products.user_security_instructions.sections_heading',
                            )
                        }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{
                            t(
                                'products.user_security_instructions.sections_help',
                            )
                        }}
                    </p>
                </div>

                <Collapsible
                    v-model:open="showDocumentPreview"
                    class="rounded-lg border"
                >
                    <CollapsibleTrigger as-child>
                        <Button
                            type="button"
                            variant="ghost"
                            class="flex h-auto w-full items-center justify-between gap-2 px-4 py-3"
                        >
                            <span
                                class="flex items-center gap-2 text-sm font-medium"
                            >
                                <Eye class="h-4 w-4" />
                                {{
                                    t(
                                        'products.user_security_instructions.document_preview',
                                    )
                                }}
                            </span>
                            <ChevronDown
                                class="h-4 w-4 shrink-0 transition-transform"
                                :class="showDocumentPreview ? 'rotate-180' : ''"
                            />
                        </Button>
                    </CollapsibleTrigger>
                    <CollapsibleContent class="space-y-2 border-t px-4 py-3">
                        <p class="text-sm text-muted-foreground">
                            {{
                                t(
                                    'products.user_security_instructions.document_preview_help',
                                )
                            }}
                        </p>
                        <MarkdownPreview
                            :source="documentPreviewMarkdown"
                            :empty-label="
                                t(
                                    'products.user_security_instructions.document_preview_empty',
                                )
                            "
                        />
                    </CollapsibleContent>
                </Collapsible>

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
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Label
                                :for="`applicable-${section.section_key}`"
                                class="text-sm"
                            >
                                {{
                                    t(
                                        'products.user_security_instructions.fields.is_applicable',
                                    )
                                }}
                            </Label>
                            <Switch
                                :id="`applicable-${section.section_key}`"
                                v-model="section.is_applicable"
                                :disabled="!canEdit"
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            :html-for="`title-override-${section.section_key}`"
                            :help="
                                t(
                                    'products.user_security_instructions.help.title_override',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.user_security_instructions.fields.title_override',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            :id="`title-override-${section.section_key}`"
                            v-model="section.title_override"
                            :disabled="!canEdit || !section.is_applicable"
                        />
                        <InputError
                            :message="sectionError(index, 'title_override')"
                        />
                    </div>

                    <PolicyBodyField
                        v-model="section.body"
                        :input-id="`section-body-${section.section_key}`"
                        :label="
                            t('products.user_security_instructions.fields.body')
                        "
                        :help="
                            t('products.user_security_instructions.help.body')
                        "
                        :disabled="!canEdit || !section.is_applicable"
                        :error="sectionError(index, 'body')"
                    />
                </div>
                <InputError :message="form.errors.sections" />
            </div>

            <div v-if="canEdit" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showRetireDialog"
            :title="
                t('products.user_security_instructions.confirm_retire_title')
            "
            :description="
                t('products.user_security_instructions.confirm_retire')
            "
            @confirm="doRetire"
            @cancel="showRetireDialog = false"
        />

        <AppAlertDialog
            v-model:open="showPublishEvidenceDialog"
            :title="
                t(
                    'products.user_security_instructions.confirm_publish_evidence_title',
                )
            "
            :description="
                t(
                    'products.user_security_instructions.confirm_publish_evidence',
                )
            "
            @confirm="doPublishEvidence"
            @cancel="showPublishEvidenceDialog = false"
        />
    </div>
</template>
