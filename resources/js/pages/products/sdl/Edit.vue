<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    FileDown,
    FileText,
    Link2,
    Plus,
    RefreshCw,
    Save,
    ShieldCheck,
    Sparkles,
} from '@lucide/vue';
import { computed, reactive, ref, watch } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import MarkdownPreview from '@/components/MarkdownPreview.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    aiDraft as suggestSdlAiDraft,
    approve as approveSdlRun,
    edit as productSdlEdit,
    exportMethod as exportSdlRun,
    index as productSdlIndex,
    linkExternalEvidence as linkSdlExternalEvidence,
    revokeApproval as revokeSdlApproval,
    update,
} from '@/routes/products/sdl';
import { update as updateSdlStage } from '@/routes/products/sdl/stages';
import { sync as syncRepository } from '@/routes/products/repository';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { edit as editTask } from '@/routes/products/tasks';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type EvidenceOption = {
    id: number;
    title: string;
    type?: string;
    source?: string | null;
    collected_at?: string | null;
};
type GitEvidenceOption = {
    id: number;
    title: string;
    source: string | null;
    collected_at: string | null;
    checksum_short: string | null;
};
type GitSuggestionItem = {
    kind: 'snapshot' | 'ci_url' | string;
    evidence_id: number | null;
    title: string;
    url: string | null;
    source: string | null;
    checksum_short: string | null;
    collected_at: string | null;
    suggested_stages: string[];
    already_on_run: boolean;
    ci_conclusion: string | null;
};
type GitSuggestions = {
    synced_at: string | null;
    has_error: boolean;
    items: GitSuggestionItem[];
};
type RepositoryPayload = {
    id: number;
    full_name: string;
    remote_url: string;
    default_branch: string | null;
    last_synced_at: string | null;
    last_sync_summary: {
        error?: string;
        evidence_id?: number;
        ci?: {
            status?: string;
            conclusion?: string | null;
            workflow_name?: string | null;
            html_url?: string | null;
        };
    } | null;
};
type ProductSummary = { id: number; name: string; slug: string };
type ExceptionTask = {
    id: number;
    product_id: number;
    title: string;
    status: string;
};
type StageException = {
    id: number;
    owner_user_id: number;
    owner_name: string | null;
    expires_at: string;
    is_expired: boolean;
    task: ExceptionTask | null;
};
type StageEntry = {
    id: number | null;
    stage: string;
    status: string;
    completed_at: string | null;
    completed_by: number | null;
    completed_by_name: string | null;
    notes: string | null;
    evidence_ids: number[];
    exception: StageException | null;
};
type StageDraft = {
    status: string;
    notes: string;
    evidence_ids: number[];
    exception_owner_user_id: number | '';
    exception_expires_at: string;
};
type SdlRunDetail = {
    id: number;
    title: string;
    status: string;
    current_stage: string;
    product_version_id: number | null;
    version_number: string | null;
    owner_user_id: number | null;
    notes: string | null;
    approved_at: string | null;
    approved_by_name: string | null;
    is_terminal: boolean;
    is_approved: boolean;
    can_approve: boolean;
    evidence_ids: number[];
    stage_entries: StageEntry[];
};

const props = defineProps<{
    product: ProductSummary;
    run: SdlRunDetail;
    members: Member[];
    versions: VersionOption[];
    evidence: EvidenceOption[];
    repository: RepositoryPayload | null;
    git_evidence: GitEvidenceOption[];
    git_suggestions: GitSuggestions;
    canManage: boolean;
    aiEnabled: boolean;
    stage_note_templates: Record<string, string>;
    template_locale: string;
    options: {
        statuses: string[];
        stages: string[];
        stage_statuses: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.sdl.index_title',
        href: productSdlIndex(props.product.id),
    },
    {
        titleKey: 'products.sdl.edit_title',
        href: productSdlEdit({
            product: props.product.id,
            sdlRun: props.run.id,
        }),
    },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    title: props.run.title,
    status: props.run.status,
    current_stage: props.run.current_stage,
    product_version_id: (props.run.product_version_id ?? '') as number | '',
    owner_user_id: (props.run.owner_user_id ?? '') as number | '',
    notes: props.run.notes ?? '',
    evidence_ids: [...props.run.evidence_ids],
});

const buildStageDrafts = (entries: StageEntry[]): Record<string, StageDraft> =>
    Object.fromEntries(
        entries.map((entry) => [
            entry.stage,
            {
                status: entry.status,
                notes: entry.notes ?? '',
                evidence_ids: [...(entry.evidence_ids ?? [])],
                exception_owner_user_id:
                    (entry.exception?.owner_user_id as number | undefined) ??
                    '',
                exception_expires_at: entry.exception?.expires_at ?? '',
            },
        ]),
    );

const stageDrafts = reactive<Record<string, StageDraft>>(
    buildStageDrafts(props.run.stage_entries),
);
const savingStage = ref<string | null>(null);
const stageErrors = reactive<Record<string, Record<string, string>>>({});
const approving = ref(false);
const revoking = ref(false);
const showRevokeDialog = ref(false);
const showTemplateDialog = ref(false);
const templateStagePending = ref<string | null>(null);
const syncingRepository = ref(false);
const linkingExternal = ref(false);
const externalUrl = ref('');
const externalTitle = ref('');
const externalStage = ref('');
const externalErrors = reactive<{
    url?: string;
    title?: string;
    stage?: string;
}>({});

