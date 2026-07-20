<script setup lang="ts">
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ClipboardList,
    GitBranch,
    RefreshCw,
    Save,
    Tags,
    Trash2,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import ClassificationWizard from '@/components/products/ClassificationWizard.vue';
import ScopeWizard from '@/components/products/ScopeWizard.vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Switch } from '@/components/ui/switch';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { setProductModuleOrigin } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import {
    canAccessProductModule,
    productModules,
    productModuleStatusClass,
} from '@/pages/products/columns';
import type { ProductModuleStatus } from '@/pages/products/columns';
import {
    destroy,
    edit as editProduct,
    index as productsIndex,
    update,
} from '@/routes/products';
import {
    destroy as destroyRepository,
    store as storeRepository,
    sync as syncRepository,
} from '@/routes/products/repository';
import { edit as editIntegrations } from '@/routes/settings/integrations';

type Member = {
    id: number;
    name: string;
    email: string;
};

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type Options = {
    product_types: string[];
    licensing_models: string[];
    scope_statuses: string[];
    classification_statuses: string[];
};

type EditableProduct = {
    id: number;
    name: string;
    slug: string;
    product_line: string | null;
    description: string | null;
    intended_purpose: string | null;
    product_type: string;
    manufacturer: string | null;
    trademark: string | null;
    licensing_model: string;
    has_remote_data_processing: boolean;
    has_network_connectivity: boolean;
    deployment_model: string | null;
    support_period_notes: string | null;
    end_of_support_policy: string | null;
    product_owner_user_id: number | null;
    security_contact_user_id: number | null;
    scope_status: string;
    scope_rationale: string | null;
    classification_status: string;
    classification_rationale: string | null;
    classification_next_review_at: string | null;
};

type LatestScopeAssessment = {
    id: number;
    answers: Record<string, string>;
    suggested_status: string;
    final_status: string;
    rationale: string | null;
    reviewed_at: string | null;
    reviewed_by: number | null;
} | null;

type LatestClassification = {
    id: number;
    answers: Record<string, string>;
    suggested_status: string;
    final_status: string;
    rationale: string | null;
    regulatory_content_version: string;
    evidence_notes: string | null;
    reviewed_at: string | null;
    reviewed_by: number | null;
    approved_at: string | null;
    approved_by: number | null;
    next_review_at: string | null;
} | null;

type ProductRepositoryPayload = {
    id: number;
    full_name: string;
    remote_url: string;
    default_branch: string | null;
    connection_id: number;
    external_id: string | null;
    last_synced_at: string | null;
    last_sync_summary: {
        tags_count?: number;
        releases_count?: number;
        latest_tag?: string | null;
        latest_release?: string | null;
        error?: string;
        ci?: {
            status?: string;
            conclusion?: string | null;
            workflow_name?: string | null;
            html_url?: string | null;
        };
    } | null;
};

type VcsConnectionOption = {
    id: number;
    provider: string;
    label: string | null;
    status: string;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: EditableProduct;
    members: Member[];
    options: Options;
    module_statuses?: Record<string, ProductModuleStatus>;
    latestScopeAssessment?: LatestScopeAssessment;
    latestClassification?: LatestClassification;
    openScopeWizard?: boolean;
    openClassificationWizard?: boolean;
    repository?: ProductRepositoryPayload | null;
    vcs_connections?: VcsConnectionOption[];
}>();

const { t } = useTranslations();
const page = usePage();
const authUser = computed(() => page.props.auth.user);

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
]);
const showDeleteDialog = ref(false);
const showScopeWizard = ref(props.openScopeWizard ?? false);
const showClassificationWizard = ref(props.openClassificationWizard ?? false);
const showUnlinkRepositoryDialog = ref(false);
const syncingRepository = ref(false);

const repositoryForm = useForm({
    connection_id:
        props.repository?.connection_id ?? props.vcs_connections?.[0]?.id ?? '',
    repository: props.repository?.full_name ?? '',
});

const activeVcsConnections = computed(() =>
    (props.vcs_connections ?? []).filter(
        (connection) => connection.status === 'active',
    ),
);

const linkRepository = () => {
    repositoryForm.post(storeRepository.url(props.product.id), {
        preserveScroll: true,
    });
};

