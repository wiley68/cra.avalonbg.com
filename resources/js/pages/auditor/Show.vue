<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, Plus, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { index as auditorIndex } from '@/routes/auditor';
import {
    edit as packagesEdit,
    show as packagesShow,
} from '@/routes/auditor/packages';
import {
    destroy as findingsDestroy,
    status as findingsStatus,
    store as findingsStore,
    update as findingsUpdate,
} from '@/routes/auditor/packages/findings';

type OrganizationSummary = { id: number; name: string; slug: string };

type Person = { id: number; name: string; email: string } | null;

type EvidenceItem = {
    id: number;
    title: string;
    type: string;
    freshness_status?: string | null;
    confidentiality?: string | null;
};

type PackageDetail = {
    id: number;
    title: string;
    status: string;
    notes: string | null;
    product_id: number;
    product_name: string;
    product_slug: string;
    shared_at: string | null;
    closed_at: string | null;
    created_by_name: string | null;
    evidence_ids: number[];
    evidence: EvidenceItem[];
    is_editable: boolean;
};

type FindingItem = {
    id: number;
    title: string;
    body: string;
    severity: string;
    status: string;
    created_by: number;
    created_by_name: string | null;
    remediated_at: string | null;
    created_at: string | null;
    updated_at: string | null;
};

type ProductPassport = {
    id: number;
    name: string;
    slug: string;
    manufacturer: string | null;
    trademark: string | null;
    product_type: string | null;
    licensing_model: string | null;
    scope_status: string | null;
    classification_status: string | null;
    intended_purpose: string | null;
    product_owner: Person;
    security_contact: Person;
    versions: Array<{
        id: number;
        version_number: string;
        state: string | null;
        support_status: string | null;
        release_date: string | null;
    }>;
    support_periods: Array<{
        id: number;
        type: string;
        start_basis: string;
        duration_months: number;
        effective_starts_at: string | null;
        effective_ends_at: string | null;
        schedule_resolved: boolean;
        basis: string | null;
        is_extended: boolean;
    }>;
};

type ReadinessSection = {
    key: string;
    status: 'pass' | 'warn' | 'fail' | 'na';
    summary: string;
    metrics?: Record<string, number | string | null>;
};

type ReadinessGap = {
    section: string;
    status: 'warn' | 'fail';
    message_key: string;
    link: string | null;
};

type ReadinessReport = {
    generated_at: string;
    product: { id: number; name: string; slug: string };
    sections: ReadinessSection[];
    gaps: ReadinessGap[];
    metrics: Record<string, number | null>;
};

const props = defineProps<{
    organization: OrganizationSummary;
    package: PackageDetail;
    product: ProductPassport;
    report: ReadinessReport;
    findings: FindingItem[];
    findingOptions: {
        severities: string[];
        statuses: string[];
    };
    canManage: boolean;
    canCreateFindings: boolean;
    canManageFindingContent: boolean;
    canManageRemediation: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.auditor', href: auditorIndex() },
    {
        title: props.package.title,
        href: packagesShow(props.package.id),
    },
]);

const createForm = useForm({
    title: '',
    body: '',
    severity: 'minor',
});

const editingId = ref<number | null>(null);
const editForm = useForm({
    title: '',
    body: '',
    severity: 'minor',
});

const findingToDelete = ref<number | null>(null);
const showDeleteDialog = ref(false);

const statusLabel = computed(() => {
    const key = `auditor.statuses.${props.package.status}`;
    const translated = t(key);

    return translated === key ? props.package.status : translated;
});

const statusVariant = computed(() => {
    if (props.package.status === 'shared') {
        return 'default' as const;
    }

    if (props.package.status === 'closed') {
        return 'secondary' as const;
    }

    return 'outline' as const;
});

const readinessStatusVariant = (
    status: ReadinessSection['status'] | ReadinessGap['status'],
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    switch (status) {
        case 'pass':
            return 'default';
        case 'warn':
            return 'secondary';
        case 'fail':
            return 'destructive';
        default:
            return 'outline';
    }
};

