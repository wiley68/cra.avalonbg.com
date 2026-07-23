<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ExternalLink,
    Link2,
    Plus,
    Save,
    Trash2,
    Unlink,
} from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    createVulnerability as createIncidentVulnerability,
    destroy as destroyProductIncident,
    edit as productIncidentsEdit,
    index as productIncidentsIndex,
    linkVulnerability as linkIncidentVulnerability,
    unlinkVulnerability as unlinkIncidentVulnerability,
    update,
} from '@/routes/products/incidents';
import { store as storeTimelineEvent } from '@/routes/products/incidents/timeline';
import { edit as editProductVulnerability } from '@/routes/products/vulnerabilities';
import { edit as editProduct, index as productsIndex } from '@/routes/products';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type CustomerOption = { id: number; name: string; is_active: boolean };
type DeploymentOption = {
    id: number;
    customer_id: number;
    customer_name: string;
    environment: string;
    product_version_number: string | null;
};
type ProductSummary = { id: number; name: string; slug: string };
type VulnerabilityOption = {
    id: number;
    title: string;
    cve_id: string | null;
    status: string;
};
type LinkedVulnerability = {
    id: number;
    title: string;
    cve_id: string | null;
    status: string;
    business_severity: string;
};
type TimelineEvent = {
    id: number;
    occurred_at: string;
    label: string;
    notes: string | null;
    created_by: string | null;
    created_at: string | null;
};
type IncidentDetail = {
    id: number;
    title: string;
    status: string;
    severity: string;
    summary: string | null;
    root_cause: string | null;
    corrective_measures: string | null;
    lessons_learned: string | null;
    owner_user_id: number | null;
    actual_started_at: string | null;
    detected_at: string | null;
    awareness_at: string | null;
    classified_at: string | null;
    notes: string | null;
    version_ids: number[];
    customer_ids: number[];
    deployment_ids: number[];
    product_vulnerability_id: number | null;
    linked_vulnerability: LinkedVulnerability | null;
    timeline_events: TimelineEvent[];
};

const props = defineProps<{
    product: ProductSummary;
    incident: IncidentDetail;
    members: Member[];
    versions: VersionOption[];
    customers: CustomerOption[];
    deployments: DeploymentOption[];
    vulnerabilities: VulnerabilityOption[];
    options: {
        statuses: string[];
        severities: string[];
    };
    canManage: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.incidents.index_title',
        href: productIncidentsIndex(props.product.id),
    },
    {
        title: props.incident.title,
        href: productIncidentsEdit({
            product: props.product.id,
            incident: props.incident.id,
        }),
    },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const showDeleteDialog = ref(false);

const nowLocalDatetime = (): string => {
    const date = new Date();
    const pad = (value: number) => String(value).padStart(2, '0');

    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
};

const form = useForm({
    title: props.incident.title,
    summary: props.incident.summary ?? '',
    status: props.incident.status,
    severity: props.incident.severity,
    root_cause: props.incident.root_cause ?? '',
    corrective_measures: props.incident.corrective_measures ?? '',
    lessons_learned: props.incident.lessons_learned ?? '',
    owner_user_id: (props.incident.owner_user_id ?? '') as number | '',
    actual_started_at: props.incident.actual_started_at ?? '',
    detected_at: props.incident.detected_at ?? '',
    awareness_at: props.incident.awareness_at ?? '',
    classified_at: props.incident.classified_at ?? '',
    notes: props.incident.notes ?? '',
    version_ids: [...props.incident.version_ids],
    customer_ids: [...props.incident.customer_ids],
    deployment_ids: [...props.incident.deployment_ids],
});

const timelineForm = useForm({
    occurred_at: nowLocalDatetime(),
    label: '',
    notes: '',
});

const linkForm = useForm({
    product_vulnerability_id: '' as number | '',
});

const createVulnerabilityForm = useForm({});

const coreTimestampRows = computed(
    () =>
        [
            {
                key: 'actual_started_at',
                label: t('products.incidents.fields.actual_started_at'),
                value: props.incident.actual_started_at,
            },
            {
                key: 'detected_at',
                label: t('products.incidents.fields.detected_at'),
                value: props.incident.detected_at,
            },
            {
                key: 'awareness_at',
                label: t('products.incidents.fields.awareness_at'),
                value: props.incident.awareness_at,
            },
            {
                key: 'classified_at',
                label: t('products.incidents.fields.classified_at'),
                value: props.incident.classified_at,
            },
        ] as const,
);

