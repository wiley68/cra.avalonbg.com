<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    Archive,
    ArrowLeft,
    CheckCircle2,
    FileDown,
    Pencil,
    RefreshCcw,
    Save,
    Send,
    Sparkles,
    Trash2,
} from '@lucide/vue';
import { computed, reactive, ref } from 'vue';
import { toast } from 'vue-sonner';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import PolicyBodyField from '@/components/PolicyBodyField.vue';
import TextDiffViewer from '@/components/TextDiffViewer.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
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
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { index as evidenceIndex } from '@/routes/products/evidence';
import { edit as editSdlRun } from '@/routes/products/sdl';
import { edit as editSecurityInstruction } from '@/routes/products/security-instructions';
import { edit as editTask } from '@/routes/products/tasks';
import {
    aiDraft as suggestAiDraft,
    edit as packagesEdit,
    exportMethod as exportPackage,
    index as packagesIndex,
    publish as publishPackage,
    refreshGenerated,
    retire as retirePackage,
    submitReview,
    update,
} from '@/routes/products/technical-documentation';

type ProductSummary = { id: number; name: string; slug: string };
type VersionOption = { id: number; version_number: string };
type MemberOption = { id: number; name: string };
type ReviewTask = {
    id: number;
    product_id: number;
    title: string;
    status: string;
};
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

type SupersedesSection = {
    source: string;
    body_markdown: string | null;
    generated_payload: Record<string, unknown> | unknown[] | null;
    is_applicable: boolean;
    override_reason: string | null;
};

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
    user_security_instruction_id: number | null;
    sdl_run_id: number | null;
    linked_usi: {
        id: number;
        title: string;
        version_label: string;
        locale: string;
        product_version_id: number | null;
        version_number: string | null;
    } | null;
    linked_sdl: {
        id: number;
        title: string;
        status: string;
        product_version_id: number | null;
        version_number: string | null;
    } | null;
    supersedes_id: number | null;
    supersedes_title: string | null;
    supersedes_sections?: Record<string, SupersedesSection>;
    dependency_delta: DependencyDelta | null;
    sections: SectionPayload[];
};

type DependencyDeltaRow = {
    name: string;
    version: string | null;
    purl: string | null;
    ecosystem: string;
};

type DependencyDeltaChanged = {
    name: string;
    purl: string | null;
    ecosystem: string;
    from_version: string | null;
    to_version: string | null;
};

type DependencyDelta = {
    available: boolean;
    unavailable_reason: string | null;
    parent_version_id: number | null;
    current_version_id: number | null;
    parent_version_number: string | null;
    current_version_number: string | null;
    added: DependencyDeltaRow[];
    removed: DependencyDeltaRow[];
    changed: DependencyDeltaChanged[];
    counts: {
        added: number;
        removed: number;
        changed: number;
        unchanged: number;
    };
    truncated: boolean;
};

type EvidenceFreshness = {
    total: number;
    current: number;
    review_due: number;
    expired: number;
    invalid: number;
    superseded: number;
    stale: number;
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
    aiEnabled: boolean;
    memberOptions: MemberOption[];
    reviewTask: ReviewTask | null;
    evidenceFreshness: EvidenceFreshness;
    published_usi: PublishedUsiOption[];
    sdl_runs: SdlRunOption[];
}>();

const { t } = useTranslations();
const page = usePage();

const showRequestErrors = (errors: Record<string, string>) => {
    const message =
        errors.sections ||
        errors.status ||
        Object.values(errors).find((value) => typeof value === 'string');

    if (message) {
        toast.error(String(message));
    }
};

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
    user_security_instruction_id: (props.package.user_security_instruction_id ??
        '') as number | '',
    sdl_run_id: (props.package.sdl_run_id ?? '') as number | '',
    sections: props.package.sections.map((section) => ({
        section_key: section.section_key,
        source: section.source,
        body_markdown: section.body_markdown ?? '',
        sort_order: section.sort_order,
        is_applicable: section.is_applicable,
        override_reason: section.override_reason ?? '',
    })),
});

