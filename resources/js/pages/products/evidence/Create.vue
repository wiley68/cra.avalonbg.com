<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { index as evidenceIndex, store } from '@/routes/products/evidence';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { create as evidenceCreate } from '@/routes/products/evidence';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type RequirementOption = {
    id: number;
    code: string;
    article_ref: string | null;
};
type ControlOption = { id: number; code: string; name: string };
type RiskOption = { id: number; title: string };
type VulnerabilityOption = {
    id: number;
    title: string;
    cve_id: string | null;
};
type EvidenceOption = { id: number; title: string };
type ProductSummary = { id: number; name: string; slug: string };

const props = defineProps<{
    product: ProductSummary;
    members: Member[];
    versions: VersionOption[];
    requirements: RequirementOption[];
    controls: ControlOption[];
    risks: RiskOption[];
    vulnerabilities: VulnerabilityOption[];
    evidenceOptions: EvidenceOption[];
    options: {
        types: string[];
        confidentialities: string[];
        freshness_statuses: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'products.evidence.index_title', href: evidenceIndex(props.product.id) },
    { titleKey: 'products.evidence.create_title', href: evidenceCreate(props.product.id) },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    title: '',
    type: props.options.types[0] ?? 'document',
    source: '',
    owner_user_id: '' as number | '',
    product_version_id: '' as number | '',
    confidentiality: props.options.confidentialities[0] ?? 'internal',
    collected_at: '',
    valid_until: '',
    review_due_at: '',
    freshness_status: props.options.freshness_statuses[0] ?? 'current',
    supersedes_evidence_id: '' as number | '',
    notes: '',
    review_notes: '',
    reviewer_user_id: '' as number | '',
    reviewed_at: '',
    file: null as File | null,
    requirement_ids: [] as number[],
    control_ids: [] as number[],
    risk_ids: [] as number[],
    vulnerability_ids: [] as number[],
});

const onFileChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    form.file = target.files?.[0] ?? null;
};

