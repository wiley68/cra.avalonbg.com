<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus, Save, Sparkles, Trash2 } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
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
import { Switch } from '@/components/ui/switch';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { triage as triageVulnerability } from '@/routes/products/assistant';
import {
    create as campaignsCreate,
    show as campaignsShow,
} from '@/routes/products/campaigns';
import {
    destroy as destroyProductVulnerability,
    index as productVulnerabilitiesIndex,
    update,
} from '@/routes/products/vulnerabilities';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { edit as productVulnerabilitiesEdit } from '@/routes/products/vulnerabilities';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type ComponentOption = {
    id: number;
    name: string;
    version: string | null;
    version_number: string | null;
};
type ProductSummary = { id: number; name: string; slug: string };
type PatchCampaignSummary = {
    id: number;
    title: string;
    status: string;
    target_version_number: string | null;
    started_at: string | null;
    completed_at: string | null;
};
type VulnerabilityDetail = {
    id: number;
    title: string;
    summary: string | null;
    cve_id: string | null;
    advisory_url: string | null;
    discovery_source: string;
    discovered_at: string | null;
    awareness_at: string | null;
    status: string;
    cvss_score: number | null;
    business_severity: string;
    exploitation_status: string;
    is_public: boolean;
    workaround: string | null;
    corrective_action: string | null;
    owner_user_id: number | null;
    substitute_owner_user_id: number | null;
    corrective_measure_available_at: string | null;
    notes: string | null;
    component_ids: number[];
    affected_version_ids: number[];
    fixed_version_ids: number[];
    deadline_24h: string | null;
    deadline_72h: string | null;
    overdue_24h: boolean;
    overdue_72h: boolean;
    patch_campaigns: PatchCampaignSummary[];
};

const props = defineProps<{
    product: ProductSummary;
    vulnerability: VulnerabilityDetail;
    members: Member[];
    versions: VersionOption[];
    components: ComponentOption[];
    options: {
        statuses: string[];
        discovery_sources: string[];
        severities: string[];
        exploitation_statuses: string[];
    };
    canManage: boolean;
    canManageCampaigns: boolean;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.vulnerabilities.index_title',
        href: productVulnerabilitiesIndex(props.product.id),
    },
    {
        title: props.vulnerability.title,
        href: productVulnerabilitiesEdit({
            product: props.product.id,
            vulnerability: props.vulnerability.id,
        }),
    },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const showDeleteDialog = ref(false);
const showTriageDialog = ref(false);

const triageForm = useForm({
    vulnerability_id: props.vulnerability.id,
    note: '',
});

const triageError = computed((): string | undefined => {
    const errors = triageForm.errors as Record<string, string | undefined>;

    return errors.assistant ?? errors.vulnerability_id ?? errors.note;
});

const form = useForm({
    title: props.vulnerability.title,
    summary: props.vulnerability.summary ?? '',
    cve_id: props.vulnerability.cve_id ?? '',
    advisory_url: props.vulnerability.advisory_url ?? '',
    discovery_source: props.vulnerability.discovery_source,
    discovered_at: props.vulnerability.discovered_at ?? '',
    awareness_at: props.vulnerability.awareness_at ?? '',
    status: props.vulnerability.status,
    cvss_score: (props.vulnerability.cvss_score ?? '') as number | '',
    business_severity: props.vulnerability.business_severity,
    exploitation_status: props.vulnerability.exploitation_status,
    is_public: props.vulnerability.is_public,
    workaround: props.vulnerability.workaround ?? '',
    corrective_action: props.vulnerability.corrective_action ?? '',
    owner_user_id: (props.vulnerability.owner_user_id ?? '') as number | '',
    substitute_owner_user_id: (props.vulnerability.substitute_owner_user_id ??
        '') as number | '',
    corrective_measure_available_at:
        props.vulnerability.corrective_measure_available_at ?? '',
    notes: props.vulnerability.notes ?? '',
    component_ids: [...props.vulnerability.component_ids],
    affected_version_ids: [...props.vulnerability.affected_version_ids],
    fixed_version_ids: [...props.vulnerability.fixed_version_ids],
});