type AiStageDraftState = {
    loading: boolean;
    error: string;
    notes_markdown: string;
    disclaimer: string;
};

const emptyAiStageDraft = (): AiStageDraftState => ({
    loading: false,
    error: '',
    notes_markdown: '',
    disclaimer: '',
});

const aiStageDrafts = reactive<Record<string, AiStageDraftState>>(
    Object.fromEntries(
        props.run.stage_entries.map((entry) => [
            entry.stage,
            emptyAiStageDraft(),
        ]),
    ),
);

const ensureAiStageDraft = (stage: string): AiStageDraftState => {
    if (!aiStageDrafts[stage]) {
        aiStageDrafts[stage] = emptyAiStageDraft();
    }

    return aiStageDrafts[stage];
};

const xsrfToken = (): string => {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);

    return match ? decodeURIComponent(match[1]) : '';
};

const requestAiStageDraft = async (stage: string): Promise<void> => {
    if (!canEdit.value || !props.aiEnabled) {
        return;
    }

    const draft = ensureAiStageDraft(stage);
    draft.loading = true;
    draft.error = '';
    draft.notes_markdown = '';
    draft.disclaimer = '';

    try {
        const response = await fetch(
            suggestSdlAiDraft({
                product: props.product.id,
                sdlRun: props.run.id,
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
                    stage,
                    current_notes: stageDrafts[stage]?.notes ?? '',
                }),
            },
        );

        const payload = (await response.json().catch(() => ({}))) as {
            notes_markdown?: string;
            disclaimer?: string;
            message?: string;
            errors?: Record<string, string[]>;
        };

        if (!response.ok) {
            const firstError = payload.errors
                ? Object.values(payload.errors).flat()[0]
                : undefined;
            draft.error =
                firstError ||
                payload.message ||
                t('products.sdl.ai_draft_error');

            return;
        }

        draft.notes_markdown = payload.notes_markdown ?? '';
        draft.disclaimer =
            payload.disclaimer || t('products.sdl.ai_draft_disclaimer');

        if (!draft.notes_markdown) {
            draft.error = t('products.sdl.ai_draft_error');
        }
    } catch {
        draft.error = t('products.sdl.ai_draft_error');
    } finally {
        draft.loading = false;
    }
};

const applyAiStageDraft = (stage: string): void => {
    const draft = aiStageDrafts[stage];

    if (!draft?.notes_markdown || !stageDrafts[stage]) {
        return;
    }

    stageDrafts[stage].notes = draft.notes_markdown;
    discardAiStageDraft(stage);
};

const discardAiStageDraft = (stage: string): void => {
    const draft = ensureAiStageDraft(stage);
    draft.notes_markdown = '';
    draft.disclaimer = '';
    draft.error = '';
    draft.loading = false;
};

const isLocked = computed(
    () => props.run.is_approved || props.run.status === 'approved',
);

const editableStatuses = computed(() =>
    props.options.statuses.filter(
        (status) => status !== 'approved' || isLocked.value,
    ),
);

const canEdit = computed(() => props.canManage && !isLocked.value);

const exportMarkdownUrl = computed(
    () =>
        exportSdlRun({
            product: props.product.id,
            sdlRun: props.run.id,
            format: 'markdown',
        }).url,
);

const exportPdfUrl = computed(
    () =>
        exportSdlRun({
            product: props.product.id,
            sdlRun: props.run.id,
            format: 'pdf',
        }).url,
);

watch(
    () => props.run.stage_entries,
    (entries) => {
        Object.assign(stageDrafts, buildStageDrafts(entries));
    },
    { deep: true },
);

watch(
    () => props.run.evidence_ids,
    (ids) => {
        form.evidence_ids = [...ids];
    },
);

const submit = () => {
    if (!canEdit.value) {
        return;
    }

    form.transform((data) => ({
        ...data,
        product_version_id: data.product_version_id || null,
        owner_user_id: data.owner_user_id || null,
    })).put(
        update({
            product: props.product.id,
            sdlRun: props.run.id,
        }).url,
    );
};

const saveStage = (stage: string) => {
    if (!canEdit.value) {
        return;
    }

    const draft = stageDrafts[stage];

    if (!draft) {
        return;
    }

    savingStage.value = stage;
    delete stageErrors[stage];

    router.put(
        updateSdlStage({
            product: props.product.id,
            sdlRun: props.run.id,
            stage,
        }).url,
        {
            status: draft.status,
            notes: draft.notes || null,
            evidence_ids: draft.evidence_ids,
            ...(draft.status === 'exception'
                ? {
                      exception_owner_user_id:
                          draft.exception_owner_user_id || null,
                      exception_expires_at: draft.exception_expires_at || null,
                  }
                : {}),
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                stageErrors[stage] = errors as Record<string, string>;
            },
            onFinish: () => {
                savingStage.value = null;
            },
        },
    );
};

const toggleRunEvidence = (id: number, checked: boolean) => {
    if (checked) {
        if (!form.evidence_ids.includes(id)) {
            form.evidence_ids.push(id);
        }

        return;
    }

    form.evidence_ids = form.evidence_ids.filter((value) => value !== id);
};