const lifecycleError = computed(() => {
    const errors: Record<string, string | undefined> = {
        ...(page.props.errors as Record<string, string | undefined>),
        ...(form.errors as Record<string, string | undefined>),
    };

    return errors['sections'] || errors['status'] || undefined;
});

const showSubmitDialog = ref(false);
const showRetireDialog = ref(false);
const submitForm = useForm({
    assignee_user_id: '' as number | '',
});

type AiSectionDraftState = {
    loading: boolean;
    error: string;
    body_markdown: string;
    disclaimer: string;
};

const aiDrafts = reactive<Record<string, AiSectionDraftState>>({});

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const ensureAiDraftState = (sectionKey: string): AiSectionDraftState => {
    if (!aiDrafts[sectionKey]) {
        aiDrafts[sectionKey] = {
            loading: false,
            error: '',
            body_markdown: '',
            disclaimer: '',
        };
    }

    return aiDrafts[sectionKey];
};

const requestAiDraft = async (index: number): Promise<void> => {
    const section = form.sections[index];
    if (
        !section ||
        readOnly.value ||
        !props.aiEnabled ||
        section.source !== 'authored' ||
        !section.is_applicable
    ) {
        return;
    }

    const state = ensureAiDraftState(section.section_key);
    state.loading = true;
    state.error = '';
    state.body_markdown = '';
    state.disclaimer = '';

    try {
        const response = await fetch(
            suggestAiDraft({
                product: props.product.id,
                package: props.package.id,
            }).url,
            {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    section_key: section.section_key,
                    current_body: section.body_markdown,
                }),
            },
        );

        const payload = (await response.json().catch(() => ({}))) as {
            body_markdown?: string;
            disclaimer?: string;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            const firstError = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : undefined;
            state.error =
                firstError ||
                payload.message ||
                t('products.technical_documentation.ai_draft_error');

            return;
        }

        state.body_markdown = payload.body_markdown ?? '';
        state.disclaimer =
            payload.disclaimer ||
            t('products.technical_documentation.ai_draft_disclaimer');

        if (!state.body_markdown) {
            state.error = t('products.technical_documentation.ai_draft_error');
        }
    } catch {
        state.error = t('products.technical_documentation.ai_draft_error');
    } finally {
        state.loading = false;
    }
};

const applyAiDraft = (index: number): void => {
    const section = form.sections[index];
    if (!section) {
        return;
    }

    const state = aiDrafts[section.section_key];
    if (!state?.body_markdown) {
        return;
    }

    section.body_markdown = state.body_markdown;
    discardAiDraft(section.section_key);
};

const discardAiDraft = (sectionKey: string): void => {
    if (!aiDrafts[sectionKey]) {
        return;
    }

    aiDrafts[sectionKey].body_markdown = '';
    aiDrafts[sectionKey].disclaimer = '';
    aiDrafts[sectionKey].error = '';
    aiDrafts[sectionKey].loading = false;
};

const routeArgs = {
    product: props.product.id,
    package: props.package.id,
};

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

const previousSection = (sectionKey: string): SupersedesSection | null => {
    return props.package.supersedes_sections?.[sectionKey] ?? null;
};

const previousSectionBody = (sectionKey: string): string | null => {
    const previous = previousSection(sectionKey);

    return previous?.body_markdown ?? null;
};

const previousSectionApplicable = (sectionKey: string): boolean | null => {
    const previous = previousSection(sectionKey);

    return previous ? previous.is_applicable : null;
};

const previousGeneratedMarkdown = (sectionKey: string): string => {
    const previous = previousSection(sectionKey);
    const payload = previous?.generated_payload;

    if (
        payload !== null &&
        payload !== undefined &&
        !Array.isArray(payload) &&
        typeof payload === 'object' &&
        typeof payload.markdown === 'string'
    ) {
        return payload.markdown;
    }

    return '';
};