const linkedVulnerability = computed(() => props.incident.linked_vulnerability);

const vulnerabilityEditUrl = computed(() => {
    if (!linkedVulnerability.value) {
        return null;
    }

    return editProductVulnerability({
        product: props.product.id,
        vulnerability: linkedVulnerability.value.id,
    }).url;
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        owner_user_id: data.owner_user_id || null,
        actual_started_at: data.actual_started_at || null,
        detected_at: data.detected_at || null,
        awareness_at: data.awareness_at || null,
        classified_at: data.classified_at || null,
    })).put(
        update({
            product: props.product.id,
            incident: props.incident.id,
        }).url,
    );
};

const submitTimeline = () => {
    timelineForm
        .transform((data) => ({
            ...data,
            notes: data.notes || null,
        }))
        .post(
            storeTimelineEvent({
                product: props.product.id,
                incident: props.incident.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    timelineForm.reset();
                    timelineForm.occurred_at = nowLocalDatetime();
                },
            },
        );
};

const submitLinkVulnerability = () => {
    linkForm
        .transform((data) => ({
            product_vulnerability_id: data.product_vulnerability_id || null,
        }))
        .post(
            linkIncidentVulnerability({
                product: props.product.id,
                incident: props.incident.id,
            }).url,
            {
                preserveScroll: true,
                onSuccess: () => {
                    linkForm.reset();
                },
            },
        );
};

const unlinkVulnerability = () => {
    router.delete(
        unlinkIncidentVulnerability({
            product: props.product.id,
            incident: props.incident.id,
        }).url,
        { preserveScroll: true },
    );
};

const createVulnerabilityFromIncident = () => {
    createVulnerabilityForm.post(
        createIncidentVulnerability({
            product: props.product.id,
            incident: props.incident.id,
        }).url,
    );
};