const submit = () => {
    form.transform((data) => ({
        ...data,
        owner_user_id: data.owner_user_id || null,
        product_version_id: data.product_version_id || null,
        supersedes_evidence_id: data.supersedes_evidence_id || null,
        reviewer_user_id: data.reviewer_user_id || null,
        collected_at: data.collected_at || null,
        valid_until: data.valid_until || null,
        review_due_at: data.review_due_at || null,
        reviewed_at: data.reviewed_at || null,
    })).post(store(props.product.id).url, { forceFormData: true });
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.evidence.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const toggleId = (
    field: 'requirement_ids' | 'control_ids' | 'risk_ids' | 'vulnerability_ids',
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
    <Head :title="t('products.evidence.create_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.evidence.create_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="evidenceIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-6" @submit.prevent="submit">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="title"
                        required
                        :help="t('products.evidence.help.title')"
                    >
                        {{ t('products.evidence.fields.title') }}
                    </FieldLabel>
                    <Input id="title" v-model="form.title" required />
                    <InputError :message="form.errors.title" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="type"
                        required
                        :help="t('products.evidence.help.type')"
                    >
                        {{ t('products.evidence.fields.type') }}
                    </FieldLabel>
                    <select
                        id="type"
                        v-model="form.type"
                        :class="selectClass"
                        required
                    >
                        <option
                            v-for="type in options.types"
                            :key="type"
                            :value="type"
                        >
                            {{ enumLabel('types', type) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.type" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="confidentiality"
                        required
                        :help="t('products.evidence.help.confidentiality')"
                    >
                        {{ t('products.evidence.fields.confidentiality') }}
                    </FieldLabel>
                    <select
                        id="confidentiality"
                        v-model="form.confidentiality"
                        :class="selectClass"
                        required
                    >
                        <option
                            v-for="item in options.confidentialities"
                            :key="item"
                            :value="item"
                        >
                            {{ enumLabel('confidentialities', item) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.confidentiality" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="source"
                        :help="t('products.evidence.help.source')"
                    >
                        {{ t('products.evidence.fields.source') }}
                    </FieldLabel>
                    <Input id="source" v-model="form.source" />
                    <InputError :message="form.errors.source" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="product_version_id"
                        :help="t('products.evidence.help.product_version')"
                    >
                        {{ t('products.evidence.fields.product_version') }}
                    </FieldLabel>
                    <select
                        id="product_version_id"
                        v-model="form.product_version_id"
                        :class="selectClass"
                    >
                        <option value="">{{ t('products.none') }}</option>
                        <option
                            v-for="version in versions"
                            :key="version.id"
                            :value="version.id"
                        >
                            {{ version.version_number }}
                        </option>
                    </select>
                    <InputError :message="form.errors.product_version_id" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="owner_user_id"
                        :help="t('products.evidence.help.owner')"
                    >
                        {{ t('products.evidence.fields.owner') }}
                    </FieldLabel>
                    <select
                        id="owner_user_id"
                        v-model="form.owner_user_id"
                        :class="selectClass"
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
                    <InputError :message="form.errors.owner_user_id" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="freshness_status"
                        required
                        :help="t('products.evidence.help.freshness_status')"
                    >
                        {{ t('products.evidence.fields.freshness_status') }}
                    </FieldLabel>
                    <select
                        id="freshness_status"
                        v-model="form.freshness_status"
                        :class="selectClass"
                        required
                    >
                        <option
                            v-for="status in options.freshness_statuses"
                            :key="status"
                            :value="status"
                        >
                            {{ enumLabel('freshness_statuses', status) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.freshness_status" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="collected_at"
                        :help="t('products.evidence.help.collected_at')"
                    >
                        {{ t('products.evidence.fields.collected_at') }}
                    </FieldLabel>
                    <Input
                        id="collected_at"
                        v-model="form.collected_at"
                        type="datetime-local"
                    />
                    <InputError :message="form.errors.collected_at" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="valid_until"
                        :help="t('products.evidence.help.valid_until')"
                    >
                        {{ t('products.evidence.fields.valid_until') }}
                    </FieldLabel>
                    <Input
                        id="valid_until"
                        v-model="form.valid_until"
                        type="date"
                    />
                    <InputError :message="form.errors.valid_until" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="review_due_at"
                        :help="t('products.evidence.help.review_due_at')"
                    >
                        {{ t('products.evidence.fields.review_due_at') }}
                    </FieldLabel>
                    <Input
                        id="review_due_at"
                        v-model="form.review_due_at"
                        type="date"
                    />
                    <InputError :message="form.errors.review_due_at" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="supersedes_evidence_id"
                        :help="t('products.evidence.help.supersedes')"
                    >
                        {{ t('products.evidence.fields.supersedes') }}
                    </FieldLabel>
                    <select
                        id="supersedes_evidence_id"
                        v-model="form.supersedes_evidence_id"
                        :class="selectClass"
                    >
                        <option value="">{{ t('products.none') }}</option>
                        <option
                            v-for="item in evidenceOptions"
                            :key="item.id"
                            :value="item.id"
                        >
                            {{ item.title }}
                        </option>
                    </select>
                    <InputError :message="form.errors.supersedes_evidence_id" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="file"
                        required
                        :help="t('products.evidence.help.file')"
                    >
                        {{ t('products.evidence.fields.file') }}
                    </FieldLabel>
                    <Input
                        id="file"
                        type="file"
                        required
                        @change="onFileChange"
                    />
                    <InputError :message="form.errors.file" />
                </div>

                <div class="grid gap-2 sm:col-span-2">
                    <FieldLabel
                        html-for="notes"
                        :help="t('products.evidence.help.notes')"
                    >
                        {{ t('products.evidence.fields.notes') }}
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
                <FieldLabel :help="t('products.evidence.help.requirements')">
                    {{ t('products.evidence.fields.requirements') }}
                </FieldLabel>
                <div
                    class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                >
                    <label
                        v-for="requirement in requirements"
                        :key="requirement.id"
                        class="flex items-start gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            class="mt-1"
                            :checked="
                                form.requirement_ids.includes(requirement.id)
                            "
                            @change="
                                toggleId(
                                    'requirement_ids',
                                    requirement.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span class="font-medium">{{ requirement.code }}</span>
                    </label>
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel :help="t('products.evidence.help.controls')">
                    {{ t('products.evidence.fields.controls') }}
                </FieldLabel>
                <div
                    class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                >
                    <label
                        v-for="control in controls"
                        :key="control.id"
                        class="flex items-start gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            class="mt-1"
                            :checked="form.control_ids.includes(control.id)"
                            @change="
                                toggleId(
                                    'control_ids',
                                    control.id,
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span>{{ control.code }} — {{ control.name }}</span>
                    </label>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel :help="t('products.evidence.help.risks')">
                        {{ t('products.evidence.fields.risks') }}
                    </FieldLabel>
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <label
                            v-for="risk in risks"
                            :key="risk.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="form.risk_ids.includes(risk.id)"
                                @change="
                                    toggleId(
                                        'risk_ids',
                                        risk.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>{{ risk.title }}</span>
                        </label>
                    </div>
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        :help="t('products.evidence.help.vulnerabilities')"
                    >
                        {{ t('products.evidence.fields.vulnerabilities') }}
                    </FieldLabel>
                    <div
                        class="max-h-40 space-y-2 overflow-y-auto rounded-md border p-3"
                    >
                        <label
                            v-for="vulnerability in vulnerabilities"
                            :key="vulnerability.id"
                            class="flex items-start gap-2 text-sm"
                        >
                            <input
                                type="checkbox"
                                class="mt-1"
                                :checked="
                                    form.vulnerability_ids.includes(
                                        vulnerability.id,
                                    )
                                "
                                @change="
                                    toggleId(
                                        'vulnerability_ids',
                                        vulnerability.id,
                                        ($event.target as HTMLInputElement)
                                            .checked,
                                    )
                                "
                            />
                            <span>{{ vulnerability.title }}</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing || !form.file">
                    <Plus class="h-4 w-4" />
                    {{ t('products.evidence.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