const sectionChangedSinceParent = (sectionKey: string): boolean => {
    return (
        props.package.sections.find(
            (section) => section.section_key === sectionKey,
        )?.changed_since_parent ?? false
    );
};

const changedSections = computed(() =>
    props.package.sections.filter((section) => section.changed_since_parent),
);

const hasSupersedes = computed(() => props.package.supersedes_id !== null);

const dependencyDelta = computed(() => props.package.dependency_delta);

const hasDependencyDeltaChanges = computed(() => {
    const delta = dependencyDelta.value;
    if (!delta?.available) {
        return false;
    }

    return delta.counts.added + delta.counts.removed + delta.counts.changed > 0;
});

const evidenceHref = computed(() => evidenceIndex(props.product.id).url);

const hasStaleEvidence = computed(() => props.evidenceFreshness.stale > 0);

const readOnly = computed(() => !props.package.is_editable || !props.canManage);

const canSubmit = computed(
    () => props.canManage && props.package.status === 'draft',
);

const canPublish = computed(
    () =>
        props.canManage &&
        (props.package.status === 'draft' ||
            props.package.status === 'under_review'),
);

const canRetire = computed(
    () => props.canManage && props.package.status === 'published',
);

const reviewTaskHref = computed(() => {
    if (props.reviewTask === null) {
        return null;
    }

    return editTask({
        product: props.reviewTask.product_id,
        task: props.reviewTask.id,
    }).url;
});

const exportRouteArgs = computed(() => ({
    product: props.product.id,
    package: props.package.id,
}));

const exportMarkdownUrl = computed(
    () =>
        exportPackage({
            ...exportRouteArgs.value,
            format: 'markdown',
        }).url,
);

const exportPdfUrl = computed(
    () =>
        exportPackage({
            ...exportRouteArgs.value,
            format: 'pdf',
        }).url,
);

const exportReleaseUrl = computed(
    () =>
        exportPackage({
            ...exportRouteArgs.value,
            format: 'release',
        }).url,
);

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
        refreshGenerated(routeArgs).url,
        {},
        {
            preserveScroll: true,
            onError: showRequestErrors,
        },
    );
};

const openSubmitDialog = () => {
    submitForm.clearErrors();
    showSubmitDialog.value = true;
};

const doSubmitReview = () => {
    submitForm
        .transform((data) => ({
            assignee_user_id: data.assignee_user_id || null,
        }))
        .post(submitReview(routeArgs).url, {
            preserveScroll: true,
            onError: showRequestErrors,
            onSuccess: () => {
                showSubmitDialog.value = false;
                submitForm.reset();
            },
        });
};

const doPublish = () => {
    router.post(
        publishPackage(routeArgs).url,
        {},
        {
            preserveScroll: true,
            onError: showRequestErrors,
        },
    );
};