const deadlinePreview = computed(() => {
    if (!form.awareness_at) {
        return null;
    }

    const awareness = new Date(form.awareness_at);

    if (Number.isNaN(awareness.getTime())) {
        return null;
    }

    const d24 = new Date(awareness.getTime() + 24 * 60 * 60 * 1000);
    const d72 = new Date(awareness.getTime() + 72 * 60 * 60 * 1000);

    return {
        deadline_24h: d24.toLocaleString(),
        deadline_72h: d72.toLocaleString(),
    };
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        owner_user_id: data.owner_user_id || null,
        substitute_owner_user_id: data.substitute_owner_user_id || null,
        corrective_measure_available_at:
            data.corrective_measure_available_at || null,
        cvss_score: data.cvss_score === '' ? null : data.cvss_score,
        discovered_at: data.discovered_at || null,
        awareness_at: data.awareness_at || null,
    })).put(
        update({
            product: props.product.id,
            vulnerability: props.vulnerability.id,
        }).url,
    );
};

const confirmDelete = () => {
    showDeleteDialog.value = false;
    router.delete(
        destroyProductVulnerability({
            product: props.product.id,
            vulnerability: props.vulnerability.id,
        }).url,
    );
};

const openTriageDialog = (): void => {
    triageForm.vulnerability_id = props.vulnerability.id;
    triageForm.note = '';
    triageForm.clearErrors();
    showTriageDialog.value = true;
};

