<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import { computed } from 'vue';
import FieldLabel from '@/components/FieldLabel.vue';
import InputError from '@/components/InputError.vue';
import OptionInfoTooltip from '@/components/OptionInfoTooltip.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { index as productRisksIndex, store } from '@/routes/products/risks';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { create as productRisksCreate } from '@/routes/products/risks';

type Member = { id: number; name: string; email: string };
type VersionOption = { id: number; version_number: string };
type ControlOption = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    assigned: boolean;
};
type RequirementOption = {
    id: number;
    code: string;
    article_ref: string | null;
    requirement_text: string | null;
};
type ProductSummary = { id: number; name: string; slug: string };

const props = defineProps<{
    product: ProductSummary;
    members: Member[];
    versions: VersionOption[];
    controls: ControlOption[];
    requirements: RequirementOption[];
    options: {
        categories: string[];
        likelihoods: number[];
        impacts: number[];
        treatments: string[];
        statuses: string[];
    };
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.risks.index_title',
        href: productRisksIndex(props.product.id),
    },
    {
        titleKey: 'products.risks.create_title',
        href: productRisksCreate(props.product.id),
    },
]);

const form = useForm({
    title: '',
    asset: '',
    threat: '',
    weakness: '',
    attack_scenario: '',
    category: props.options.categories[0] ?? 'unauthorised_access',
    likelihood: props.options.likelihoods[2] ?? 3,
    impact: props.options.impacts[2] ?? 3,
    residual_likelihood: '' as number | '',
    residual_impact: '' as number | '',
    treatment: props.options.treatments[0] ?? 'mitigate',
    treatment_plan: '',
    status: props.options.statuses[0] ?? 'open',
    owner_user_id: '' as number | '',
    deadline: '',
    product_version_id: '' as number | '',
    control_ids: [] as number[],
    requirement_ids: [] as number[],
});

const scoreToLevel = (likelihood: number, impact: number): string => {
    const score = likelihood * impact;

    if (score >= 17) {
        return 'critical';
    }

    if (score >= 10) {
        return 'high';
    }

    if (score >= 5) {
        return 'medium';
    }

    return 'low';
};

const initialRisk = computed(() =>
    scoreToLevel(Number(form.likelihood), Number(form.impact)),
);

const residualRisk = computed(() => {
    if (form.residual_likelihood === '' || form.residual_impact === '') {
        return null;
    }

    return scoreToLevel(
        Number(form.residual_likelihood),
        Number(form.residual_impact),
    );
});

const submit = () => {
    form.transform((data) => ({
        ...data,
        owner_user_id: data.owner_user_id || null,
        product_version_id: data.product_version_id || null,
        residual_likelihood: data.residual_likelihood || null,
        residual_impact: data.residual_impact || null,
        deadline: data.deadline || null,
    })).post(store(props.product.id).url);
};

const enumLabel = (group: string, value: string | number): string => {
    const key = `products.risks.${group}.${value}`;
    const translated = t(key);

    return translated === key ? String(value) : translated;
};

const toggleId = (
    field: 'control_ids' | 'requirement_ids',
    id: number,
    checked: boolean,
) => {
    if (checked) {
        if (!form[field].includes(id)) {
            form[field] = [...form[field], id];
        }

        return;
    }

    form[field] = form[field].filter((value) => value !== id);
};

const textareaClass =
    'border-input bg-background flex w-full rounded-md border px-3 py-2 text-sm';
</script>