const confirmDelete = () => {
    showDeleteDialog.value = false;
    router.delete(
        destroyProductIncident({
            product: props.product.id,
            incident: props.incident.id,
        }).url,
    );
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.incidents.${group}.${value}`;
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

const toggleId = (
    field: 'version_ids' | 'customer_ids' | 'deployment_ids',
    id: number,
    checked: boolean,
) => {
    if (checked) {
        if (!form[field].includes(id)) {
            form[field].push(id);
        }

        return;
    }

    form[field] = form[field].filter((value) => value !== id);
};

const deploymentLabel = (deployment: DeploymentOption): string => {
    const envKey = `products.deployments.environments.${deployment.environment}`;
    const environment =
        t(envKey) === envKey ? deployment.environment : t(envKey);
    const version = deployment.product_version_number ?? '—';

    return `${deployment.customer_name} — ${environment} (${version})`;
};
</script>

<template>
    <Head :title="t('products.incidents.edit_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.incidents.edit_title') }}
                </h1>
            </div>
            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="productIncidentsIndex(props.product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button
                    v-if="canManage"
                    variant="destructive"
                    type="button"
                    @click="showDeleteDialog = true"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('common.delete') }}
                </Button>
            </div>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <fieldset :disabled="!canManage" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="title"
                            required
                            :help="t('products.incidents.help.title')"
                        >
                            {{ t('products.incidents.fields.title') }}
                        </FieldLabel>
                        <Input id="title" v-model="form.title" required />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="summary"
                            :help="t('products.incidents.help.summary')"
                        >
                            {{ t('products.incidents.fields.summary') }}
                        </FieldLabel>
                        <textarea
                            id="summary"
                            v-model="form.summary"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="form.errors.summary" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="status"
                            required
                            :help="t('products.incidents.help.status')"
                        >
                            {{ t('products.incidents.fields.status') }}
                        </FieldLabel>
                        <select
                            id="status"
                            v-model="form.status"
                            required
                            :class="selectClass"
                        >
                            <option
                                v-for="status in options.statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ enumLabel('statuses', status) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.status" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="severity"
                            required
                            :help="t('products.incidents.help.severity')"
                        >
                            {{ t('products.incidents.fields.severity') }}
                        </FieldLabel>
                        <select
                            id="severity"
                            v-model="form.severity"
                            required
                            :class="selectClass"
                        >
                            <option
                                v-for="severity in options.severities"
                                :key="severity"
                                :value="severity"
                            >
                                {{ enumLabel('severities', severity) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.severity" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="owner_user_id"
                            :help="t('products.incidents.help.owner')"
                        >
                            {{ t('products.incidents.fields.owner') }}
                        </FieldLabel>
                        <select
                            id="owner_user_id"
                            v-model="form.owner_user_id"
                            :class="selectClass"
                        >
                            <option value="">
                                {{ t('products.none') }}
                            </option>
                            <option
                                v-for="member in members"
                                :key="member.id"
                                :value="member.id"
                            >
                                {{ member.name }} ({{ member.email }})
                            </option>
                        </select>
                        <InputError :message="form.errors.owner_user_id" />
                    </div>
                </div>

                <div class="space-y-3">
                    <div>
                        <h2 class="text-base font-semibold">
                            {{ t('products.incidents.core_timestamps_title') }}
                        </h2>
                        <p class="text-sm text-muted-foreground">
                            {{
                                t('products.incidents.core_timestamps_subtitle')
                            }}
                        </p>
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <FieldLabel
                                html-for="actual_started_at"
                                :help="
                                    t(
                                        'products.incidents.help.actual_started_at',
                                    )
                                "
                            >
                                {{
                                    t(
                                        'products.incidents.fields.actual_started_at',
                                    )
                                }}
                            </FieldLabel>
                            <Input
                                id="actual_started_at"
                                v-model="form.actual_started_at"
                                type="datetime-local"
                            />
                            <InputError
                                :message="form.errors.actual_started_at"
                            />
                        </div>

                        <div class="grid gap-2">
                            <FieldLabel
                                html-for="detected_at"
                                :help="t('products.incidents.help.detected_at')"
                            >
                                {{ t('products.incidents.fields.detected_at') }}
                            </FieldLabel>
                            <Input
                                id="detected_at"
                                v-model="form.detected_at"
                                type="datetime-local"
                            />
                            <InputError :message="form.errors.detected_at" />
                        </div>

                        <div class="grid gap-2">
                            <FieldLabel
                                html-for="awareness_at"
                                :help="
                                    t('products.incidents.help.awareness_at')
                                "
                            >
                                {{
                                    t('products.incidents.fields.awareness_at')
                                }}
                            </FieldLabel>
                            <Input
                                id="awareness_at"
                                v-model="form.awareness_at"
                                type="datetime-local"
                            />
                            <InputError :message="form.errors.awareness_at" />
                        </div>

                        <div class="grid gap-2">
                            <FieldLabel
                                html-for="classified_at"
                                :help="
                                    t('products.incidents.help.classified_at')
                                "
                            >
                                {{
                                    t('products.incidents.fields.classified_at')
                                }}
                            </FieldLabel>
                            <Input
                                id="classified_at"
                                v-model="form.classified_at"
                                type="datetime-local"
                            />
                            <InputError :message="form.errors.classified_at" />
                        </div>
                    </div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="root_cause"
                            :help="t('products.incidents.help.root_cause')"
                        >
                            {{ t('products.incidents.fields.root_cause') }}
                        </FieldLabel>
                        <textarea
                            id="root_cause"
                            v-model="form.root_cause"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="form.errors.root_cause" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="corrective_measures"
                            :help="
                                t('products.incidents.help.corrective_measures')
                            "
                        >
                            {{
                                t(
                                    'products.incidents.fields.corrective_measures',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="corrective_measures"
                            v-model="form.corrective_measures"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError
                            :message="form.errors.corrective_measures"
                        />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="lessons_learned"
                            :help="t('products.incidents.help.lessons_learned')"
                        >
                            {{ t('products.incidents.fields.lessons_learned') }}
                        </FieldLabel>
                        <textarea
                            id="lessons_learned"
                            v-model="form.lessons_learned"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="form.errors.lessons_learned" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="notes"
                            :help="t('products.incidents.help.notes')"
                        >
                            {{ t('products.incidents.fields.notes') }}
                        </FieldLabel>
                        <textarea
                            id="notes"
                            v-model="form.notes"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="form.errors.notes" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <FieldLabel :help="t('products.incidents.help.versions')">
                        {{ t('products.incidents.fields.versions') }}
                    </FieldLabel>
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <p
                            v-if="versions.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.incidents.no_versions') }}
                        </p>
                        <label
                            v-for="version in versions"
                            :key="version.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="form.version_ids.includes(version.id)"
                                @change="
                                    toggleId(
                                        'version_ids',
                                        version.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>{{ version.version_number }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.version_ids" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel :help="t('products.incidents.help.customers')">
                        {{ t('products.incidents.fields.customers') }}
                    </FieldLabel>
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <p
                            v-if="customers.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.incidents.no_customers') }}
                        </p>
                        <label
                            v-for="customer in customers"
                            :key="customer.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="
                                    form.customer_ids.includes(customer.id)
                                "
                                @change="
                                    toggleId(
                                        'customer_ids',
                                        customer.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>
                                {{ customer.name }}
                                <span
                                    v-if="!customer.is_active"
                                    class="text-muted-foreground"
                                >
                                    ({{ t('customers.inactive') }})
                                </span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.customer_ids" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        :help="t('products.incidents.help.deployments')"
                    >
                        {{ t('products.incidents.fields.deployments') }}
                    </FieldLabel>
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <p
                            v-if="deployments.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.incidents.no_deployments') }}
                        </p>
                        <label
                            v-for="deployment in deployments"
                            :key="deployment.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="
                                    form.deployment_ids.includes(deployment.id)
                                "
                                @change="
                                    toggleId(
                                        'deployment_ids',
                                        deployment.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>{{ deploymentLabel(deployment) }}</span>
                        </label>
                    </div>
                    <InputError :message="form.errors.deployment_ids" />
                </div>
            </fieldset>

            <div v-if="canManage" class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <section class="space-y-4 border-t pt-6">
            <div>
                <h2 class="text-base font-semibold">
                    {{ t('products.incidents.vulnerability_title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.incidents.vulnerability_subtitle') }}
                </p>
            </div>

            <div
                v-if="linkedVulnerability"
                class="rounded-md border px-3 py-3 text-sm"
            >
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div class="space-y-1">
                        <div class="font-medium">
                            {{ linkedVulnerability.title }}
                        </div>
                        <div class="text-xs text-muted-foreground">
                            <span v-if="linkedVulnerability.cve_id">
                                {{ linkedVulnerability.cve_id }} ·
                            </span>
                            {{
                                t(
                                    `products.vulnerabilities.statuses.${linkedVulnerability.status}`,
                                )
                            }}
                            ·
                            {{
                                t(
                                    `products.vulnerabilities.severities.${linkedVulnerability.business_severity}`,
                                )
                            }}
                        </div>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <Button
                            v-if="vulnerabilityEditUrl"
                            as-child
                            variant="outline"
                            size="sm"
                        >
                            <Link :href="vulnerabilityEditUrl">
                                <ExternalLink class="h-4 w-4" />
                                {{ t('products.incidents.vulnerability_open') }}
                            </Link>
                        </Button>
                        <Button
                            v-if="canManage"
                            type="button"
                            variant="outline"
                            size="sm"
                            @click="unlinkVulnerability"
                        >
                            <Unlink class="h-4 w-4" />
                            {{ t('products.incidents.vulnerability_unlink') }}
                        </Button>
                    </div>
                </div>
            </div>

            <p v-else class="text-sm text-muted-foreground">
                {{ t('products.incidents.vulnerability_empty') }}
            </p>

            <div
                v-if="canManage"
                class="grid gap-4 rounded-md border p-4 sm:grid-cols-2"
            >
                <form
                    class="space-y-3"
                    @submit.prevent="submitLinkVulnerability"
                >
                    <FieldLabel
                        html-for="product_vulnerability_id"
                        :help="t('products.incidents.help.vulnerability_link')"
                    >
                        {{ t('products.incidents.vulnerability_link') }}
                    </FieldLabel>
                    <select
                        id="product_vulnerability_id"
                        v-model="linkForm.product_vulnerability_id"
                        required
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('products.incidents.vulnerability_none') }}
                        </option>
                        <option
                            v-for="vulnerability in vulnerabilities"
                            :key="vulnerability.id"
                            :value="vulnerability.id"
                        >
                            {{ vulnerability.title }}
                            <template v-if="vulnerability.cve_id">
                                ({{ vulnerability.cve_id }})
                            </template>
                        </option>
                    </select>
                    <p
                        v-if="vulnerabilities.length === 0"
                        class="text-xs text-muted-foreground"
                    >
                        {{ t('products.incidents.no_vulnerabilities') }}
                    </p>
                    <InputError
                        :message="linkForm.errors.product_vulnerability_id"
                    />
                    <Button
                        type="submit"
                        size="sm"
                        :disabled="
                            linkForm.processing || vulnerabilities.length === 0
                        "
                    >
                        <Link2 class="h-4 w-4" />
                        {{ t('products.incidents.vulnerability_link') }}
                    </Button>
                </form>

                <div class="space-y-3">
                    <p class="text-sm text-muted-foreground">
                        {{ t('products.incidents.vulnerability_create_help') }}
                    </p>
                    <Button
                        type="button"
                        size="sm"
                        :disabled="createVulnerabilityForm.processing"
                        @click="createVulnerabilityFromIncident"
                    >
                        <Plus class="h-4 w-4" />
                        {{ t('products.incidents.vulnerability_create') }}
                    </Button>
                </div>
            </div>
        </section>

        <section class="space-y-4 border-t pt-6">
            <div>
                <h2 class="text-base font-semibold">
                    {{ t('products.incidents.timeline_title') }}
                </h2>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.incidents.timeline_subtitle') }}
                </p>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="row in coreTimestampRows"
                    :key="row.key"
                    class="rounded-md border px-3 py-2 text-sm"
                >
                    <div class="text-muted-foreground">{{ row.label }}</div>
                    <div class="font-medium">
                        {{ formatDateTime(row.value) }}
                    </div>
                </div>
            </div>

            <div
                v-if="incident.timeline_events.length === 0"
                class="text-sm text-muted-foreground"
            >
                {{ t('products.incidents.timeline_empty') }}
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="event in incident.timeline_events"
                    :key="event.id"
                    class="rounded-md border px-3 py-2 text-sm"
                >
                    <div
                        class="flex flex-wrap items-center justify-between gap-2"
                    >
                        <span class="font-medium">{{ event.label }}</span>
                        <span class="text-xs text-muted-foreground">
                            {{ formatDateTime(event.occurred_at) }}
                        </span>
                    </div>
                    <div
                        v-if="event.created_by"
                        class="mt-1 text-xs text-muted-foreground"
                    >
                        {{
                            t('products.incidents.timeline_recorded_by', {
                                name: event.created_by,
                            })
                        }}
                    </div>
                    <p
                        v-if="event.notes"
                        class="mt-2 whitespace-pre-wrap text-muted-foreground"
                    >
                        {{ event.notes }}
                    </p>
                </div>
            </div>

            <form
                v-if="canManage"
                class="space-y-4 rounded-md border p-4"
                @submit.prevent="submitTimeline"
            >
                <h3 class="text-sm font-medium">
                    {{ t('products.incidents.timeline_add') }}
                </h3>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="timeline_occurred_at"
                            required
                            :help="
                                t(
                                    'products.incidents.help.timeline_occurred_at',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.incidents.fields.timeline_occurred_at',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            id="timeline_occurred_at"
                            v-model="timelineForm.occurred_at"
                            type="datetime-local"
                            required
                        />
                        <InputError
                            :message="timelineForm.errors.occurred_at"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="timeline_label"
                            required
                            :help="t('products.incidents.help.timeline_label')"
                        >
                            {{ t('products.incidents.fields.timeline_label') }}
                        </FieldLabel>
                        <Input
                            id="timeline_label"
                            v-model="timelineForm.label"
                            required
                        />
                        <InputError :message="timelineForm.errors.label" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="timeline_notes"
                            :help="t('products.incidents.help.timeline_notes')"
                        >
                            {{ t('products.incidents.fields.timeline_notes') }}
                        </FieldLabel>
                        <textarea
                            id="timeline_notes"
                            v-model="timelineForm.notes"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="timelineForm.errors.notes" />
                    </div>
                </div>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="timelineForm.processing">
                        <Plus class="h-4 w-4" />
                        {{ t('products.incidents.timeline_add') }}
                    </Button>
                </div>
            </form>
        </section>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.incidents.confirm_delete')"
            @confirm="confirmDelete"
        />
    </div>
</template>