const doRetire = () => {
    showRetireDialog.value = false;
    router.post(
        retirePackage(routeArgs).url,
        {},
        {
            preserveScroll: true,
            onError: showRequestErrors,
        },
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
        user_security_instruction_id:
            data.user_security_instruction_id === ''
                ? null
                : data.user_security_instruction_id,
        sdl_run_id: data.sdl_run_id === '' ? null : data.sdl_run_id,
        sections: data.sections.map((section) => ({
            section_key: section.section_key,
            body_markdown: section.body_markdown || null,
            is_applicable: section.is_applicable,
            override_reason: section.is_applicable
                ? null
                : section.override_reason || null,
            sort_order: section.sort_order,
        })),
    })).put(update(routeArgs).url);
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
                    <a :href="exportMarkdownUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{
                            t(
                                'products.technical_documentation.export_markdown',
                            )
                        }}
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportPdfUrl" target="_blank" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{ t('products.technical_documentation.export_pdf') }}
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportReleaseUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{
                            t('products.technical_documentation.export_release')
                        }}
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="packagesIndex(props.product.id)">
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
                @click="openSubmitDialog"
            >
                <Send class="h-4 w-4" />
                {{ t('products.technical_documentation.submit_review') }}
            </Button>
            <Button v-if="reviewTaskHref" as-child variant="outline">
                <Link :href="reviewTaskHref">
                    <Pencil class="h-4 w-4" />
                    {{ t('products.technical_documentation.view_review_task') }}
                </Link>
            </Button>
            <Button
                v-if="canPublish"
                type="button"
                variant="outline"
                @click="doPublish"
            >
                <CheckCircle2 class="h-4 w-4" />
                {{ t('products.technical_documentation.publish') }}
            </Button>
            <Button
                v-if="canRetire"
                type="button"
                variant="outline"
                @click="showRetireDialog = true"
            >
                <Archive class="h-4 w-4" />
                {{ t('products.technical_documentation.retire') }}
            </Button>
        </div>

        <p
            v-if="lifecycleError"
            class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
        >
            {{ lifecycleError }}
        </p>

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
                    v-if="hasSupersedes"
                    class="space-y-2 rounded-md border px-3 py-3 text-sm"
                >
                    <p class="text-muted-foreground">
                        {{
                            t(
                                'products.technical_documentation.fields.supersedes',
                            )
                        }}:
                        {{ props.package.supersedes_title }}
                    </p>
                    <p v-if="changedSections.length > 0" class="font-medium">
                        {{
                            t(
                                'products.technical_documentation.delta_changed_summary',
                                {
                                    count: String(changedSections.length),
                                },
                            )
                        }}
                    </p>
                    <ul
                        v-if="changedSections.length > 0"
                        class="flex flex-wrap gap-2"
                    >
                        <li
                            v-for="section in changedSections"
                            :key="section.section_key"
                        >
                            <a
                                :href="`#section-${section.section_key}`"
                                class="text-xs underline underline-offset-2"
                            >
                                {{ sectionLabel(section.section_key) }}
                            </a>
                        </li>
                    </ul>
                    <p v-else class="text-muted-foreground">
                        {{
                            t(
                                'products.technical_documentation.delta_unchanged_summary',
                            )
                        }}
                    </p>

                    <div
                        v-if="dependencyDelta"
                        class="space-y-2 border-t border-border pt-3"
                    >
                        <p class="font-medium">
                            {{
                                t(
                                    'products.technical_documentation.dependency_delta_title',
                                )
                            }}
                        </p>
                        <p
                            v-if="!dependencyDelta.available"
                            class="text-muted-foreground"
                        >
                            {{
                                dependencyDelta.unavailable_reason ===
                                'same_product_version'
                                    ? t(
                                          'products.technical_documentation.dependency_delta_same_version',
                                      )
                                    : t(
                                          'products.technical_documentation.dependency_delta_needs_versions',
                                      )
                            }}
                        </p>
                        <template v-else>
                            <p class="text-muted-foreground">
                                {{
                                    t(
                                        'products.technical_documentation.dependency_delta_versions',
                                        {
                                            parent:
                                                dependencyDelta.parent_version_number ??
                                                '—',
                                            current:
                                                dependencyDelta.current_version_number ??
                                                '—',
                                        },
                                    )
                                }}
                            </p>
                            <p
                                v-if="hasDependencyDeltaChanges"
                                class="font-medium"
                            >
                                {{
                                    t(
                                        'products.technical_documentation.dependency_delta_summary',
                                        {
                                            added: String(
                                                dependencyDelta.counts.added,
                                            ),
                                            removed: String(
                                                dependencyDelta.counts.removed,
                                            ),
                                            changed: String(
                                                dependencyDelta.counts.changed,
                                            ),
                                        },
                                    )
                                }}
                            </p>
                            <p v-else class="text-muted-foreground">
                                {{
                                    t(
                                        'products.technical_documentation.dependency_delta_unchanged',
                                    )
                                }}
                            </p>
                            <p
                                v-if="dependencyDelta.truncated"
                                class="text-xs text-muted-foreground"
                            >
                                {{
                                    t(
                                        'products.technical_documentation.dependency_delta_truncated',
                                    )
                                }}
                            </p>

                            <div
                                v-if="dependencyDelta.added.length > 0"
                                class="space-y-1"
                            >
                                <p
                                    class="text-xs font-medium text-emerald-700 dark:text-emerald-400"
                                >
                                    {{
                                        t(
                                            'products.technical_documentation.dependency_delta_added',
                                        )
                                    }}
                                </p>
                                <ul
                                    class="space-y-0.5 text-xs text-muted-foreground"
                                >
                                    <li
                                        v-for="row in dependencyDelta.added"
                                        :key="`added-${row.purl ?? row.name}`"
                                    >
                                        + {{ row.name }}
                                        <span v-if="row.version"
                                            >@{{ row.version }}</span
                                        >
                                    </li>
                                </ul>
                            </div>

                            <div
                                v-if="dependencyDelta.removed.length > 0"
                                class="space-y-1"
                            >
                                <p class="text-xs font-medium text-destructive">
                                    {{
                                        t(
                                            'products.technical_documentation.dependency_delta_removed',
                                        )
                                    }}
                                </p>
                                <ul
                                    class="space-y-0.5 text-xs text-muted-foreground"
                                >
                                    <li
                                        v-for="row in dependencyDelta.removed"
                                        :key="`removed-${row.purl ?? row.name}`"
                                    >
                                        − {{ row.name }}
                                        <span v-if="row.version"
                                            >@{{ row.version }}</span
                                        >
                                    </li>
                                </ul>
                            </div>

                            <div
                                v-if="dependencyDelta.changed.length > 0"
                                class="space-y-1"
                            >
                                <p class="text-xs font-medium">
                                    {{
                                        t(
                                            'products.technical_documentation.dependency_delta_changed',
                                        )
                                    }}
                                </p>
                                <ul
                                    class="space-y-0.5 text-xs text-muted-foreground"
                                >
                                    <li
                                        v-for="row in dependencyDelta.changed"
                                        :key="`changed-${row.purl ?? row.name}`"
                                    >
                                        ~ {{ row.name }}:
                                        {{ row.from_version ?? '—' }} →
                                        {{ row.to_version ?? '—' }}
                                    </li>
                                </ul>
                            </div>
                        </template>
                    </div>
                </div>

                <div
                    v-if="hasStaleEvidence"
                    class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-3 text-sm text-destructive"
                >
                    <p>
                        {{
                            t(
                                'products.technical_documentation.stale_evidence_hint',
                                {
                                    stale: String(evidenceFreshness.stale),
                                    total: String(evidenceFreshness.total),
                                    expired: String(evidenceFreshness.expired),
                                    review_due: String(
                                        evidenceFreshness.review_due,
                                    ),
                                },
                            )
                        }}
                    </p>
                    <Link
                        :href="evidenceHref"
                        class="mt-1 inline-flex text-xs font-medium underline underline-offset-2"
                    >
                        {{
                            t(
                                'products.technical_documentation.stale_evidence_link',
                            )
                        }}
                    </Link>
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
                            :disabled="readOnly"
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
                        <p
                            v-if="published_usi.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                t(
                                    'products.technical_documentation.usi_link_none',
                                )
                            }}
                        </p>
                        <Link
                            v-if="props.package.linked_usi"
                            :href="
                                editSecurityInstruction({
                                    product: props.product.id,
                                    instruction: props.package.linked_usi.id,
                                }).url
                            "
                            class="text-sm font-medium underline underline-offset-2"
                        >
                            {{
                                t(
                                    'products.technical_documentation.open_linked_usi',
                                )
                            }}
                        </Link>
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
                            :disabled="readOnly"
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
                        <p
                            v-if="sdl_runs.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{
                                t(
                                    'products.technical_documentation.sdl_link_none',
                                )
                            }}
                        </p>
                        <Link
                            v-if="props.package.linked_sdl"
                            :href="
                                editSdlRun({
                                    product: props.product.id,
                                    sdlRun: props.package.linked_sdl.id,
                                }).url
                            "
                            class="text-sm font-medium underline underline-offset-2"
                        >
                            {{
                                t(
                                    'products.technical_documentation.open_linked_sdl',
                                )
                            }}
                        </Link>
                    </div>
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
                    :id="`section-${section.section_key}`"
                    :key="section.section_key"
                    class="scroll-mt-6 space-y-4 border-t border-border pt-6"
                >
                    <div class="flex items-start justify-between gap-4">
                        <div class="space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="font-medium">
                                    {{ sectionLabel(section.section_key) }}
                                </h3>
                                <Badge
                                    v-if="
                                        hasSupersedes &&
                                        sectionChangedSinceParent(
                                            section.section_key,
                                        )
                                    "
                                    variant="secondary"
                                >
                                    {{
                                        t(
                                            'products.technical_documentation.delta_changed_badge',
                                        )
                                    }}
                                </Badge>
                            </div>
                            <p class="text-xs text-muted-foreground">
                                {{ section.section_key }}
                                ·
                                {{ sourceLabel(section.source) }}
                            </p>
                        </div>
                        <div
                            class="flex flex-wrap items-center justify-end gap-3"
                        >
                            <Button
                                v-if="
                                    !readOnly &&
                                    aiEnabled &&
                                    section.source === 'authored' &&
                                    section.is_applicable
                                "
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    aiDrafts[section.section_key]?.loading
                                "
                                @click="requestAiDraft(index)"
                            >
                                <Sparkles class="h-4 w-4" />
                                {{
                                    aiDrafts[section.section_key]?.loading
                                        ? t(
                                              'products.technical_documentation.ai_draft_loading',
                                          )
                                        : t(
                                              'products.technical_documentation.ai_draft_suggest',
                                          )
                                }}
                            </Button>
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
                    </div>

                    <p
                        v-if="
                            hasSupersedes &&
                            previousSectionApplicable(section.section_key) !==
                                null &&
                            previousSectionApplicable(section.section_key) !==
                                section.is_applicable
                        "
                        class="text-xs text-muted-foreground"
                    >
                        {{
                            t(
                                'products.technical_documentation.diff_applicable_changed',
                                {
                                    previous: previousSectionApplicable(
                                        section.section_key,
                                    )
                                        ? t('common.yes')
                                        : t('common.no'),
                                    current: section.is_applicable
                                        ? t('common.yes')
                                        : t('common.no'),
                                },
                            )
                        }}
                    </p>

                    <div
                        v-if="aiDrafts[section.section_key]?.error"
                        class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                    >
                        {{ aiDrafts[section.section_key]?.error }}
                    </div>

                    <div
                        v-if="
                            section.source === 'authored' &&
                            aiDrafts[section.section_key]?.body_markdown
                        "
                        class="space-y-3 rounded-md border border-border bg-muted/30 p-4"
                    >
                        <p class="text-sm text-muted-foreground">
                            {{
                                aiDrafts[section.section_key]?.disclaimer ||
                                t(
                                    'products.technical_documentation.ai_draft_disclaimer',
                                )
                            }}
                        </p>
                        <MarkdownPreview
                            :source="
                                aiDrafts[section.section_key]?.body_markdown ||
                                ''
                            "
                            :empty-label="
                                t(
                                    'products.technical_documentation.ai_draft_empty',
                                )
                            "
                        />
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                size="sm"
                                @click="applyAiDraft(index)"
                            >
                                <CheckCircle2 class="h-4 w-4" />
                                {{
                                    t(
                                        'products.technical_documentation.ai_draft_apply',
                                    )
                                }}
                            </Button>
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                @click="discardAiDraft(section.section_key)"
                            >
                                <Trash2 class="h-4 w-4" />
                                {{
                                    t(
                                        'products.technical_documentation.ai_draft_discard',
                                    )
                                }}
                            </Button>
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
                            :previous-body="
                                previousSectionBody(section.section_key)
                            "
                            :previous-label="props.package.supersedes_title"
                            :current-label="form.version_label"
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

                            <Tabs
                                v-if="
                                    (section.source === 'generated' ||
                                        section.source === 'linked') &&
                                    (generatedMarkdown(section.section_key) ||
                                        previousGeneratedMarkdown(
                                            section.section_key,
                                        ))
                                "
                                default-value="preview"
                                class="w-full"
                            >
                                <TabsList>
                                    <TabsTrigger value="preview">
                                        {{ t('common.markdown.preview') }}
                                    </TabsTrigger>
                                    <TabsTrigger
                                        v-if="
                                            previousGeneratedMarkdown(
                                                section.section_key,
                                            )
                                        "
                                        value="diff"
                                    >
                                        {{ t('common.markdown.diff') }}
                                    </TabsTrigger>
                                </TabsList>
                                <TabsContent value="preview" class="mt-3">
                                    <MarkdownPreview
                                        :source="
                                            generatedMarkdown(
                                                section.section_key,
                                            )
                                        "
                                        :empty-label="
                                            section.source === 'linked'
                                                ? t(
                                                      'products.technical_documentation.linked_usi_empty',
                                                  )
                                                : t(
                                                      'products.technical_documentation.generated_empty',
                                                  )
                                        "
                                    />
                                </TabsContent>
                                <TabsContent
                                    v-if="
                                        previousGeneratedMarkdown(
                                            section.section_key,
                                        )
                                    "
                                    value="diff"
                                    class="mt-3"
                                >
                                    <TextDiffViewer
                                        :previous="
                                            previousGeneratedMarkdown(
                                                section.section_key,
                                            )
                                        "
                                        :current="
                                            generatedMarkdown(
                                                section.section_key,
                                            )
                                        "
                                        :previous-label="
                                            props.package.supersedes_title ??
                                            undefined
                                        "
                                        :current-label="form.version_label"
                                    />
                                </TabsContent>
                            </Tabs>

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
                                :previous-body="
                                    previousSectionBody(section.section_key)
                                "
                                :previous-label="props.package.supersedes_title"
                                :current-label="form.version_label"
                            />
                        </div>
                    </template>
                </div>

                <InputError :message="lifecycleError" />
            </div>

            <div v-if="!readOnly" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <Dialog
            :open="showSubmitDialog"
            @update:open="
                (open: boolean) => {
                    showSubmitDialog = open;
                }
            "
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            t(
                                'products.technical_documentation.submit_review_title',
                            )
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'products.technical_documentation.submit_review_help',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="grid min-w-0 gap-2 py-2">
                    <FieldLabel
                        html-for="assignee_user_id"
                        :help="
                            t(
                                'products.technical_documentation.help.assignee_user_id',
                            )
                        "
                    >
                        {{
                            t(
                                'products.technical_documentation.fields.assignee',
                            )
                        }}
                    </FieldLabel>
                    <Select v-model="submitForm.assignee_user_id">
                        <SelectTrigger id="assignee_user_id" class="w-full">
                            <SelectValue
                                :placeholder="
                                    t(
                                        'products.technical_documentation.select_assignee',
                                    )
                                "
                            />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="member in memberOptions"
                                :key="member.id"
                                :value="String(member.id)"
                            >
                                {{ member.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="submitForm.errors.assignee_user_id" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showSubmitDialog = false"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="submitForm.processing"
                        @click="doSubmitReview"
                    >
                        <Send class="h-4 w-4" />
                        {{
                            t('products.technical_documentation.submit_review')
                        }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <AppAlertDialog
            v-model:open="showRetireDialog"
            :title="t('products.technical_documentation.confirm_retire_title')"
            :description="t('products.technical_documentation.confirm_retire')"
            @confirm="doRetire"
            @cancel="showRetireDialog = false"
        />
    </div>
</template>