const severityVariant = (
    severity: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (severity === 'critical' || severity === 'major') {
        return 'destructive';
    }

    if (severity === 'minor') {
        return 'secondary';
    }

    return 'outline';
};

const sectionTitle = (key: string): string => {
    const translation = t(`products.readiness.sections.${key}`);

    return translation === `products.readiness.sections.${key}`
        ? key
        : translation;
};

const summaryLabel = (section: ReadinessSection): string => {
    const key = `products.readiness.summaries.${section.key}.${section.summary}`;
    const translated = t(key);

    return translated === key ? section.summary : translated;
};

const gapMessage = (gap: ReadinessGap): string => {
    const translated = t(gap.message_key);

    return translated === gap.message_key
        ? t('products.readiness.gaps.generic')
        : translated;
};

const enumLabel = (group: string, value: string | null): string => {
    if (!value) {
        return t('products.passport.empty');
    }

    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const evidenceTypeLabel = (value: string): string => {
    const key = `products.evidence.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const freshnessLabel = (value: string | null | undefined): string => {
    if (!value) {
        return '—';
    }

    const key = `products.evidence.freshness_statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const findingSeverityLabel = (value: string): string => {
    const key = `auditor.findings.severities.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const findingStatusLabel = (value: string): string => {
    const key = `auditor.findings.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const failCount = computed(
    () => props.report.gaps.filter((gap) => gap.status === 'fail').length,
);
const warnCount = computed(
    () => props.report.gaps.filter((gap) => gap.status === 'warn').length,
);

const personLabel = (person: Person): string => {
    if (!person) {
        return t('products.passport.empty');
    }

    return `${person.name} (${person.email})`;
};

const submitCreate = () => {
    createForm.post(findingsStore(props.package.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            createForm.reset();
            createForm.severity = 'minor';
        },
    });
};

const startEdit = (finding: FindingItem) => {
    editingId.value = finding.id;
    editForm.title = finding.title;
    editForm.body = finding.body;
    editForm.severity = finding.severity;
    editForm.clearErrors();
};

const cancelEdit = () => {
    editingId.value = null;
    editForm.reset();
};

const submitEdit = (findingId: number) => {
    editForm.put(findingsUpdate([props.package.id, findingId]).url, {
        preserveScroll: true,
        onSuccess: () => {
            editingId.value = null;
            editForm.reset();
        },
    });
};

const updateFindingStatus = (findingId: number, status: string) => {
    router.put(
        findingsStatus([props.package.id, findingId]).url,
        { status },
        { preserveScroll: true },
    );
};

const confirmDeleteFinding = (findingId: number) => {
    findingToDelete.value = findingId;
    showDeleteDialog.value = true;
};

const doDeleteFinding = () => {
    if (findingToDelete.value === null) {
        return;
    }

    const id = findingToDelete.value;
    showDeleteDialog.value = false;
    findingToDelete.value = null;

    router.delete(findingsDestroy([props.package.id, id]).url, {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="package.title" />

    <div class="mx-auto w-full max-w-5xl space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="space-y-2">
                <p class="text-sm text-muted-foreground">
                    {{ organization.name }} · {{ product.name }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ package.title }}
                </h1>
                <div class="flex flex-wrap items-center gap-2 text-sm">
                    <Badge :variant="statusVariant">
                        {{ statusLabel }}
                    </Badge>
                    <span
                        v-if="package.created_by_name"
                        class="text-muted-foreground"
                    >
                        {{ t('auditor.fields.created_by') }}:
                        {{ package.created_by_name }}
                    </span>
                </div>
                <p class="max-w-3xl text-sm text-muted-foreground">
                    {{ t('auditor.review_subtitle') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="auditorIndex()">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child variant="outline">
                    <Link :href="packagesEdit(package.id)">
                        <Pencil class="h-4 w-4" />
                        {{ t('auditor.manage_package') }}
                    </Link>
                </Button>
            </div>
        </div>

        <p
            class="rounded-md border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            {{ t('auditor.review_disclaimer') }}
        </p>

        <section v-if="package.notes" class="space-y-2 rounded-lg border p-5">
            <h2 class="text-lg font-semibold">
                {{ t('auditor.fields.notes') }}
            </h2>
            <p class="text-sm whitespace-pre-wrap">{{ package.notes }}</p>
        </section>

        <section class="space-y-4 rounded-lg border p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">
                    {{ t('auditor.findings.title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('auditor.findings.subtitle') }}
                </p>
            </div>

            <form
                v-if="canCreateFindings"
                class="space-y-3 rounded-md border border-dashed p-4"
                @submit.prevent="submitCreate"
            >
                <h3 class="text-sm font-medium">
                    {{ t('auditor.findings.add') }}
                </h3>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="finding_title"
                        :help="t('auditor.findings.help.title')"
                        required
                    >
                        {{ t('auditor.findings.fields.title') }}
                    </FieldLabel>
                    <Input
                        id="finding_title"
                        v-model="createForm.title"
                        required
                    />
                    <InputError :message="createForm.errors.title" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="finding_severity"
                        :help="t('auditor.findings.help.severity')"
                        required
                    >
                        {{ t('auditor.findings.fields.severity') }}
                    </FieldLabel>
                    <Select v-model="createForm.severity">
                        <SelectTrigger id="finding_severity" class="w-full">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem
                                v-for="severity in findingOptions.severities"
                                :key="severity"
                                :value="severity"
                            >
                                {{ findingSeverityLabel(severity) }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <InputError :message="createForm.errors.severity" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="finding_body"
                        :help="t('auditor.findings.help.body')"
                        required
                    >
                        {{ t('auditor.findings.fields.body') }}
                    </FieldLabel>
                    <textarea
                        id="finding_body"
                        v-model="createForm.body"
                        rows="3"
                        required
                        class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <InputError :message="createForm.errors.body" />
                </div>
                <Button type="submit" :disabled="createForm.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('auditor.findings.add') }}
                </Button>
            </form>

            <ul v-if="findings.length" class="space-y-3">
                <li
                    v-for="finding in findings"
                    :key="finding.id"
                    class="space-y-3 rounded-md border p-4"
                >
                    <template v-if="editingId === finding.id">
                        <div class="grid gap-2">
                            <FieldLabel
                                :help="t('auditor.findings.help.title')"
                                required
                            >
                                {{ t('auditor.findings.fields.title') }}
                            </FieldLabel>
                            <Input v-model="editForm.title" required />
                            <InputError :message="editForm.errors.title" />
                        </div>
                        <div class="grid gap-2">
                            <FieldLabel
                                :help="t('auditor.findings.help.severity')"
                                required
                            >
                                {{ t('auditor.findings.fields.severity') }}
                            </FieldLabel>
                            <Select v-model="editForm.severity">
                                <SelectTrigger class="w-full">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="severity in findingOptions.severities"
                                        :key="severity"
                                        :value="severity"
                                    >
                                        {{ findingSeverityLabel(severity) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                            <InputError :message="editForm.errors.severity" />
                        </div>
                        <div class="grid gap-2">
                            <FieldLabel
                                :help="t('auditor.findings.help.body')"
                                required
                            >
                                {{ t('auditor.findings.fields.body') }}
                            </FieldLabel>
                            <textarea
                                v-model="editForm.body"
                                rows="3"
                                required
                                class="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            />
                            <InputError :message="editForm.errors.body" />
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <Button
                                type="button"
                                :disabled="editForm.processing"
                                @click="submitEdit(finding.id)"
                            >
                                {{ t('common.save') }}
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                @click="cancelEdit"
                            >
                                {{ t('common.cancel') }}
                            </Button>
                        </div>
                    </template>

                    <template v-else>
                        <div
                            class="flex flex-wrap items-start justify-between gap-3"
                        >
                            <div class="space-y-1">
                                <h3 class="font-medium">{{ finding.title }}</h3>
                                <div class="flex flex-wrap items-center gap-2">
                                    <Badge
                                        :variant="
                                            severityVariant(finding.severity)
                                        "
                                    >
                                        {{
                                            findingSeverityLabel(
                                                finding.severity,
                                            )
                                        }}
                                    </Badge>
                                    <Badge variant="outline">
                                        {{ findingStatusLabel(finding.status) }}
                                    </Badge>
                                    <span
                                        v-if="finding.created_by_name"
                                        class="text-xs text-muted-foreground"
                                    >
                                        {{ finding.created_by_name }}
                                    </span>
                                </div>
                            </div>
                            <div
                                v-if="canManageFindingContent"
                                class="flex flex-wrap gap-2"
                            >
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="outline"
                                    @click="startEdit(finding)"
                                >
                                    <Pencil class="h-4 w-4" />
                                    {{ t('common.edit') }}
                                </Button>
                                <Button
                                    v-if="finding.status === 'open'"
                                    type="button"
                                    size="sm"
                                    variant="destructive"
                                    @click="confirmDeleteFinding(finding.id)"
                                >
                                    <Trash2 class="h-4 w-4" />
                                    {{ t('common.delete') }}
                                </Button>
                            </div>
                        </div>
                        <p
                            class="text-sm whitespace-pre-wrap text-muted-foreground"
                        >
                            {{ finding.body }}
                        </p>
                        <div
                            v-if="canManageRemediation"
                            class="grid max-w-sm gap-2"
                        >
                            <FieldLabel
                                :html-for="`finding_status_${finding.id}`"
                                :help="t('auditor.findings.help.status')"
                            >
                                {{ t('auditor.findings.fields.status') }}
                            </FieldLabel>
                            <Select
                                :model-value="finding.status"
                                @update:model-value="
                                    (value) =>
                                        updateFindingStatus(
                                            finding.id,
                                            String(value),
                                        )
                                "
                            >
                                <SelectTrigger
                                    :id="`finding_status_${finding.id}`"
                                    class="w-full"
                                >
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem
                                        v-for="status in findingOptions.statuses"
                                        :key="status"
                                        :value="status"
                                    >
                                        {{ findingStatusLabel(status) }}
                                    </SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <p
                            v-else-if="finding.remediated_at"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('auditor.findings.fields.remediated_at') }}:
                            {{
                                new Date(finding.remediated_at).toLocaleString()
                            }}
                        </p>
                    </template>
                </li>
            </ul>

            <p v-else class="text-sm text-muted-foreground">
                {{ t('auditor.findings.empty') }}
            </p>
        </section>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg border p-4">
                <p
                    class="text-xs tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.readiness.metrics.failures') }}
                </p>
                <p class="mt-1 text-2xl font-semibold">{{ failCount }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <p
                    class="text-xs tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.readiness.metrics.warnings') }}
                </p>
                <p class="mt-1 text-2xl font-semibold">{{ warnCount }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <p
                    class="text-xs tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.readiness.metrics.generated') }}
                </p>
                <p class="mt-1 text-sm font-medium">
                    {{ new Date(report.generated_at).toLocaleString() }}
                </p>
            </div>
        </div>

        <section class="space-y-3 rounded-lg border p-5">
            <h2 class="text-lg font-semibold">
                {{ t('products.passport.identity_title') }}
            </h2>
            <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.manufacturer') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{
                            product.manufacturer || t('products.passport.empty')
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.product_type') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ enumLabel('types', product.product_type) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.licensing_model') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ enumLabel('licensing', product.licensing_model) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.passport.scope') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ enumLabel('scope', product.scope_status) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.passport.classification') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{
                            enumLabel(
                                'classification',
                                product.classification_status,
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.trademark') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ product.trademark || t('products.passport.empty') }}
                    </dd>
                </div>
            </dl>
            <div v-if="product.intended_purpose">
                <p class="text-xs text-muted-foreground">
                    {{ t('products.fields.intended_purpose') }}
                </p>
                <p class="mt-1 text-sm">{{ product.intended_purpose }}</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-3 rounded-lg border p-5">
                <h2 class="text-lg font-semibold">
                    {{ t('products.passport.versions_title') }}
                </h2>
                <ul v-if="product.versions.length" class="space-y-2 text-sm">
                    <li
                        v-for="version in product.versions"
                        :key="version.id"
                        class="flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0 last:pb-0"
                    >
                        <span class="font-medium">{{
                            version.version_number
                        }}</span>
                        <span class="text-muted-foreground">
                            {{ version.state || '—' }}
                            <template v-if="version.release_date">
                                · {{ version.release_date }}
                            </template>
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('products.passport.empty') }}
                </p>
            </div>

            <div class="space-y-3 rounded-lg border p-5">
                <h2 class="text-lg font-semibold">
                    {{ t('products.passport.support_title') }}
                </h2>
                <ul
                    v-if="product.support_periods.length"
                    class="space-y-2 text-sm"
                >
                    <li
                        v-for="period in product.support_periods"
                        :key="period.id"
                        class="border-b pb-2 last:border-0 last:pb-0"
                    >
                        <div class="font-medium">
                            {{
                                t(
                                    `products.support_periods.types.${period.type}`,
                                )
                            }}
                        </div>
                        <div class="text-muted-foreground">
                            {{
                                t(
                                    'products.support_periods.duration_months_label',
                                    {
                                        count: String(period.duration_months),
                                    },
                                )
                            }}
                            <template
                                v-if="
                                    period.schedule_resolved &&
                                    period.effective_starts_at &&
                                    period.effective_ends_at
                                "
                            >
                                · {{ period.effective_starts_at }} →
                                {{ period.effective_ends_at }}
                            </template>
                        </div>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('products.passport.empty') }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-3 rounded-lg border p-5">
                <h2 class="text-lg font-semibold">
                    {{ t('products.passport.people_title') }}
                </h2>
                <dl class="space-y-3 text-sm">
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ t('products.fields.product_owner') }}
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ personLabel(product.product_owner) }}
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs text-muted-foreground">
                            {{ t('products.fields.security_contact') }}
                        </dt>
                        <dd class="mt-1 font-medium">
                            {{ personLabel(product.security_contact) }}
                        </dd>
                    </div>
                </dl>
            </div>

            <div class="space-y-3 rounded-lg border p-5">
                <h2 class="text-lg font-semibold">
                    {{ t('auditor.fields.evidence') }}
                </h2>
                <ul v-if="package.evidence.length" class="space-y-2 text-sm">
                    <li
                        v-for="item in package.evidence"
                        :key="item.id"
                        class="flex flex-wrap items-center justify-between gap-2 border-b pb-2 last:border-0 last:pb-0"
                    >
                        <span class="font-medium">{{ item.title }}</span>
                        <span class="text-muted-foreground">
                            {{ evidenceTypeLabel(item.type) }}
                            <template v-if="item.freshness_status">
                                ·
                                {{ freshnessLabel(item.freshness_status) }}
                            </template>
                        </span>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('auditor.no_package_evidence') }}
                </p>
            </div>
        </section>

        <section v-if="report.gaps.length" class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ t('products.readiness.gaps_title') }}
            </h2>
            <ul class="space-y-2">
                <li
                    v-for="(gap, index) in report.gaps"
                    :key="`${gap.section}-${index}`"
                    class="flex flex-wrap items-center gap-2 rounded-md border px-3 py-2"
                >
                    <Badge :variant="readinessStatusVariant(gap.status)">
                        {{ t(`products.readiness.status.${gap.status}`) }}
                    </Badge>
                    <span class="text-sm">{{ gapMessage(gap) }}</span>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ t('products.readiness.sections_title') }}
            </h2>
            <div class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="section in report.sections"
                    :key="section.key"
                    class="space-y-2 rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-medium">
                            {{ sectionTitle(section.key) }}
                        </h3>
                        <Badge
                            :variant="readinessStatusVariant(section.status)"
                        >
                            {{
                                t(`products.readiness.status.${section.status}`)
                            }}
                        </Badge>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        {{ summaryLabel(section) }}
                    </p>
                </article>
            </div>
        </section>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('auditor.findings.confirm_delete')"
            :confirm-label="t('common.confirm_delete')"
            variant="destructive"
            @confirm="doDeleteFinding"
            @cancel="showDeleteDialog = false"
        />
    </div>
</template>