<template>
    <Head :title="t('products.risks.create_title')" />

    <div class="mx-auto w-full max-w-3xl space-y-6">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.risks.create_title') }}
                </h1>
            </div>
            <Button as-child variant="outline">
                <Link :href="productRisksIndex(props.product.id)">
                    <ArrowLeft class="h-4 w-4" />
                    {{ t('common.back') }}
                </Link>
            </Button>
        </div>

        <form class="space-y-5 rounded-lg border p-6" @submit.prevent="submit">
            <div class="grid gap-2">
                <FieldLabel
                    html-for="title"
                    required
                    :help="t('products.risks.help.title')"
                >
                    {{ t('products.risks.fields.title') }}
                </FieldLabel>
                <Input id="title" v-model="form.title" required />
                <InputError :message="form.errors.title" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="category"
                        required
                        :help="t('products.risks.help.category')"
                    >
                        {{ t('products.risks.fields.category') }}
                    </FieldLabel>
                    <select
                        id="category"
                        v-model="form.category"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.categories"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('categories', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.category" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="status"
                        required
                        :help="t('products.risks.help.status')"
                    >
                        {{ t('products.risks.fields.status') }}
                    </FieldLabel>
                    <select
                        id="status"
                        v-model="form.status"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.statuses"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('statuses', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.status" />
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="asset"
                    :help="t('products.risks.help.asset')"
                >
                    {{ t('products.risks.fields.asset') }}
                </FieldLabel>
                <textarea
                    id="asset"
                    v-model="form.asset"
                    rows="2"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.asset" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="threat"
                    :help="t('products.risks.help.threat')"
                >
                    {{ t('products.risks.fields.threat') }}
                </FieldLabel>
                <textarea
                    id="threat"
                    v-model="form.threat"
                    rows="2"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.threat" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="weakness"
                    :help="t('products.risks.help.weakness')"
                >
                    {{ t('products.risks.fields.weakness') }}
                </FieldLabel>
                <textarea
                    id="weakness"
                    v-model="form.weakness"
                    rows="2"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.weakness" />
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="attack_scenario"
                    :help="t('products.risks.help.attack_scenario')"
                >
                    {{ t('products.risks.fields.attack_scenario') }}
                </FieldLabel>
                <textarea
                    id="attack_scenario"
                    v-model="form.attack_scenario"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.attack_scenario" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="likelihood"
                        required
                        :help="t('products.risks.help.likelihood')"
                    >
                        {{ t('products.risks.fields.likelihood') }}
                    </FieldLabel>
                    <select
                        id="likelihood"
                        v-model.number="form.likelihood"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.likelihoods"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('likelihoods', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.likelihood" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="impact"
                        required
                        :help="t('products.risks.help.impact')"
                    >
                        {{ t('products.risks.fields.impact') }}
                    </FieldLabel>
                    <select
                        id="impact"
                        v-model.number="form.impact"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.impacts"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('impacts', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.impact" />
                </div>
            </div>

            <p class="text-sm">
                <span class="font-medium">
                    {{ t('products.risks.fields.initial_risk') }}:
                </span>
                {{ enumLabel('levels', initialRisk) }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="residual_likelihood"
                        :help="t('products.risks.help.residual_likelihood')"
                    >
                        {{ t('products.risks.fields.residual_likelihood') }}
                    </FieldLabel>
                    <select
                        id="residual_likelihood"
                        v-model="form.residual_likelihood"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">{{ t('products.none') }}</option>
                        <option
                            v-for="value in options.likelihoods"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('likelihoods', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.residual_likelihood" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="residual_impact"
                        :help="t('products.risks.help.residual_impact')"
                    >
                        {{ t('products.risks.fields.residual_impact') }}
                    </FieldLabel>
                    <select
                        id="residual_impact"
                        v-model="form.residual_impact"
                        class="h-9 rounded-md border bg-background px-3"
                    >
                        <option value="">{{ t('products.none') }}</option>
                        <option
                            v-for="value in options.impacts"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('impacts', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.residual_impact" />
                </div>
            </div>

            <p v-if="residualRisk" class="text-sm">
                <span class="font-medium">
                    {{ t('products.risks.fields.residual_risk') }}:
                </span>
                {{ enumLabel('levels', residualRisk) }}
            </p>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="treatment"
                        required
                        :help="t('products.risks.help.treatment')"
                    >
                        {{ t('products.risks.fields.treatment') }}
                    </FieldLabel>
                    <select
                        id="treatment"
                        v-model="form.treatment"
                        class="h-9 rounded-md border bg-background px-3"
                        required
                    >
                        <option
                            v-for="value in options.treatments"
                            :key="value"
                            :value="value"
                        >
                            {{ enumLabel('treatments', value) }}
                        </option>
                    </select>
                    <InputError :message="form.errors.treatment" />
                </div>
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="deadline"
                        :help="t('products.risks.help.deadline')"
                    >
                        {{ t('products.risks.fields.deadline') }}
                    </FieldLabel>
                    <Input id="deadline" v-model="form.deadline" type="date" />
                    <InputError :message="form.errors.deadline" />
                </div>
            </div>

            <div class="grid gap-2">
                <FieldLabel
                    html-for="treatment_plan"
                    :help="t('products.risks.help.treatment_plan')"
                >
                    {{ t('products.risks.fields.treatment_plan') }}
                </FieldLabel>
                <textarea
                    id="treatment_plan"
                    v-model="form.treatment_plan"
                    rows="3"
                    :class="textareaClass"
                />
                <InputError :message="form.errors.treatment_plan" />
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <FieldLabel
                        html-for="owner_user_id"
                        :help="t('products.risks.help.owner')"
                    >
                        {{ t('products.risks.fields.owner') }}
                    </FieldLabel>
                    <select
                        id="owner_user_id"
                        v-model="form.owner_user_id"
                        class="h-9 rounded-md border bg-background px-3"
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
                        html-for="product_version_id"
                        :help="t('products.risks.help.product_version')"
                    >
                        {{ t('products.risks.fields.product_version') }}
                    </FieldLabel>
                    <select
                        id="product_version_id"
                        v-model="form.product_version_id"
                        class="h-9 rounded-md border bg-background px-3"
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
            </div>

            <div class="grid gap-2">
                <FieldLabel :help="t('products.risks.help.controls')">
                    {{ t('products.risks.fields.controls') }}
                </FieldLabel>
                <div
                    class="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3"
                >
                    <p
                        v-if="controls.length === 0"
                        class="text-sm text-muted-foreground"
                    >
                        {{ t('products.risks.no_controls') }}
                    </p>
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
                        <span>
                            <span class="font-medium"
                                >{{ control.code }} — {{ control.name
                                }}{{ ' — '
                                }}<OptionInfoTooltip
                                    :items="[
                                        {
                                            label: t(
                                                'controls.fields.description',
                                            ),
                                            value: control.description,
                                        },
                                    ]"
                            /></span>
                            <span
                                v-if="control.assigned"
                                class="text-muted-foreground"
                            >
                                ({{ t('products.risks.on_product') }})
                            </span>
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.control_ids" />
            </div>

            <div class="grid gap-2">
                <FieldLabel :help="t('products.risks.help.requirements')">
                    {{ t('products.risks.fields.requirements') }}
                </FieldLabel>
                <div
                    class="max-h-48 space-y-2 overflow-y-auto rounded-md border p-3"
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
                        <span>
                            <span class="font-medium">{{
                                requirement.code
                            }}</span>
                            <span
                                v-if="requirement.article_ref"
                                class="text-muted-foreground"
                            >
                                — {{ requirement.article_ref }} </span
                            >{{ ' — '
                            }}<OptionInfoTooltip
                                :items="[
                                    {
                                        label: t(
                                            'products.requirements.fields.requirement_text',
                                        ),
                                        value: requirement.requirement_text,
                                    },
                                ]"
                            />
                        </span>
                    </label>
                </div>
                <InputError :message="form.errors.requirement_ids" />
            </div>

            <Button type="submit" :disabled="form.processing">
                <Plus class="h-4 w-4" />
                {{ t('common.create') }}
            </Button>
        </form>
    </div>
</template>