const syncRepositoryNow = () => {
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

const confirmUnlinkRepository = () => {
    router.delete(destroyRepository.url(props.product.id), {
        preserveScroll: true,
        onFinish: () => {
            showUnlinkRepositoryDialog.value = false;
            repositoryForm.repository = '';
        },
    });
};

const ciLabel = (
    summary: ProductRepositoryPayload['last_sync_summary'],
): string => {
    if (!summary?.ci) {
        return t('products.repository.ci_unknown');
    }

    const conclusion = summary.ci.conclusion;
    if (conclusion) {
        return conclusion;
    }

    return summary.ci.status || t('products.repository.ci_unknown');
};

const moduleActions = computed(() => {
    const productId = props.product.id;

    return productModules.map((module) => {
        const status = props.module_statuses?.[module.key] ?? 'empty';
        const accessible = canAccessProductModule(module, authUser.value);

        return {
            label: t(module.labelKey),
            icon: module.icon,
            class: accessible
                ? productModuleStatusClass(status)
                : `${productModuleStatusClass(status)} opacity-50`,
            disabled: !accessible,
            onSelect: () => {
                if (!accessible) {
                    return;
                }

                setProductModuleOrigin(productId, 'edit');
                router.visit(module.href(productId));
            },
        };
    });
});

const form = useForm({
    name: props.product.name,
    slug: props.product.slug,
    product_line: props.product.product_line ?? '',
    description: props.product.description ?? '',
    intended_purpose: props.product.intended_purpose ?? '',
    product_type: props.product.product_type,
    manufacturer: props.product.manufacturer ?? '',
    trademark: props.product.trademark ?? '',
    licensing_model: props.product.licensing_model,
    has_remote_data_processing: props.product.has_remote_data_processing,
    has_network_connectivity: props.product.has_network_connectivity,
    deployment_model: props.product.deployment_model ?? '',
    support_period_notes: props.product.support_period_notes ?? '',
    end_of_support_policy: props.product.end_of_support_policy ?? '',
    product_owner_user_id: (props.product.product_owner_user_id ?? '') as
        number | '',
    security_contact_user_id: (props.product.security_contact_user_id ?? '') as
        number | '',
    scope_status: props.product.scope_status,
    scope_rationale: props.product.scope_rationale ?? '',
    classification_status: props.product.classification_status,
    classification_rationale: props.product.classification_rationale ?? '',
    classification_next_review_at:
        props.product.classification_next_review_at ?? '',
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        product_owner_user_id: data.product_owner_user_id || null,
        security_contact_user_id: data.security_contact_user_id || null,
        classification_next_review_at:
            data.classification_next_review_at || null,
    })).put(update(props.product.id).url);
};

const confirmDelete = () => {
    showDeleteDialog.value = false;
    router.delete(destroy(props.product.id).url);
};

const onScopeConfirmed = () => {
    showScopeWizard.value = false;
};

const onClassificationConfirmed = () => {
    showClassificationWizard.value = false;
};