const toggleStageEvidence = (stage: string, id: number, checked: boolean) => {
    const draft = stageDrafts[stage];

    if (!draft) {
        return;
    }

    if (checked) {
        if (!draft.evidence_ids.includes(id)) {
            draft.evidence_ids.push(id);
        }

        return;
    }

    draft.evidence_ids = draft.evidence_ids.filter((value) => value !== id);
};

const approveRun = () => {
    if (!props.canManage || !props.run.can_approve || isLocked.value) {
        return;
    }

    approving.value = true;

    router.post(
        approveSdlRun({
            product: props.product.id,
            sdlRun: props.run.id,
        }).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                approving.value = false;
            },
        },
    );
};

const confirmRevoke = () => {
    if (!props.canManage || !isLocked.value) {
        return;
    }

    showRevokeDialog.value = false;
    revoking.value = true;

    router.post(
        revokeSdlApproval({
            product: props.product.id,
            sdlRun: props.run.id,
        }).url,
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                revoking.value = false;
            },
        },
    );
};

const hasStageTemplate = (stage: string): boolean =>
    Object.prototype.hasOwnProperty.call(props.stage_note_templates, stage);

const fillStageTemplate = (stage: string): void => {
    const template = props.stage_note_templates[stage];

    if (!template || !stageDrafts[stage]) {
        return;
    }

    stageDrafts[stage].notes = template;
};

const requestApplyTemplate = (stage: string): void => {
    if (!canEdit.value || !hasStageTemplate(stage)) {
        return;
    }

    const current = stageDrafts[stage]?.notes?.trim() ?? '';

    if (current !== '') {
        templateStagePending.value = stage;
        showTemplateDialog.value = true;

        return;
    }

    fillStageTemplate(stage);
};

const confirmApplyTemplate = (): void => {
    const stage = templateStagePending.value;
    showTemplateDialog.value = false;
    templateStagePending.value = null;

    if (stage) {
        fillStageTemplate(stage);
    }
};

const cancelApplyTemplate = (): void => {
    showTemplateDialog.value = false;
    templateStagePending.value = null;
};

const ciLabel = (summary: RepositoryPayload['last_sync_summary']): string => {
    if (!summary?.ci) {
        return t('products.repository.ci_unknown');
    }

    return (
        summary.ci.conclusion ||
        summary.ci.status ||
        t('products.repository.ci_unknown')
    );
};

const syncRepositoryNow = (): void => {
    if (!canEdit.value || !props.repository) {
        return;
    }

    syncingRepository.value = true;
    router.post(
        syncRepository.url(props.product.id),
        {},
        {
            preserveScroll: true,
            onFinish: () => {
                syncingRepository.value = false;
            },
        },
    );
};

const attachGitEvidence = (id: number): void => {
    if (!canEdit.value) {
        return;
    }

    toggleRunEvidence(id, true);
};

const attachGitSuggestionToRun = (item: GitSuggestionItem): void => {
    if (!canEdit.value || item.evidence_id === null) {
        return;
    }

    attachGitEvidence(item.evidence_id);
};

const attachGitSuggestionToStage = (
    item: GitSuggestionItem,
    stage: string,
): void => {
    if (!canEdit.value || item.evidence_id === null) {
        return;
    }

    attachGitEvidence(item.evidence_id);
    toggleStageEvidence(stage, item.evidence_id, true);
};

const suggestionAlreadyOnStage = (
    item: GitSuggestionItem,
    stage: string,
): boolean => {
    if (item.evidence_id === null || !stageDrafts[stage]) {
        return false;
    }

    return stageDrafts[stage].evidence_ids.includes(item.evidence_id);
};

const suggestionAttachedToRun = (item: GitSuggestionItem): boolean => {
    if (item.kind === 'snapshot' && item.evidence_id !== null) {
        return form.evidence_ids.includes(item.evidence_id);
    }

    return item.already_on_run;
};

const prefillExternalFromSuggestion = (item: GitSuggestionItem): void => {
    if (!canEdit.value || !item.url) {
        return;
    }

    externalUrl.value = item.url;
    externalTitle.value = item.title;
    externalStage.value = item.suggested_stages[0] ?? '';

    document.getElementById('git-url')?.scrollIntoView({
        behavior: 'smooth',
        block: 'center',
    });
};

const submitExternalLink = (): void => {
    if (!canEdit.value) {
        return;
    }

    linkingExternal.value = true;
    Object.keys(externalErrors).forEach((key) => {
        delete externalErrors[key as keyof typeof externalErrors];
    });

    router.post(
        linkSdlExternalEvidence({
            product: props.product.id,
            sdlRun: props.run.id,
        }).url,
        {
            url: externalUrl.value,
            title: externalTitle.value || null,
            stage: externalStage.value || null,
        },
        {
            preserveScroll: true,
            onError: (errors) => {
                Object.assign(externalErrors, errors);
            },
            onSuccess: () => {
                externalUrl.value = '';
                externalTitle.value = '';
                externalStage.value = '';
            },
            onFinish: () => {
                linkingExternal.value = false;
            },
        },
    );
};

