<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import {
    create as productIncidentsCreate,
    index as productIncidentsIndex,
    store,
} from '@/routes/products/incidents';
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

const props = defineProps<{
    product: ProductSummary;
    members: Member[];
    versions: VersionOption[];
    customers: CustomerOption[];
    deployments: DeploymentOption[];
    options: {
        statuses: string[];
        severities: string[];
        cia_impacts?: string[];
        attack_vectors?: string[];
        report_channels?: string[];
        communication_channels?: string[];
    };
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
        titleKey: 'products.incidents.create_title',
        href: productIncidentsCreate(props.product.id),
    },
]);

const textareaClass =
    'flex min-h-[80px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50';

const selectClass =
    'flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-colors focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring';

const form = useForm({
    title: '',
    summary: '',
    status: props.options.statuses[0] ?? 'open',
    severity: props.options.severities[1] ?? 'medium',
    confidentiality_impact: '' as string,
    integrity_impact: '' as string,
    availability_impact: '' as string,
    attack_vector: '' as string,
    root_cause: '',
    corrective_measures: '',
    lessons_learned: '',
    owner_user_id: '' as number | '',
    actual_started_at: '',
    detected_at: '',
    awareness_at: '',
    classified_at: '',
    notes: '',
    version_ids: [] as number[],
    customer_ids: [] as number[],
    deployment_ids: [] as number[],
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        confidentiality_impact: data.confidentiality_impact || null,
        integrity_impact: data.integrity_impact || null,
        availability_impact: data.availability_impact || null,
        attack_vector: data.attack_vector || null,
        owner_user_id: data.owner_user_id || null,
        actual_started_at: data.actual_started_at || null,
        detected_at: data.detected_at || null,
        awareness_at: data.awareness_at || null,
        classified_at: data.classified_at || null,
    })).post(store(props.product.id).url);
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.incidents.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
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
    <Head :title="t('products.incidents.create_title')" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.incidents.create_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productIncidentsIndex(props.product.id)">
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
                    <p class="text-sm font-medium">
                        {{ t('products.incidents.cia_title') }}
                    </p>
                    <p class="text-xs text-muted-foreground">
                        {{ t('products.incidents.cia_subtitle') }}
                    </p>
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="confidentiality_impact"
                        :help="
                            t('products.incidents.help.confidentiality_impact')
                        "
                    >
                        {{
                            t(
                                'products.incidents.fields.confidentiality_impact',
                            )
                        }}
                    </FieldLabel>
                    <select
                        id="confidentiality_impact"
                        v-model="form.confidentiality_impact"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('products.none') }}
                        </option>
                        <option
                            v-for="impact in options.cia_impacts ?? []"
                            :key="impact"
                            :value="impact"
                        >
                            {{ enumLabel('cia_impacts', impact) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.confidentiality_impact" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="integrity_impact"
                        :help="t('products.incidents.help.integrity_impact')"
                    >
                        {{ t('products.incidents.fields.integrity_impact') }}
                    </FieldLabel>
                    <select
                        id="integrity_impact"
                        v-model="form.integrity_impact"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('products.none') }}
                        </option>
                        <option
                            v-for="impact in options.cia_impacts ?? []"
                            :key="impact"
                            :value="impact"
                        >
                            {{ enumLabel('cia_impacts', impact) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.integrity_impact" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="availability_impact"
                        :help="t('products.incidents.help.availability_impact')"
                    >
                        {{ t('products.incidents.fields.availability_impact') }}
                    </FieldLabel>
                    <select
                        id="availability_impact"
                        v-model="form.availability_impact"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('products.none') }}
                        </option>
                        <option
                            v-for="impact in options.cia_impacts ?? []"
                            :key="impact"
                            :value="impact"
                        >
                            {{ enumLabel('cia_impacts', impact) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.availability_impact" />
                </div>

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="attack_vector"
                        :help="t('products.incidents.help.attack_vector')"
                    >
                        {{ t('products.incidents.fields.attack_vector') }}
                    </FieldLabel>
                    <select
                        id="attack_vector"
                        v-model="form.attack_vector"
                        :class="selectClass"
                    >
                        <option value="">
                            {{ t('products.none') }}
                        </option>
                        <option
                            v-for="vector in options.attack_vectors ?? []"
                            :key="vector"
                            :value="vector"
                        >
                            {{ enumLabel('attack_vectors', vector) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.attack_vector" />
                </div>

                <div class="grid gap-2">
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

                <div class="grid gap-2">
                    <FieldLabel
                        html-for="actual_started_at"
                        :help="t('products.incidents.help.actual_started_at')"
                    >
                        {{ t('products.incidents.fields.actual_started_at') }}
                    </FieldLabel>
                    <Input
                        id="actual_started_at"
                        v-model="form.actual_started_at"
                        type="datetime-local"
                    />
                    <InputError :message="form.errors.actual_started_at" />
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
                        :help="t('products.incidents.help.awareness_at')"
                    >
                        {{ t('products.incidents.fields.awareness_at') }}
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
                        :help="t('products.incidents.help.classified_at')"
                    >
                        {{ t('products.incidents.fields.classified_at') }}
                    </FieldLabel>
                    <Input
                        id="classified_at"
                        v-model="form.classified_at"
                        type="datetime-local"
                    />
                    <InputError :message="form.errors.classified_at" />
                </div>

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
                        :help="t('products.incidents.help.corrective_measures')"
                    >
                        {{ t('products.incidents.fields.corrective_measures') }}
                    </FieldLabel>
                    <textarea
                        id="corrective_measures"
                        v-model="form.corrective_measures"
                        :class="textareaClass"
                        rows="3"
                    />
                    <InputError :message="form.errors.corrective_measures" />
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
                                    ($event.target as HTMLInputElement).checked,
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
                            :checked="form.customer_ids.includes(customer.id)"
                            @change="
                                toggleId(
                                    'customer_ids',
                                    customer.id,
                                    ($event.target as HTMLInputElement).checked,
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
                <FieldLabel :help="t('products.incidents.help.deployments')">
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
                                    ($event.target as HTMLInputElement).checked,
                                )
                            "
                        />
                        <span>{{ deploymentLabel(deployment) }}</span>
                    </label>
                </div>
                <InputError :message="form.errors.deployment_ids" />
            </div>

            <div class="flex justify-end">
                <Button type="submit" :disabled="form.processing">
                    <Plus class="h-4 w-4" />
                    {{ t('products.incidents.create') }}
                </Button>
            </div>
        </form>
    </div>
</template>
