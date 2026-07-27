<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ClipboardCheck,
    IdCard,
    ListOrdered,
    Sparkles,
} from '@lucide/vue';
import { computed } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import {
    setProductModuleOrigin,
    useProductModuleBack,
} from '@/composables/useProductModuleBack';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import {
    productEnumLabel,
    productModuleStatusClass,
} from '@/pages/products/columns';
import type { ProductModuleStatus } from '@/pages/products/columns';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { show as passportShow } from '@/routes/products/passport';
import { show as readinessShow } from '@/routes/products/readiness';
import { show as wizardShow } from '@/routes/products/wizard';

type OrganizationSummary = { id: number; name: string; slug: string };

type WizardProduct = {
    id: number;
    name: string;
    slug: string;
    product_type: string | null;
    scope_status: string | null;
    classification_status: string | null;
};

type WizardStepStatus = 'empty' | 'complete' | 'attention' | 'critical' | 'na';

type WizardStep = {
    number: number;
    key: string;
    required: boolean;
    label_key: string;
    content_key: string;
    href: string;
    status: WizardStepStatus;
    is_complete: boolean;
    is_current: boolean;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: WizardProduct;
    steps: WizardStep[];
    current_step_key: string | null;
    required_complete: boolean;
    success: boolean;
}>();

const { t } = useTranslations();
const { backHref } = useProductModuleBack(props.product.id);

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'breadcrumbs.wizard', href: wizardShow(props.product.id) },
]);

const typeLabel = computed(() =>
    props.product.product_type
        ? productEnumLabel(t, 'types', props.product.product_type)
        : t('products.passport.empty'),
);
const scopeLabel = computed(() =>
    props.product.scope_status
        ? productEnumLabel(t, 'scope', props.product.scope_status)
        : t('products.passport.empty'),
);
const classificationLabel = computed(() =>
    props.product.classification_status
        ? productEnumLabel(
              t,
              'classification',
              props.product.classification_status,
          )
        : t('products.passport.empty'),
);

const completedSteps = computed(() => {
    const currentNumber = currentStep.value?.number ?? Number.POSITIVE_INFINITY;

    return props.steps.filter(
        (step) => step.is_complete && step.number < currentNumber,
    );
});

const currentStep = computed(
    () => props.steps.find((step) => step.is_current) ?? null,
);

const upcomingSteps = computed(() => {
    if (!currentStep.value) {
        return props.steps.filter((step) => !step.is_complete);
    }

    return props.steps.filter(
        (step) => step.number > currentStep.value!.number && !step.is_complete,
    );
});

const optionalOpenSteps = computed(() =>
    props.steps.filter((step) => !step.required && !step.is_complete),
);

const stepStatusClass = (status: WizardStepStatus): string => {
    if (status === 'na') {
        return 'text-muted-foreground';
    }

    return productModuleStatusClass(status as ProductModuleStatus);
};

const openStep = (href: string): void => {
    setProductModuleOrigin(props.product.id, 'wizard');
    router.visit(href);
};
</script>