const labelFor = (group: string, value: string): string => {
    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const formatReviewedAt = (value: string | null | undefined): string => {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};

const textareaClass =
    'border-input bg-background ring-offset-background placeholder:text-muted-foreground focus-visible:ring-ring flex w-full rounded-md border px-3 py-2 text-sm focus-visible:ring-2 focus-visible:ring-offset-2 focus-visible:outline-none';
</script>

<template>
    <Head :title="t('products.edit_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
                <p class="text-sm text-muted-foreground">
                    {{ props.organization.name }}
                </p>
                <h1 class="text-xl font-semibold whitespace-nowrap">
                    {{ t('products.edit_title') }}
                </h1>
            </div>
            <div class="flex items-center gap-2">
                <TableRowActionsMenu
                    :actions="moduleActions"
                    :label="t('common.manage')"
                    :trigger-text="t('common.manage')"
                    trigger-variant="outline"
                />
                <Button as-child variant="outline">
                    <Link :href="productsIndex()">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <form class="space-y-8 rounded-lg border p-6" @submit.prevent="submit">
            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.identity') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="name"
                            required
                            :help="t('products.help.name')"
                            >{{ t('common.name') }}</FieldLabel
                        >
                        <Input id="name" v-model="form.name" required />
                        <InputError :message="form.errors.name" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="slug"
                            :help="t('products.help.slug')"
                            >{{ t('products.fields.slug') }}</FieldLabel
                        >
                        <Input id="slug" v-model="form.slug" />
                        <InputError :message="form.errors.slug" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="product_line"
                            :help="t('products.help.product_line')"
                            >{{ t('products.fields.product_line') }}</FieldLabel
                        >
                        <Input id="product_line" v-model="form.product_line" />
                        <InputError :message="form.errors.product_line" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="description"
                            :help="t('products.help.description')"
                            >{{ t('products.fields.description') }}</FieldLabel
                        >
                        <textarea
                            id="description"
                            v-model="form.description"
                            rows="3"
                            :class="textareaClass"
                        />
                        <InputError :message="form.errors.description" />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="intended_purpose"
                            :help="t('products.help.intended_purpose')"
                            >{{
                                t('products.fields.intended_purpose')
                            }}</FieldLabel
                        >
                        <textarea
                            id="intended_purpose"
                            v-model="form.intended_purpose"
                            rows="6"
                            :class="textareaClass"
                        />
                        <InputError :message="form.errors.intended_purpose" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="product_type"
                            required
                            :help="t('products.help.product_type')"
                            >{{ t('products.fields.product_type') }}</FieldLabel
                        >
                        <select
                            id="product_type"
                            v-model="form.product_type"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.product_types"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('types', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.product_type" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="licensing_model"
                            required
                            :help="t('products.help.licensing_model')"
                            >{{
                                t('products.fields.licensing_model')
                            }}</FieldLabel
                        >
                        <select
                            id="licensing_model"
                            v-model="form.licensing_model"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.licensing_models"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('licensing', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.licensing_model" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="manufacturer"
                            :help="t('products.help.manufacturer')"
                            >{{ t('products.fields.manufacturer') }}</FieldLabel
                        >
                        <Input id="manufacturer" v-model="form.manufacturer" />
                        <InputError :message="form.errors.manufacturer" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="trademark"
                            :help="t('products.help.trademark')"
                            >{{ t('products.fields.trademark') }}</FieldLabel
                        >
                        <Input id="trademark" v-model="form.trademark" />
                        <InputError :message="form.errors.trademark" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.technical') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="flex items-center gap-3">
                        <Switch
                            id="has_remote_data_processing"
                            v-model="form.has_remote_data_processing"
                            class="cursor-pointer"
                        />
                        <FieldLabel
                            html-for="has_remote_data_processing"
                            :help="t('products.help.remote_processing')"
                        >
                            {{ t('products.fields.remote_processing') }}
                        </FieldLabel>
                    </div>
                    <div class="flex items-center gap-3">
                        <Switch
                            id="has_network_connectivity"
                            v-model="form.has_network_connectivity"
                            class="cursor-pointer"
                        />
                        <FieldLabel
                            html-for="has_network_connectivity"
                            :help="t('products.help.network_connectivity')"
                        >
                            {{ t('products.fields.network_connectivity') }}
                        </FieldLabel>
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="deployment_model"
                            :help="t('products.help.deployment_model')"
                            >{{
                                t('products.fields.deployment_model')
                            }}</FieldLabel
                        >
                        <Input
                            id="deployment_model"
                            v-model="form.deployment_model"
                        />
                        <InputError :message="form.errors.deployment_model" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.support') }}
                </h2>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="support_period_notes"
                            :help="t('products.help.support_period_notes')"
                            >{{
                                t('products.fields.support_period_notes')
                            }}</FieldLabel
                        >
                        <textarea
                            id="support_period_notes"
                            v-model="form.support_period_notes"
                            rows="2"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="form.errors.support_period_notes"
                        />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="end_of_support_policy"
                            :help="t('products.help.end_of_support_policy')"
                            >{{
                                t('products.fields.end_of_support_policy')
                            }}</FieldLabel
                        >
                        <textarea
                            id="end_of_support_policy"
                            v-model="form.end_of_support_policy"
                            rows="2"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="form.errors.end_of_support_policy"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('products.sections.scope') }}
                    </h2>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showScopeWizard = true"
                    >
                        <ClipboardList class="h-4 w-4" />
                        {{
                            latestScopeAssessment
                                ? t('products.scope_wizard.rerun')
                                : t('products.scope_wizard.start')
                        }}
                    </Button>
                </div>
                <div
                    v-if="latestScopeAssessment"
                    class="rounded-md border bg-muted/30 p-3 text-sm"
                >
                    <p class="font-medium">
                        {{ t('products.scope_wizard.last_assessment') }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{ t('products.scope_wizard.final_status') }}:
                        {{
                            labelFor(
                                'scope',
                                latestScopeAssessment.final_status,
                            )
                        }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ t('products.scope_wizard.reviewed_at') }}:
                        {{
                            formatReviewedAt(latestScopeAssessment.reviewed_at)
                        }}
                    </p>
                    <p
                        v-if="latestScopeAssessment.rationale"
                        class="mt-2 whitespace-pre-wrap"
                    >
                        {{ latestScopeAssessment.rationale }}
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('products.scope_wizard.no_assessment') }}
                </p>
                <div class="grid gap-4">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="scope_status"
                            required
                            :help="t('products.help.scope_status')"
                            >{{ t('products.fields.scope_status') }}</FieldLabel
                        >
                        <select
                            id="scope_status"
                            v-model="form.scope_status"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.scope_statuses"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('scope', value) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.scope_status" />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="scope_rationale"
                            :help="t('products.help.scope_rationale')"
                            >{{
                                t('products.fields.scope_rationale')
                            }}</FieldLabel
                        >
                        <textarea
                            id="scope_rationale"
                            v-model="form.scope_rationale"
                            rows="6"
                            :class="textareaClass"
                        />
                        <InputError :message="form.errors.scope_rationale" />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <h2
                        class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                    >
                        {{ t('products.sections.classification') }}
                    </h2>
                    <Button
                        type="button"
                        variant="outline"
                        @click="showClassificationWizard = true"
                    >
                        <Tags class="h-4 w-4" />
                        {{
                            latestClassification
                                ? t('products.classification_wizard.rerun')
                                : t('products.classification_wizard.start')
                        }}
                    </Button>
                </div>
                <div
                    v-if="latestClassification"
                    class="rounded-md border bg-muted/30 p-3 text-sm"
                >
                    <p class="font-medium">
                        {{
                            t('products.classification_wizard.last_assessment')
                        }}
                    </p>
                    <p class="mt-1 text-muted-foreground">
                        {{ t('products.classification_wizard.final_status') }}:
                        {{
                            labelFor(
                                'classification',
                                latestClassification.final_status,
                            )
                        }}
                    </p>
                    <p class="text-muted-foreground">
                        {{
                            t(
                                'products.classification_wizard.regulatory_content_version',
                            )
                        }}:
                        {{ latestClassification.regulatory_content_version }}
                    </p>
                    <p class="text-muted-foreground">
                        {{ t('products.classification_wizard.reviewed_at') }}:
                        {{ formatReviewedAt(latestClassification.reviewed_at) }}
                    </p>
                    <p
                        v-if="latestClassification.approved_at"
                        class="text-muted-foreground"
                    >
                        {{ t('products.classification_wizard.approved_at') }}:
                        {{ formatReviewedAt(latestClassification.approved_at) }}
                    </p>
                    <p
                        v-if="latestClassification.rationale"
                        class="mt-2 whitespace-pre-wrap"
                    >
                        {{ latestClassification.rationale }}
                    </p>
                </div>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('products.classification_wizard.no_assessment') }}
                </p>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="classification_status"
                            required
                            :help="t('products.help.classification_status')"
                            >{{
                                t('products.fields.classification_status')
                            }}</FieldLabel
                        >
                        <select
                            id="classification_status"
                            v-model="form.classification_status"
                            class="h-9 rounded-md border bg-background px-3"
                        >
                            <option
                                v-for="value in options.classification_statuses"
                                :key="value"
                                :value="value"
                            >
                                {{ labelFor('classification', value) }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.classification_status"
                        />
                    </div>
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="classification_rationale"
                            :help="t('products.help.classification_rationale')"
                            >{{
                                t('products.fields.classification_rationale')
                            }}</FieldLabel
                        >
                        <textarea
                            id="classification_rationale"
                            v-model="form.classification_rationale"
                            rows="6"
                            :class="textareaClass"
                        />
                        <InputError
                            :message="form.errors.classification_rationale"
                        />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="classification_next_review_at"
                            :help="t('products.help.next_review')"
                            >{{ t('products.fields.next_review') }}</FieldLabel
                        >
                        <Input
                            id="classification_next_review_at"
                            v-model="form.classification_next_review_at"
                            type="date"
                        />
                        <InputError
                            :message="form.errors.classification_next_review_at"
                        />
                    </div>
                </div>
            </section>

            <section class="space-y-4">
                <h2
                    class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
                >
                    {{ t('products.sections.contacts') }}
                </h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-2">
                        <FieldLabel
                            html-for="product_owner_user_id"
                            :help="t('products.help.product_owner')"
                            >{{
                                t('products.fields.product_owner')
                            }}</FieldLabel
                        >
                        <select
                            id="product_owner_user_id"
                            v-model="form.product_owner_user_id"
                            class="h-9 w-full max-w-full min-w-0 rounded-md border bg-background px-3"
                        >
                            <option value="">{{ t('products.none') }}</option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} ({{ member.email }})
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.product_owner_user_id"
                        />
                    </div>
                    <div class="grid min-w-0 gap-2">
                        <FieldLabel
                            html-for="security_contact_user_id"
                            :help="t('products.help.security_contact')"
                            >{{
                                t('products.fields.security_contact')
                            }}</FieldLabel
                        >
                        <select
                            id="security_contact_user_id"
                            v-model="form.security_contact_user_id"
                            class="h-9 w-full max-w-full min-w-0 rounded-md border bg-background px-3"
                        >
                            <option value="">{{ t('products.none') }}</option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} ({{ member.email }})
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.security_contact_user_id"
                        />
                    </div>
                </div>
            </section>

            <div class="flex items-center justify-between gap-3">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
                <Button
                    type="button"
                    variant="destructive"
                    @click="showDeleteDialog = true"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('common.delete') }}
                </Button>
            </div>
        </form>

        <section class="space-y-4 rounded-lg border p-6">
            <h2
                class="text-sm font-semibold tracking-wide text-muted-foreground uppercase"
            >
                {{ t('products.sections.repository') }}
            </h2>

            <div v-if="repository" class="space-y-3 rounded-lg border p-4">
                <div class="flex items-start justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 font-medium">
                            <GitBranch class="h-4 w-4" />
                            <a
                                :href="repository.remote_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="underline-offset-4 hover:underline"
                            >
                                {{ repository.full_name }}
                            </a>
                        </div>
                        <p
                            v-if="repository.default_branch"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.repository.default_branch') }}:
                            {{ repository.default_branch }}
                        </p>
                        <p
                            v-if="repository.last_synced_at"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.repository.last_synced') }}:
                            {{
                                new Date(
                                    repository.last_synced_at,
                                ).toLocaleString()
                            }}
                        </p>
                        <div
                            v-if="repository.last_sync_summary"
                            class="space-y-1 text-sm text-muted-foreground"
                        >
                            <p v-if="repository.last_sync_summary.error">
                                {{ t('products.repository.sync_error') }}:
                                {{ repository.last_sync_summary.error }}
                            </p>
                            <template v-else>
                                <p>
                                    {{ t('products.repository.tags') }}:
                                    {{
                                        repository.last_sync_summary
                                            .tags_count ?? 0
                                    }}
                                    <span
                                        v-if="
                                            repository.last_sync_summary
                                                .latest_tag
                                        "
                                    >
                                        ({{
                                            repository.last_sync_summary
                                                .latest_tag
                                        }})
                                    </span>
                                </p>
                                <p>
                                    {{ t('products.repository.releases') }}:
                                    {{
                                        repository.last_sync_summary
                                            .releases_count ?? 0
                                    }}
                                    <span
                                        v-if="
                                            repository.last_sync_summary
                                                .latest_release
                                        "
                                    >
                                        ({{
                                            repository.last_sync_summary
                                                .latest_release
                                        }})
                                    </span>
                                </p>
                                <p>
                                    {{ t('products.repository.ci_status') }}:
                                    {{ ciLabel(repository.last_sync_summary) }}
                                    <a
                                        v-if="
                                            repository.last_sync_summary.ci
                                                ?.html_url
                                        "
                                        :href="
                                            repository.last_sync_summary.ci
                                                .html_url
                                        "
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="ml-1 underline-offset-4 hover:underline"
                                    >
                                        {{ t('products.repository.view_run') }}
                                    </a>
                                </p>
                            </template>
                        </div>
                    </div>
                    <div class="flex shrink-0 flex-col gap-2 sm:flex-row">
                        <Button
                            type="button"
                            variant="outline"
                            :disabled="syncingRepository"
                            data-test="sync-repository-button"
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
                        <Button
                            type="button"
                            variant="destructive"
                            @click="showUnlinkRepositoryDialog = true"
                        >
                            <Trash2 class="h-4 w-4" />
                            {{ t('products.repository.unlink') }}
                        </Button>
                    </div>
                </div>
            </div>

            <div
                v-if="activeVcsConnections.length === 0"
                class="space-y-2 text-sm text-muted-foreground"
            >
                <p>{{ t('products.repository.no_connection') }}</p>
                <Button type="button" variant="outline" as-child>
                    <Link :href="editIntegrations()">
                        {{ t('products.repository.open_integrations') }}
                    </Link>
                </Button>
            </div>

            <form v-else class="space-y-4" @submit.prevent="linkRepository">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="vcs_connection_id"
                            :help="t('products.repository.connection_help')"
                        >
                            {{ t('products.repository.connection') }}
                        </FieldLabel>
                        <select
                            id="vcs_connection_id"
                            v-model="repositoryForm.connection_id"
                            class="h-9 w-full rounded-md border bg-background px-3"
                            required
                        >
                            <option
                                v-for="connection in activeVcsConnections"
                                :key="connection.id"
                                :value="connection.id"
                            >
                                {{
                                    connection.label ||
                                    t('settings.integrations.github')
                                }}
                            </option>
                        </select>
                        <InputError
                            :message="repositoryForm.errors.connection_id"
                        />
                    </div>
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="repository_input"
                            :help="t('products.repository.help')"
                        >
                            {{ t('products.repository.field') }}
                        </FieldLabel>
                        <Input
                            id="repository_input"
                            v-model="repositoryForm.repository"
                            :placeholder="t('products.repository.placeholder')"
                            required
                        />
                        <InputError
                            :message="repositoryForm.errors.repository"
                        />
                    </div>
                </div>
                <Button
                    type="submit"
                    :disabled="repositoryForm.processing"
                    data-test="link-repository-button"
                >
                    <Save class="h-4 w-4" />
                    {{
                        repository
                            ? t('products.repository.update')
                            : t('products.repository.link')
                    }}
                </Button>
            </form>
        </section>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.confirm_delete')"
            @confirm="confirmDelete"
        />

        <AppAlertDialog
            v-model:open="showUnlinkRepositoryDialog"
            :title="t('products.repository.unlink_confirm_title')"
            :description="t('products.repository.unlink_confirm')"
            :confirm-label="t('products.repository.unlink')"
            @confirm="confirmUnlinkRepository"
        />

        <ScopeWizard
            v-model:open="showScopeWizard"
            :product-id="product.id"
            :product-types="options.product_types"
            :scope-statuses="options.scope_statuses"
            :initial-answers="latestScopeAssessment?.answers ?? null"
            @confirmed="onScopeConfirmed"
        />

        <ClassificationWizard
            v-model:open="showClassificationWizard"
            :product-id="product.id"
            :classification-statuses="options.classification_statuses"
            :initial-answers="latestClassification?.answers ?? null"
            :initial-regulatory-content-version="
                latestClassification?.regulatory_content_version ?? null
            "
            :initial-evidence-notes="
                latestClassification?.evidence_notes ?? null
            "
            :initial-next-review-at="
                latestClassification?.next_review_at ??
                product.classification_next_review_at ??
                null
            "
            @confirmed="onClassificationConfirmed"
        />
    </div>
</template>