const evidenceLabel = (item: EvidenceOption): string => {
    const parts = [item.title];

    if (item.type) {
        parts.push(`[${item.type}]`);
    }

    return parts.join(' ');
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.sdl.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
};

const stageCompletedLabel = (entry: StageEntry): string => {
    if (!entry.completed_at) {
        return '';
    }

    let label = t('products.sdl.stage_completed_meta', {
        when: formatDateTime(entry.completed_at),
    });

    if (entry.completed_by_name) {
        label += ` ${t('products.sdl.stage_completed_by', {
            name: entry.completed_by_name,
        })}`;
    }

    return label;
};

const exceptionTaskHref = (entry: StageEntry): string | null => {
    const task = entry.exception?.task;

    if (!task) {
        return null;
    }

    return editTask({
        product: task.product_id,
        task: task.id,
    }).url;
};
</script>

<template>
    <Head :title="t('products.sdl.edit_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.sdl.edit_title') }}
                </h1>
                <p
                    v-if="props.run.version_number"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('products.sdl.fields.product_version') }}:
                    {{ props.run.version_number }}
                </p>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('products.sdl.version_none') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button as-child variant="outline">
                    <Link :href="productSdlIndex(props.product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportMarkdownUrl" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{ t('products.sdl.export_markdown') }}
                    </a>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportPdfUrl" target="_blank" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{ t('products.sdl.export_pdf') }}
                    </a>
                </Button>
            </div>
        </div>

        <form class="space-y-4" @submit.prevent="submit">
            <fieldset class="space-y-4" :disabled="!canEdit">
                <div class="space-y-2">
                    <FieldLabel
                        html-for="title"
                        required
                        :help="t('products.sdl.help.title')"
                    >
                        {{ t('products.sdl.fields.title') }}
                    </FieldLabel>
                    <Input
                        id="title"
                        v-model="form.title"
                        required
                        maxlength="255"
                    />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <FieldLabel
                            html-for="status"
                            required
                            :help="t('products.sdl.help.status')"
                        >
                            {{ t('products.sdl.fields.status') }}
                        </FieldLabel>
                        <select
                            id="status"
                            v-model="form.status"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="status in editableStatuses"
                                :key="status"
                                :value="status"
                            >
                                {{ enumLabel('statuses', status) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="space-y-2">
                        <FieldLabel
                            html-for="current_stage"
                            required
                            :help="t('products.sdl.help.current_stage')"
                        >
                            {{ t('products.sdl.fields.current_stage') }}
                        </FieldLabel>
                        <select
                            id="current_stage"
                            v-model="form.current_stage"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="stage in props.options.stages"
                                :key="stage"
                                :value="stage"
                            >
                                {{ enumLabel('stages', stage) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.current_stage" />
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="space-y-2">
                        <FieldLabel
                            html-for="product_version_id"
                            :help="t('products.sdl.help.product_version')"
                        >
                            {{ t('products.sdl.fields.product_version') }}
                        </FieldLabel>
                        <select
                            id="product_version_id"
                            v-model="form.product_version_id"
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('products.sdl.version_none') }}
                            </option>
                            <option
                                v-for="version in props.versions"
                                :key="version.id"
                                :value="version.id"
                            >
                                {{ version.version_number }}
                            </option>
                        </select>
                        <InputError :message="form.errors.product_version_id" />
                    </div>

                    <div class="space-y-2">
                        <FieldLabel
                            html-for="owner_user_id"
                            :help="t('products.sdl.help.owner')"
                        >
                            {{ t('products.sdl.fields.owner') }}
                        </FieldLabel>
                        <select
                            id="owner_user_id"
                            v-model="form.owner_user_id"
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('products.sdl.none_selected') }}
                            </option>
                            <option
                                v-for="member in props.members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }}
                            </option>
                        </select>
                        <InputError :message="form.errors.owner_user_id" />
                    </div>
                </div>

                <div class="space-y-2">
                    <FieldLabel
                        html-for="notes"
                        :help="t('products.sdl.help.notes')"
                    >
                        {{ t('products.sdl.fields.notes') }}
                    </FieldLabel>
                    <textarea
                        id="notes"
                        v-model="form.notes"
                        :class="textareaClass"
                        rows="4"
                    />
                    <InputError :message="form.errors.notes" />
                </div>

                <div class="space-y-2">
                    <FieldLabel :help="t('products.sdl.help.evidence')">
                        {{ t('products.sdl.fields.evidence') }}
                    </FieldLabel>

                    <section class="space-y-3 rounded-md border p-3">
                        <div>
                            <h3 class="text-sm font-medium">
                                {{ t('products.sdl.git_heading') }}
                            </h3>
                            <p class="text-sm text-muted-foreground">
                                {{ t('products.sdl.git_help') }}
                            </p>
                        </div>

                        <div
                            v-if="!props.repository"
                            class="space-y-2 text-sm text-muted-foreground"
                        >
                            <p>{{ t('products.sdl.git_no_repository') }}</p>
                            <Button as-child variant="outline" size="sm">
                                <Link :href="editProduct(props.product.id)">
                                    {{ t('products.sdl.git_open_product') }}
                                </Link>
                            </Button>
                        </div>

                        <div v-else class="space-y-3">
                            <div class="space-y-1 text-sm">
                                <p>
                                    <a
                                        :href="props.repository.remote_url"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="inline-flex items-center gap-1 underline-offset-4 hover:underline"
                                    >
                                        {{ props.repository.full_name }}
                                        <ExternalLink class="h-3.5 w-3.5" />
                                    </a>
                                </p>
                                <p
                                    v-if="props.repository.last_sync_summary"
                                    class="text-muted-foreground"
                                >
                                    {{ t('products.repository.ci_status') }}:
                                    {{
                                        ciLabel(
                                            props.repository.last_sync_summary,
                                        )
                                    }}
                                    <a
                                        v-if="
                                            props.repository.last_sync_summary
                                                .ci?.html_url
                                        "
                                        :href="
                                            props.repository.last_sync_summary
                                                .ci.html_url
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="ml-1 underline-offset-4 hover:underline"
                                    >
                                        {{ t('products.repository.view_run') }}
                                    </a>
                                </p>
                            </div>

                            <Button
                                v-if="canEdit"
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="syncingRepository"
                                @click="syncRepositoryNow"
                            >
                                <RefreshCw
                                    class="h-4 w-4"
                                    :class="{
                                        'animate-spin': syncingRepository,
                                    }"
                                />
                                {{ t('products.repository.sync_now') }}
                            </Button>

                            <div
                                v-if="
                                    props.git_suggestions.items.length > 0 &&
                                    !props.git_suggestions.has_error
                                "
                                class="space-y-2 rounded-md border border-dashed p-3"
                            >
                                <div>
                                    <p class="text-sm font-medium">
                                        {{
                                            t(
                                                'products.sdl.git_suggest_heading',
                                            )
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            t('products.sdl.git_suggest_help')
                                        }}
                                    </p>
                                </div>
                                <ul class="space-y-3">
                                    <li
                                        v-for="(
                                            item, index
                                        ) in props.git_suggestions.items"
                                        :key="`${item.kind}-${item.evidence_id ?? item.url ?? index}`"
                                        class="space-y-2 rounded-md border p-2 text-sm"
                                    >
                                        <div class="min-w-0">
                                            <p class="font-medium">
                                                {{
                                                    item.kind === 'ci_url'
                                                        ? t(
                                                              'products.sdl.git_suggest_ci_label',
                                                          )
                                                        : t(
                                                              'products.sdl.git_suggest_snapshot_label',
                                                          )
                                                }}
                                                —
                                                {{ item.title }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                <template
                                                    v-if="
                                                        item.ci_conclusion
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'products.repository.ci_status',
                                                        )
                                                    }}:
                                                    {{ item.ci_conclusion }}
                                                    ·
                                                </template>
                                                <template
                                                    v-if="
                                                        item.suggested_stages
                                                            .length
                                                    "
                                                >
                                                    {{
                                                        t(
                                                            'products.sdl.git_suggest_stages',
                                                        )
                                                    }}:
                                                    {{
                                                        item.suggested_stages
                                                            .map((stage) =>
                                                                enumLabel(
                                                                    'stages',
                                                                    stage,
                                                                ),
                                                            )
                                                            .join(', ')
                                                    }}
                                                </template>
                                            </p>
                                            <a
                                                v-if="item.url"
                                                :href="item.url"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="mt-1 inline-flex items-center gap-1 text-xs underline-offset-4 hover:underline"
                                            >
                                                {{ item.url }}
                                                <ExternalLink
                                                    class="h-3 w-3"
                                                />
                                            </a>
                                        </div>
                                        <div
                                            v-if="canEdit"
                                            class="flex flex-wrap gap-2"
                                        >
                                            <template
                                                v-if="
                                                    item.kind ===
                                                        'snapshot' &&
                                                    item.evidence_id
                                                "
                                            >
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    :disabled="
                                                        suggestionAttachedToRun(
                                                            item,
                                                        )
                                                    "
                                                    @click="
                                                        attachGitSuggestionToRun(
                                                            item,
                                                        )
                                                    "
                                                >
                                                    <Plus class="h-4 w-4" />
                                                    {{
                                                        suggestionAttachedToRun(
                                                            item,
                                                        )
                                                            ? t(
                                                                  'products.sdl.git_attached',
                                                              )
                                                            : t(
                                                                  'products.sdl.git_suggest_add_run',
                                                              )
                                                    }}
                                                </Button>
                                                <Button
                                                    v-for="stage in item.suggested_stages"
                                                    :key="`${item.evidence_id}-${stage}`"
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    :disabled="
                                                        suggestionAlreadyOnStage(
                                                            item,
                                                            stage,
                                                        )
                                                    "
                                                    @click="
                                                        attachGitSuggestionToStage(
                                                            item,
                                                            stage,
                                                        )
                                                    "
                                                >
                                                    <Plus class="h-4 w-4" />
                                                    {{
                                                        suggestionAlreadyOnStage(
                                                            item,
                                                            stage,
                                                        )
                                                            ? t(
                                                                  'products.sdl.git_suggest_on_stage',
                                                                  {
                                                                      stage: enumLabel(
                                                                          'stages',
                                                                          stage,
                                                                      ),
                                                                  },
                                                              )
                                                            : t(
                                                                  'products.sdl.git_suggest_add_stage',
                                                                  {
                                                                      stage: enumLabel(
                                                                          'stages',
                                                                          stage,
                                                                      ),
                                                                  },
                                                              )
                                                    }}
                                                </Button>
                                            </template>
                                            <Button
                                                v-else-if="
                                                    item.kind === 'ci_url' &&
                                                    item.url
                                                "
                                                type="button"
                                                variant="outline"
                                                size="sm"
                                                :disabled="
                                                    suggestionAttachedToRun(
                                                        item,
                                                    )
                                                "
                                                @click="
                                                    prefillExternalFromSuggestion(
                                                        item,
                                                    )
                                                "
                                            >
                                                <Link2 class="h-4 w-4" />
                                                {{
                                                    suggestionAttachedToRun(
                                                        item,
                                                    )
                                                        ? t(
                                                              'products.sdl.git_suggest_url_linked',
                                                          )
                                                        : t(
                                                              'products.sdl.git_suggest_use_url',
                                                          )
                                                }}
                                            </Button>
                                        </div>
                                    </li>
                                </ul>
                            </div>

                            <div class="space-y-2">
                                <p class="text-sm font-medium">
                                    {{ t('products.sdl.git_recent_snapshots') }}
                                </p>
                                <p
                                    v-if="props.git_evidence.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ t('products.sdl.git_no_snapshots') }}
                                </p>
                                <ul class="space-y-2">
                                    <li
                                        v-for="item in props.git_evidence"
                                        :key="item.id"
                                        class="flex flex-wrap items-center justify-between gap-2 rounded-md border p-2 text-sm"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate font-medium">
                                                {{ item.title }}
                                            </p>
                                            <p
                                                class="text-xs text-muted-foreground"
                                            >
                                                #{{ item.id }}
                                                <span
                                                    v-if="item.checksum_short"
                                                >
                                                    · {{ item.checksum_short }}…
                                                </span>
                                            </p>
                                        </div>
                                        <Button
                                            v-if="canEdit"
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            :disabled="
                                                form.evidence_ids.includes(
                                                    item.id,
                                                )
                                            "
                                            @click="attachGitEvidence(item.id)"
                                        >
                                            <Plus class="h-4 w-4" />
                                            {{
                                                form.evidence_ids.includes(
                                                    item.id,
                                                )
                                                    ? t(
                                                          'products.sdl.git_attached',
                                                      )
                                                    : t(
                                                          'products.sdl.git_attach',
                                                      )
                                            }}
                                        </Button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div v-if="canEdit" class="space-y-2 border-t pt-3">
                            <p class="text-sm font-medium">
                                {{ t('products.sdl.git_link_pr_heading') }}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {{ t('products.sdl.git_link_pr_help') }}
                            </p>
                            <div class="grid gap-2 sm:grid-cols-2">
                                <div class="space-y-2 sm:col-span-2">
                                    <FieldLabel
                                        html-for="git-url"
                                        :help="t('products.sdl.help.git_url')"
                                    >
                                        {{ t('products.sdl.fields.git_url') }}
                                    </FieldLabel>
                                    <Input
                                        id="git-url"
                                        v-model="externalUrl"
                                        type="url"
                                        :placeholder="
                                            t(
                                                'products.sdl.git_url_placeholder',
                                            )
                                        "
                                    />
                                    <InputError :message="externalErrors.url" />
                                </div>
                                <div class="space-y-2">
                                    <FieldLabel
                                        html-for="git-title"
                                        :help="t('products.sdl.help.git_title')"
                                    >
                                        {{ t('products.sdl.fields.git_title') }}
                                    </FieldLabel>
                                    <Input
                                        id="git-title"
                                        v-model="externalTitle"
                                    />
                                    <InputError
                                        :message="externalErrors.title"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <FieldLabel
                                        html-for="git-stage"
                                        :help="t('products.sdl.help.git_stage')"
                                    >
                                        {{ t('products.sdl.fields.git_stage') }}
                                    </FieldLabel>
                                    <select
                                        id="git-stage"
                                        v-model="externalStage"
                                        :class="selectClass"
                                    >
                                        <option value="">
                                            {{
                                                t(
                                                    'products.sdl.git_stage_run_only',
                                                )
                                            }}
                                        </option>
                                        <option
                                            v-for="stage in props.options
                                                .stages"
                                            :key="stage"
                                            :value="stage"
                                        >
                                            {{ enumLabel('stages', stage) }}
                                        </option>
                                    </select>
                                    <InputError
                                        :message="externalErrors.stage"
                                    />
                                </div>
                            </div>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                :disabled="
                                    linkingExternal || externalUrl.trim() === ''
                                "
                                @click="submitExternalLink"
                            >
                                <Link2 class="h-4 w-4" />
                                {{ t('products.sdl.git_link_create') }}
                            </Button>
                        </div>
                    </section>

                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <p
                            v-if="props.evidence.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.sdl.no_evidence') }}
                        </p>
                        <label
                            v-for="item in props.evidence"
                            :key="item.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="form.evidence_ids.includes(item.id)"
                                @change="
                                    toggleRunEvidence(
                                        item.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>{{ evidenceLabel(item) }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.evidence_ids" />
                </div>
            </fieldset>

            <div
                v-if="isLocked"
                class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-100"
            >
                <p>
                    {{ t('products.sdl.approved_banner') }}
                    <span v-if="props.run.approved_by_name">
                        — {{ props.run.approved_by_name }}
                    </span>
                    <span v-if="props.run.approved_at">
                        ({{ formatDateTime(props.run.approved_at) }})
                    </span>
                </p>
            </div>

            <section
                v-if="props.canManage"
                class="space-y-3 rounded-md border p-3"
            >
                <div>
                    <h2 class="text-sm font-medium">
                        {{ t('products.sdl.approval_heading') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ t('products.sdl.approval_help') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <Button
                        v-if="!isLocked"
                        type="button"
                        :disabled="!props.run.can_approve || approving"
                        @click="approveRun"
                    >
                        <ShieldCheck class="h-4 w-4" />
                        {{ t('products.sdl.approve') }}
                    </Button>
                    <p
                        v-if="!isLocked && !props.run.can_approve"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('products.sdl.approve_not_ready') }}
                    </p>
                    <Button
                        v-if="isLocked"
                        type="button"
                        variant="outline"
                        :disabled="revoking"
                        @click="showRevokeDialog = true"
                    >
                        {{ t('products.sdl.revoke_approval') }}
                    </Button>
                </div>
            </section>

            <div v-if="canEdit" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <section class="space-y-3">
            <div>
                <h2 class="text-sm font-medium">
                    {{ t('products.sdl.stages_heading') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.sdl.stages_help') }}
                </p>
            </div>

            <ul class="space-y-3">
                <li
                    v-for="entry in props.run.stage_entries"
                    :key="entry.stage"
                    class="space-y-3 rounded-md border p-3"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <h3 class="text-sm font-medium">
                            {{ enumLabel('stages', entry.stage) }}
                        </h3>
                        <p
                            v-if="entry.completed_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{ stageCompletedLabel(entry) }}
                        </p>
                    </div>

                    <fieldset
                        class="grid gap-3 sm:grid-cols-2"
                        :disabled="!canEdit"
                    >
                        <div class="space-y-2">
                            <FieldLabel
                                :html-for="`stage-status-${entry.stage}`"
                                :help="t('products.sdl.help.stage_status')"
                            >
                                {{ t('products.sdl.fields.stage_status') }}
                            </FieldLabel>
                            <select
                                :id="`stage-status-${entry.stage}`"
                                v-model="stageDrafts[entry.stage].status"
                                :class="selectClass"
                            >
                                <option
                                    v-for="status in props.options
                                        .stage_statuses"
                                    :key="status"
                                    :value="status"
                                >
                                    {{ enumLabel('stage_statuses', status) }}
                                </option>
                            </select>
                            <InputError
                                :message="stageErrors[entry.stage]?.status"
                            />
                        </div>

                        <div class="space-y-2 sm:col-span-2">
                            <div
                                class="flex flex-wrap items-center justify-between gap-2"
                            >
                                <FieldLabel
                                    :html-for="`stage-notes-${entry.stage}`"
                                    :help="t('products.sdl.help.stage_notes')"
                                >
                                    {{ t('products.sdl.fields.stage_notes') }}
                                </FieldLabel>
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        v-if="
                                            canEdit &&
                                            hasStageTemplate(entry.stage)
                                        "
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            requestApplyTemplate(entry.stage)
                                        "
                                    >
                                        <FileText class="h-4 w-4" />
                                        {{ t('products.sdl.apply_template') }}
                                    </Button>
                                    <Button
                                        v-if="canEdit && aiEnabled"
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        :disabled="
                                            ensureAiStageDraft(entry.stage)
                                                .loading
                                        "
                                        @click="
                                            requestAiStageDraft(entry.stage)
                                        "
                                    >
                                        <Sparkles class="h-4 w-4" />
                                        {{
                                            ensureAiStageDraft(entry.stage)
                                                .loading
                                                ? t(
                                                      'products.sdl.ai_draft_loading',
                                                  )
                                                : t(
                                                      'products.sdl.ai_draft_suggest',
                                                  )
                                        }}
                                    </Button>
                                </div>
                            </div>
                            <textarea
                                :id="`stage-notes-${entry.stage}`"
                                v-model="stageDrafts[entry.stage].notes"
                                :class="textareaClass"
                                rows="4"
                            />
                            <InputError
                                :message="stageErrors[entry.stage]?.notes"
                            />
                            <div
                                v-if="ensureAiStageDraft(entry.stage).error"
                                class="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive"
                            >
                                {{ ensureAiStageDraft(entry.stage).error }}
                            </div>
                            <div
                                v-if="
                                    ensureAiStageDraft(entry.stage)
                                        .notes_markdown
                                "
                                class="space-y-3 rounded-md border border-border bg-muted/30 p-4"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        ensureAiStageDraft(entry.stage)
                                            .disclaimer ||
                                        t('products.sdl.ai_draft_disclaimer')
                                    }}
                                </p>
                                <MarkdownPreview
                                    :source="
                                        ensureAiStageDraft(entry.stage)
                                            .notes_markdown
                                    "
                                    :empty-label="
                                        t('products.sdl.ai_draft_empty')
                                    "
                                />
                                <div class="flex flex-wrap gap-2">
                                    <Button
                                        type="button"
                                        size="sm"
                                        @click="applyAiStageDraft(entry.stage)"
                                    >
                                        {{ t('products.sdl.ai_draft_apply') }}
                                    </Button>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="
                                            discardAiStageDraft(entry.stage)
                                        "
                                    >
                                        {{ t('products.sdl.ai_draft_discard') }}
                                    </Button>
                                </div>
                            </div>
                        </div>

                        <template
                            v-if="
                                stageDrafts[entry.stage].status === 'exception'
                            "
                        >
                            <div class="space-y-2">
                                <FieldLabel
                                    :html-for="`stage-exception-owner-${entry.stage}`"
                                    :help="
                                        t('products.sdl.help.exception_owner')
                                    "
                                >
                                    {{
                                        t('products.sdl.fields.exception_owner')
                                    }}
                                </FieldLabel>
                                <select
                                    :id="`stage-exception-owner-${entry.stage}`"
                                    v-model="
                                        stageDrafts[entry.stage]
                                            .exception_owner_user_id
                                    "
                                    :class="selectClass"
                                >
                                    <option value="">
                                        {{ t('products.sdl.none_selected') }}
                                    </option>
                                    <option
                                        v-for="member in props.members"
                                        :key="member.id"
                                        :value="member.id"
                                    >
                                        {{ member.name }}
                                    </option>
                                </select>
                                <InputError
                                    :message="
                                        stageErrors[entry.stage]
                                            ?.exception_owner_user_id
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <FieldLabel
                                    :html-for="`stage-exception-expires-${entry.stage}`"
                                    :help="
                                        t(
                                            'products.sdl.help.exception_expires_at',
                                        )
                                    "
                                >
                                    {{
                                        t(
                                            'products.sdl.fields.exception_expires_at',
                                        )
                                    }}
                                </FieldLabel>
                                <Input
                                    :id="`stage-exception-expires-${entry.stage}`"
                                    v-model="
                                        stageDrafts[entry.stage]
                                            .exception_expires_at
                                    "
                                    type="date"
                                />
                                <InputError
                                    :message="
                                        stageErrors[entry.stage]
                                            ?.exception_expires_at
                                    "
                                />
                            </div>

                            <div
                                v-if="entry.exception"
                                class="flex flex-wrap items-center gap-3 sm:col-span-2"
                            >
                                <p
                                    v-if="entry.exception.is_expired"
                                    class="text-sm font-medium text-destructive"
                                >
                                    {{ t('products.sdl.exception_expired') }}
                                </p>
                                <Link
                                    v-if="exceptionTaskHref(entry)"
                                    :href="exceptionTaskHref(entry)!"
                                    class="inline-flex items-center gap-1 text-sm text-primary underline-offset-4 hover:underline"
                                >
                                    <ExternalLink class="h-3.5 w-3.5" />
                                    {{ t('products.sdl.exception_view_task') }}
                                </Link>
                            </div>
                        </template>

                        <div class="space-y-2 sm:col-span-2">
                            <FieldLabel
                                :help="t('products.sdl.help.stage_evidence')"
                            >
                                {{ t('products.sdl.fields.stage_evidence') }}
                            </FieldLabel>
                            <div
                                class="max-h-36 space-y-2 overflow-y-auto rounded-md border p-3"
                            >
                                <p
                                    v-if="props.evidence.length === 0"
                                    class="text-sm text-muted-foreground"
                                >
                                    {{ t('products.sdl.no_evidence') }}
                                </p>
                                <label
                                    v-for="item in props.evidence"
                                    :key="`${entry.stage}-${item.id}`"
                                    class="flex items-start gap-2 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        class="mt-1"
                                        :checked="
                                            stageDrafts[
                                                entry.stage
                                            ].evidence_ids.includes(item.id)
                                        "
                                        @change="
                                            toggleStageEvidence(
                                                entry.stage,
                                                item.id,
                                                (
                                                    $event.target as HTMLInputElement
                                                ).checked,
                                            )
                                        "
                                    />
                                    <span>{{ item.title }}</span>
                                </label>
                            </div>
                            <InputError
                                :message="
                                    stageErrors[entry.stage]?.evidence_ids
                                "
                            />
                        </div>
                    </fieldset>

                    <div v-if="canEdit" class="flex justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="savingStage === entry.stage"
                            @click="saveStage(entry.stage)"
                        >
                            <Save class="h-4 w-4" />
                            {{ t('products.sdl.stage_save') }}
                        </Button>
                    </div>
                </li>
            </ul>
        </section>

        <AppAlertDialog
            v-model:open="showRevokeDialog"
            variant="default"
            :title="t('products.sdl.confirm_revoke_title')"
            :description="t('products.sdl.confirm_revoke')"
            :loading="revoking"
            @confirm="confirmRevoke"
            @cancel="showRevokeDialog = false"
        />

        <AppAlertDialog
            v-model:open="showTemplateDialog"
            variant="default"
            :title="t('products.sdl.confirm_template_title')"
            :description="t('products.sdl.confirm_template_overwrite')"
            @confirm="confirmApplyTemplate"
            @cancel="cancelApplyTemplate"
        />
    </div>
</template>