const submitTriage = (): void => {
    triageForm.post(triageVulnerability(props.product.id).url, {
        preserveScroll: true,
        onSuccess: () => {
            showTriageDialog.value = false;
            triageForm.reset('note');
        },
    });
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.vulnerabilities.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const campaignStatusLabel = (value: string): string => {
    const key = `products.campaigns.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const startCampaignUrl = computed(() =>
    campaignsCreate.url(props.product.id, {
        query: { product_vulnerability_id: props.vulnerability.id },
    }),
);

const toggleId = (
    field: 'component_ids' | 'affected_version_ids' | 'fixed_version_ids',
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
</script>

<template>
    <Head :title="t('products.vulnerabilities.edit_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.vulnerabilities.edit_title') }}
                </h1>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2">
                <Button
                    v-if="canManage"
                    type="button"
                    variant="outline"
                    @click="openTriageDialog"
                >
                    <Sparkles class="h-4 w-4" />
                    {{ t('products.vulnerabilities.ai_triage') }}
                </Button>
                <Button as-child variant="outline">
                    <Link :href="productVulnerabilitiesIndex(props.product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
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
                            :help="t('products.vulnerabilities.help.title')"
                        >
                            {{ t('products.vulnerabilities.fields.title') }}
                        </FieldLabel>
                        <Input id="title" v-model="form.title" required />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="summary"
                            :help="t('products.vulnerabilities.help.summary')"
                        >
                            {{ t('products.vulnerabilities.fields.summary') }}
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
                            html-for="cve_id"
                            :help="t('products.vulnerabilities.help.cve_id')"
                        >
                            {{ t('products.vulnerabilities.fields.cve_id') }}
                        </FieldLabel>
                        <Input id="cve_id" v-model="form.cve_id" />
                        <InputError :message="form.errors.cve_id" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="advisory_url"
                            :help="
                                t('products.vulnerabilities.help.advisory_url')
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.advisory_url',
                                )
                            }}
                        </FieldLabel>
                        <Input id="advisory_url" v-model="form.advisory_url" />
                        <InputError :message="form.errors.advisory_url" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="discovery_source"
                            required
                            :help="
                                t(
                                    'products.vulnerabilities.help.discovery_source',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.discovery_source',
                                )
                            }}
                        </FieldLabel>
                        <select
                            id="discovery_source"
                            v-model="form.discovery_source"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="source in options.discovery_sources"
                                :key="source"
                                :value="source"
                            >
                                {{ enumLabel('discovery_sources', source) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.discovery_source" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="status"
                            required
                            :help="t('products.vulnerabilities.help.status')"
                        >
                            {{ t('products.vulnerabilities.fields.status') }}
                        </FieldLabel>
                        <select
                            id="status"
                            v-model="form.status"
                            :class="selectClass"
                            required
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
                            html-for="discovered_at"
                            :help="
                                t('products.vulnerabilities.help.discovered_at')
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.discovered_at',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            id="discovered_at"
                            v-model="form.discovered_at"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.discovered_at" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="awareness_at"
                            :help="
                                t('products.vulnerabilities.help.awareness_at')
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.awareness_at',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            id="awareness_at"
                            v-model="form.awareness_at"
                            type="datetime-local"
                        />
                        <InputError :message="form.errors.awareness_at" />
                        <p
                            v-if="deadlinePreview"
                            class="text-xs text-muted-foreground"
                        >
                            {{ t('products.vulnerabilities.deadline_24h') }}:
                            {{ deadlinePreview.deadline_24h }} ·
                            {{ t('products.vulnerabilities.deadline_72h') }}:
                            {{ deadlinePreview.deadline_72h }}
                        </p>
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="business_severity"
                            required
                            :help="
                                t(
                                    'products.vulnerabilities.help.business_severity',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.business_severity',
                                )
                            }}
                        </FieldLabel>
                        <select
                            id="business_severity"
                            v-model="form.business_severity"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="severity in options.severities"
                                :key="severity"
                                :value="severity"
                            >
                                {{ enumLabel('severities', severity) }}
                            </option>
                        </select>
                        <InputError :message="form.errors.business_severity" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="exploitation_status"
                            required
                            :help="
                                t(
                                    'products.vulnerabilities.help.exploitation_status',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.exploitation_status',
                                )
                            }}
                        </FieldLabel>
                        <select
                            id="exploitation_status"
                            v-model="form.exploitation_status"
                            :class="selectClass"
                            required
                        >
                            <option
                                v-for="status in options.exploitation_statuses"
                                :key="status"
                                :value="status"
                            >
                                {{ enumLabel('exploitation_statuses', status) }}
                            </option>
                        </select>
                        <InputError
                            :message="form.errors.exploitation_status"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="cvss_score"
                            :help="
                                t('products.vulnerabilities.help.cvss_score')
                            "
                        >
                            {{
                                t('products.vulnerabilities.fields.cvss_score')
                            }}
                        </FieldLabel>
                        <Input
                            id="cvss_score"
                            v-model="form.cvss_score"
                            type="number"
                            min="0"
                            max="10"
                            step="0.1"
                        />
                        <InputError :message="form.errors.cvss_score" />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="owner_user_id"
                            :help="t('products.vulnerabilities.help.owner')"
                        >
                            {{ t('products.vulnerabilities.fields.owner') }}
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

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="substitute_owner_user_id"
                            :help="
                                t(
                                    'products.vulnerabilities.help.substitute_owner',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.substitute_owner',
                                )
                            }}
                        </FieldLabel>
                        <select
                            id="substitute_owner_user_id"
                            v-model="form.substitute_owner_user_id"
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
                        <InputError
                            :message="form.errors.substitute_owner_user_id"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            html-for="corrective_measure_available_at"
                            :help="
                                t(
                                    'products.vulnerabilities.help.corrective_measure_available_at',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.corrective_measure_available_at',
                                )
                            }}
                        </FieldLabel>
                        <Input
                            id="corrective_measure_available_at"
                            v-model="form.corrective_measure_available_at"
                            type="datetime-local"
                        />
                        <InputError
                            :message="
                                form.errors.corrective_measure_available_at
                            "
                        />
                    </div>

                    <div class="flex items-center gap-3 sm:col-span-2">
                        <Switch
                            id="is_public"
                            v-model="form.is_public"
                            class="cursor-pointer"
                        />
                        <FieldLabel
                            html-for="is_public"
                            :help="t('products.vulnerabilities.help.is_public')"
                        >
                            {{ t('products.vulnerabilities.fields.is_public') }}
                        </FieldLabel>
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="workaround"
                            :help="
                                t('products.vulnerabilities.help.workaround')
                            "
                        >
                            {{
                                t('products.vulnerabilities.fields.workaround')
                            }}
                        </FieldLabel>
                        <textarea
                            id="workaround"
                            v-model="form.workaround"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="form.errors.workaround" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="corrective_action"
                            :help="
                                t(
                                    'products.vulnerabilities.help.corrective_action',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.corrective_action',
                                )
                            }}
                        </FieldLabel>
                        <textarea
                            id="corrective_action"
                            v-model="form.corrective_action"
                            :class="textareaClass"
                            rows="3"
                        />
                        <InputError :message="form.errors.corrective_action" />
                    </div>

                    <div class="grid gap-2 sm:col-span-2">
                        <FieldLabel
                            html-for="notes"
                            :help="t('products.vulnerabilities.help.notes')"
                        >
                            {{ t('products.vulnerabilities.fields.notes') }}
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
                    <FieldLabel
                        :help="t('products.vulnerabilities.help.components')"
                    >
                        {{ t('products.vulnerabilities.fields.components') }}
                    </FieldLabel>
                    <div
                        class="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <p
                            v-if="components.length === 0"
                            class="text-sm text-muted-foreground"
                        >
                            {{ t('products.vulnerabilities.no_components') }}
                        </p>
                        <label
                            v-for="component in components"
                            :key="component.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="
                                    form.component_ids.includes(component.id)
                                "
                                @change="
                                    toggleId(
                                        'component_ids',
                                        component.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>
                                <span class="font-medium">{{
                                    component.name
                                }}</span>
                                <span
                                    v-if="component.version"
                                    class="text-muted-foreground"
                                >
                                    @{{ component.version }}
                                </span>
                            </span>
                        </label>
                    </div>
                    <InputError :message="form.errors.component_ids" />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <FieldLabel
                            :help="
                                t(
                                    'products.vulnerabilities.help.affected_versions',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.affected_versions',
                                )
                            }}
                        </FieldLabel>
                        <div
                            class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                        >
                            <label
                                v-for="version in versions"
                                :key="`affected-${version.id}`"
                                class="flex items-start gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1"
                                    :checked="
                                        form.affected_version_ids.includes(
                                            version.id,
                                        )
                                    "
                                    @change="
                                        toggleId(
                                            'affected_version_ids',
                                            version.id,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span>{{ version.version_number }}</span>
                            </label>
                        </div>
                        <InputError
                            :message="form.errors.affected_version_ids"
                        />
                    </div>

                    <div class="grid gap-2">
                        <FieldLabel
                            :help="
                                t(
                                    'products.vulnerabilities.help.fixed_versions',
                                )
                            "
                        >
                            {{
                                t(
                                    'products.vulnerabilities.fields.fixed_versions',
                                )
                            }}
                        </FieldLabel>
                        <div
                            class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                        >
                            <label
                                v-for="version in versions"
                                :key="`fixed-${version.id}`"
                                class="flex items-start gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    class="mt-1"
                                    :checked="
                                        form.fixed_version_ids.includes(
                                            version.id,
                                        )
                                    "
                                    @change="
                                        toggleId(
                                            'fixed_version_ids',
                                            version.id,
                                            ($event.target as HTMLInputElement)
                                                .checked,
                                        )
                                    "
                                />
                                <span>{{ version.version_number }}</span>
                            </label>
                        </div>
                        <InputError :message="form.errors.fixed_version_ids" />
                    </div>
                </div>
            </fieldset>

            <div
                v-if="canManage"
                class="flex items-center justify-between gap-2"
            >
                <Button
                    type="button"
                    variant="destructive"
                    @click="showDeleteDialog = true"
                >
                    <Trash2 class="h-4 w-4" />
                    {{ t('common.delete') }}
                </Button>
                <Button type="submit" :disabled="form.processing">
                    <Save class="h-4 w-4" />
                    {{ t('common.save') }}
                </Button>
            </div>
        </form>

        <AppAlertDialog
            v-model:open="showDeleteDialog"
            :title="t('common.delete_confirm_title')"
            :description="t('products.vulnerabilities.confirm_delete')"
            @confirm="confirmDelete"
            @cancel="showDeleteDialog = false"
        />

        <Dialog
            :open="showTriageDialog"
            @update:open="(open) => (showTriageDialog = open)"
        >
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>
                        {{
                            t('products.vulnerabilities.ai_triage_dialog_title')
                        }}
                    </DialogTitle>
                    <DialogDescription>
                        {{
                            t(
                                'products.vulnerabilities.ai_triage_dialog_description',
                            )
                        }}
                    </DialogDescription>
                </DialogHeader>

                <div class="space-y-2 py-2">
                    <Label for="triage-note">
                        {{ t('products.vulnerabilities.ai_triage_note_label') }}
                    </Label>
                    <textarea
                        id="triage-note"
                        v-model="triageForm.note"
                        rows="3"
                        :class="textareaClass"
                        :placeholder="
                            t(
                                'products.vulnerabilities.ai_triage_note_placeholder',
                            )
                        "
                        :disabled="triageForm.processing"
                    />
                    <InputError :message="triageError" />
                </div>

                <DialogFooter>
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="triageForm.processing"
                        @click="showTriageDialog = false"
                    >
                        {{ t('common.cancel') }}
                    </Button>
                    <Button
                        type="button"
                        :disabled="triageForm.processing"
                        @click="submitTriage"
                    >
                        <Sparkles class="h-4 w-4" />
                        {{ t('products.vulnerabilities.ai_triage_submit') }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </div>
</template>