<template>
    <Head :title="t('products.wizard.title')" />

    <div class="flex flex-col gap-6 p-4 md:p-6">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0 space-y-1">
                <h1 class="text-xl font-semibold tracking-tight md:text-2xl">
                    {{ product.name }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    <span
                        >{{ t('products.columns.product_type') }}:
                        {{ typeLabel }}</span
                    >
                    <span class="mx-1.5 text-border">·</span>
                    <span
                        >{{ t('products.columns.scope_status') }}:
                        {{ scopeLabel }}</span
                    >
                    <span class="mx-1.5 text-border">·</span>
                    <span
                        >{{ t('products.columns.classification_status') }}:
                        {{ classificationLabel }}</span
                    >
                </p>
                <p class="max-w-3xl text-xs text-muted-foreground">
                    {{ t('products.wizard.disclaimer') }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button variant="outline" as-child>
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
            </div>
        </div>

        <Card v-if="success">
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Sparkles class="h-4 w-4 text-emerald-600" />
                    {{ t('products.wizard.success.title') }}
                </CardTitle>
                <CardDescription>
                    {{ t('products.wizard.success.description') }}
                </CardDescription>
            </CardHeader>
            <CardContent class="flex flex-wrap gap-2">
                <Button as-child>
                    <Link :href="passportShow(product.id).url">
                        <IdCard class="h-4 w-4" />
                        {{ t('products.passport_link') }}
                    </Link>
                </Button>
                <Button variant="outline" as-child>
                    <Link :href="readinessShow(product.id).url">
                        <ClipboardCheck class="h-4 w-4" />
                        {{ t('products.readiness_link') }}
                    </Link>
                </Button>
                <Button
                    v-for="step in optionalOpenSteps"
                    :key="step.key"
                    variant="outline"
                    @click="openStep(step.href)"
                >
                    <ListOrdered class="h-4 w-4" />
                    {{ t(step.label_key) }}
                </Button>
            </CardContent>
        </Card>

        <section v-if="completedSteps.length > 0" class="space-y-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ t('products.wizard.completed_heading') }}
            </h2>
            <ol class="space-y-1.5">
                <li
                    v-for="step in completedSteps"
                    :key="step.key"
                    class="flex items-baseline gap-2 text-sm"
                >
                    <span
                        class="w-6 shrink-0 text-muted-foreground tabular-nums"
                        >{{ step.number }}.</span
                    >
                    <span :class="stepStatusClass(step.status)">
                        {{ t(step.label_key) }}
                    </span>
                    <span
                        v-if="!step.required"
                        class="text-xs text-muted-foreground"
                    >
                        ({{ t('products.wizard.optional') }})
                    </span>
                </li>
            </ol>
        </section>

        <Card v-if="currentStep && !success">
            <CardHeader>
                <CardTitle class="text-base">
                    <span class="text-muted-foreground tabular-nums"
                        >{{ currentStep.number }}.</span
                    >
                    {{ t(currentStep.label_key) }}
                    <span
                        v-if="!currentStep.required"
                        class="ml-2 text-xs font-normal text-muted-foreground"
                    >
                        ({{ t('products.wizard.optional') }})
                    </span>
                </CardTitle>
            </CardHeader>
            <CardContent class="space-y-4">
                <div class="space-y-3 text-sm">
                    <div>
                        <p class="font-medium">
                            {{ t('products.wizard.sections.cra_context') }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ t(`${currentStep.content_key}.cra_context`) }}
                        </p>
                    </div>
                    <div>
                        <p class="font-medium">
                            {{ t('products.wizard.sections.why_next') }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ t(`${currentStep.content_key}.why_next`) }}
                        </p>
                    </div>
                    <div>
                        <p class="font-medium">
                            {{ t('products.wizard.sections.mandatory') }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ t(`${currentStep.content_key}.mandatory`) }}
                        </p>
                    </div>
                    <div>
                        <p class="font-medium">
                            {{ t('products.wizard.sections.interlinks') }}
                        </p>
                        <p class="text-muted-foreground">
                            {{ t(`${currentStep.content_key}.interlinks`) }}
                        </p>
                    </div>
                </div>
                <div class="flex justify-between gap-2">
                    <Button @click="openStep(currentStep.href)">
                        <ListOrdered class="h-4 w-4" />
                        {{ t('products.wizard.open_step') }}
                    </Button>
                </div>
            </CardContent>
        </Card>

        <section v-if="upcomingSteps.length > 0" class="space-y-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ t('products.wizard.upcoming_heading') }}
            </h2>
            <ol class="space-y-1">
                <li
                    v-for="step in upcomingSteps"
                    :key="step.key"
                    class="flex items-baseline gap-2 text-sm text-muted-foreground"
                >
                    <span class="w-6 shrink-0 tabular-nums"
                        >{{ step.number }}.</span
                    >
                    <span>{{ t(step.label_key) }}</span>
                    <span v-if="!step.required" class="text-xs">
                        ({{ t('products.wizard.optional') }})
                    </span>
                </li>
            </ol>
        </section>
    </div>
</template>
