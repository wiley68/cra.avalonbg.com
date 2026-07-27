<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    ArrowLeft,
    ArrowRight,
    ClipboardCheck,
    GitBranch,
    IdCard,
    ListOrdered,
    ScrollText,
    Settings,
    Shield,
    Sparkles,
    Users,
} from '@lucide/vue';
import { computed, ref, type Component } from 'vue';
import { Badge } from '@/components/ui/badge';
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
import { index as controlsIndex } from '@/routes/controls';
import { index as customersIndex } from '@/routes/customers';
import { index as policiesIndex } from '@/routes/policies';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import { show as passportShow } from '@/routes/products/passport';
import { show as readinessShow } from '@/routes/products/readiness';
import { show as wizardShow } from '@/routes/products/wizard';
import { edit as editProfile } from '@/routes/profile';
import { index as usersIndex } from '@/routes/users';

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

type WizardStatusReason = {
    section: string;
    summary: string;
};

type WizardStep = {
    number: number;
    key: string;
    required: boolean;
    label_key: string;
    content_key: string;
    href: string;
    status: WizardStepStatus;
    status_reason: WizardStatusReason | null;
    is_complete: boolean;
    is_current: boolean;
    is_dismissed: boolean;
};

type WizardSidePath = {
    from_key: string;
    to_key: string;
    from_number: number;
    to_number: number;
    from_label_key: string;
    to_label_key: string;
    when_key: string;
    href: string;
    relevant: boolean;
    to_dismissed: boolean;
};

type WizardProgress = {
    required_total: number;
    required_complete: number;
    percent: number;
    optional_total: number;
    optional_complete: number;
    optional_dismissed: number;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: WizardProduct;
    steps: WizardStep[];
    side_paths: WizardSidePath[];
    progress: WizardProgress;
    dismissed_optional: string[];
    current_step_key: string | null;
    required_complete: boolean;
    success: boolean;
    can_manage: boolean;
}>();

const { t } = useTranslations();
const page = usePage();
const { backHref } = useProductModuleBack(props.product.id);

type OrgPrepLink = {
    key: string;
    label: string;
    href: string;
    icon: Component;
    optional?: boolean;
};

const orgPrepLinks = computed((): OrgPrepLink[] => {
    const user = page.props.auth.user;
    const links: OrgPrepLink[] = [
        {
            key: 'settings',
            label: t('products.wizard.org_prep.settings'),
            href: editProfile().url,
            icon: Settings,
        },
    ];

    if (user?.can_manage_users) {
        links.push({
            key: 'users',
            label: t('products.wizard.org_prep.users'),
            href: usersIndex().url,
            icon: Users,
        });
    }

    if (user?.can_view_controls) {
        links.push({
            key: 'controls',
            label: t('products.wizard.org_prep.controls'),
            href: controlsIndex().url,
            icon: Shield,
        });
    }

    if (user?.can_view_products) {
        links.push({
            key: 'policies',
            label: t('products.wizard.org_prep.policies'),
            href: policiesIndex().url,
            icon: ScrollText,
        });
        links.push({
            key: 'customers',
            label: t('products.wizard.org_prep.customers'),
            href: customersIndex().url,
            icon: Users,
            optional: true,
        });
    }

    return links;
});

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

const currentStep = computed(
    () => props.steps.find((step) => step.is_current) ?? null,
);

/**
 * Spine rows already passed by number: finished steps, plus optional steps that
 * sit behind the current required stick-point (or all open optionals after success).
 */
const completedSteps = computed(() => {
    const currentNumber = currentStep.value?.number ?? null;

    return props.steps
        .filter((step) => {
            if (step.is_current || step.is_dismissed) {
                return false;
            }

            if (step.is_complete) {
                return true;
            }

            // Optional leftovers stay in number order — not in Upcoming.
            if (!step.required) {
                return currentNumber === null || step.number < currentNumber;
            }

            return false;
        })
        .sort((a, b) => a.number - b.number);
});

/** Only unfinished required steps ahead of the current stick-point. */
const upcomingSteps = computed(() => {
    if (!currentStep.value) {
        return [];
    }

    return props.steps.filter(
        (step) =>
            step.required &&
            !step.is_complete &&
            !step.is_current &&
            step.number > currentStep.value!.number,
    );
});

const showAllSidePaths = ref(false);

const visibleSidePaths = computed(() => {
    const paths = props.side_paths.filter((path) => !path.to_dismissed);

    if (showAllSidePaths.value) {
        return paths;
    }

    return paths.filter((path) => path.relevant);
});

const hasRelevantSidePaths = computed(() =>
    props.side_paths.some((path) => path.relevant && !path.to_dismissed),
);

const sidePathLabel = (path: WizardSidePath): string =>
    t('products.wizard.side_paths.from_to', {
        from: `${path.from_number}. ${t(path.from_label_key)}`,
        to: `${path.to_number}. ${t(path.to_label_key)}`,
    });

const progressSummary = computed(() =>
    t('products.wizard.progress.summary', {
        done: String(props.progress.required_complete),
        total: String(props.progress.required_total),
        percent: String(props.progress.percent),
    }),
);

const optionalProgressSummary = computed(() =>
    t('products.wizard.progress.optional_summary', {
        done: String(props.progress.optional_complete),
        dismissed: String(props.progress.optional_dismissed),
    }),
);

const stepStatusClass = (status: WizardStepStatus): string => {
    if (status === 'na') {
        return 'text-foreground';
    }

    return productModuleStatusClass(status as ProductModuleStatus);
};

const statusBadgeClass = (status: WizardStepStatus): string => {
    switch (status) {
        case 'critical':
            return 'border-red-600/40 bg-red-600/10 text-red-700 dark:text-red-400';
        case 'attention':
            return 'border-orange-600/40 bg-orange-600/10 text-orange-700 dark:text-orange-400';
        case 'complete':
            return 'border-emerald-600/40 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400';
        case 'na':
            return 'border-border bg-muted text-muted-foreground';
        default:
            return 'border-border bg-background text-foreground';
    }
};

const stepReasonLabel = (step: WizardStep): string => {
    const reason = step.status_reason;

    if (reason?.section && reason?.summary) {
        const wizardKey = `products.wizard.summaries.${reason.section}.${reason.summary}`;
        const wizardTranslated = t(wizardKey);

        if (wizardTranslated !== wizardKey) {
            return wizardTranslated;
        }

        const readinessKey = `products.readiness.summaries.${reason.section}.${reason.summary}`;
        const readinessTranslated = t(readinessKey);

        if (readinessTranslated !== readinessKey) {
            return readinessTranslated;
        }
    }

    if (step.status === 'na') {
        return t('products.wizard.status.na');
    }

    return t(`products.module_colors.${step.status}`);
};

const showsAttentionSignal = (status: WizardStepStatus): boolean =>
    status === 'attention' || status === 'critical' || status === 'empty';

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

        <section class="space-y-2" aria-labelledby="wizard-progress-label">
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p id="wizard-progress-label" class="text-sm font-medium">
                    {{ t('products.wizard.progress.label') }}
                </p>
                <p class="text-sm text-muted-foreground tabular-nums">
                    {{ progressSummary }}
                </p>
            </div>
            <div
                class="h-2 w-full overflow-hidden rounded-full bg-muted"
                role="progressbar"
                :aria-valuenow="progress.percent"
                aria-valuemin="0"
                aria-valuemax="100"
                :aria-label="progressSummary"
            >
                <div
                    class="h-full rounded-full bg-emerald-600 transition-[width] duration-300 dark:bg-emerald-500"
                    :style="{ width: `${progress.percent}%` }"
                />
            </div>
            <p
                v-if="progress.optional_total > 0"
                class="text-xs text-muted-foreground"
            >
                {{ optionalProgressSummary }}
            </p>
        </section>

        <section
            v-if="orgPrepLinks.length > 0"
            class="space-y-2 rounded-lg border border-dashed p-3"
        >
            <div class="space-y-1">
                <h2 class="text-sm font-medium">
                    {{ t('products.wizard.org_prep.heading') }}
                </h2>
                <p class="text-xs text-muted-foreground">
                    {{ t('products.wizard.org_prep.intro') }}
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    v-for="link in orgPrepLinks"
                    :key="link.key"
                    variant="outline"
                    size="sm"
                    as-child
                >
                    <Link :href="link.href">
                        <component :is="link.icon" class="h-4 w-4" />
                        {{ link.label }}
                        <span
                            v-if="link.optional"
                            class="text-xs font-normal text-muted-foreground"
                        >
                            ({{
                                t('products.wizard.org_prep.optional_suffix')
                            }})
                        </span>
                    </Link>
                </Button>
            </div>
        </section>

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
                    <button
                        type="button"
                        class="cursor-pointer text-left hover:underline"
                        :class="stepStatusClass(step.status)"
                        @click="openStep(step.href)"
                    >
                        {{ t(step.label_key) }}
                    </button>
                    <span class="text-xs font-normal text-muted-foreground">
                        ({{ t(`${step.content_key}.summary`) }})
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
            <CardHeader class="space-y-3">
                <div class="flex flex-wrap items-center gap-2">
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
                    <Badge
                        variant="outline"
                        :class="statusBadgeClass(currentStep.status)"
                    >
                        {{ t(`products.wizard.status.${currentStep.status}`) }}
                    </Badge>
                </div>
                <p class="text-sm font-normal text-muted-foreground">
                    {{ t(`${currentStep.content_key}.summary`) }}
                </p>
                <p
                    v-if="showsAttentionSignal(currentStep.status)"
                    class="text-sm"
                    :class="stepStatusClass(currentStep.status)"
                >
                    <span class="font-medium"
                        >{{ t('products.wizard.attention_heading') }}:</span
                    >
                    {{ stepReasonLabel(currentStep) }}
                </p>
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

        <Card>
            <CardHeader class="space-y-2">
                <CardTitle class="flex items-center gap-2 text-base">
                    <GitBranch class="h-4 w-4" />
                    {{ t('products.wizard.side_paths.heading') }}
                </CardTitle>
                <CardDescription>
                    {{ t('products.wizard.side_paths.intro') }}
                </CardDescription>
            </CardHeader>
            <CardContent class="space-y-4">
                <div
                    v-if="visibleSidePaths.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('products.wizard.side_paths.empty_relevant') }}
                </div>
                <ul v-else class="space-y-3">
                    <li
                        v-for="path in visibleSidePaths"
                        :key="`${path.from_key}-${path.to_key}-${path.when_key}`"
                        class="flex flex-col gap-2 rounded-md border p-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0 space-y-1 text-sm">
                            <p class="font-medium">
                                {{ sidePathLabel(path) }}
                            </p>
                            <p class="text-xs text-muted-foreground">
                                {{ t(path.when_key) }}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            class="shrink-0"
                            @click="openStep(path.href)"
                        >
                            <ArrowRight class="h-4 w-4" />
                            {{ t('products.wizard.side_paths.jump') }}
                        </Button>
                    </li>
                </ul>
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <Button
                        variant="ghost"
                        size="sm"
                        @click="showAllSidePaths = !showAllSidePaths"
                    >
                        {{
                            showAllSidePaths
                                ? t('products.wizard.side_paths.show_relevant')
                                : t('products.wizard.side_paths.show_all')
                        }}
                    </Button>
                    <Badge
                        v-if="hasRelevantSidePaths && !showAllSidePaths"
                        variant="outline"
                    >
                        {{ visibleSidePaths.length }}
                    </Badge>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{ t('products.wizard.side_paths.avoid') }}
                </p>
            </CardContent>
        </Card>

        <section v-if="upcomingSteps.length > 0" class="space-y-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                {{ t('products.wizard.upcoming_heading') }}
            </h2>
            <ol class="space-y-1.5">
                <li
                    v-for="step in upcomingSteps"
                    :key="step.key"
                    class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5 text-sm"
                >
                    <span
                        class="w-6 shrink-0 text-muted-foreground tabular-nums"
                        >{{ step.number }}.</span
                    >
                    <span :class="stepStatusClass(step.status)">
                        {{ t(step.label_key) }}
                    </span>
                    <span class="text-xs font-normal text-muted-foreground">
                        ({{ t(`${step.content_key}.summary`) }})
                    </span>
                    <span
                        v-if="!step.required"
                        class="text-xs text-muted-foreground"
                    >
                        ({{ t('products.wizard.optional') }})
                    </span>
                    <span
                        v-if="
                            step.status === 'attention' ||
                            step.status === 'critical'
                        "
                        class="w-full pl-8 text-xs"
                        :class="stepStatusClass(step.status)"
                    >
                        {{ stepReasonLabel(step) }}
                    </span>
                </li>
            </ol>
        </section>

        <Card
            v-if="success"
            class="border-emerald-600/25 bg-emerald-50 dark:bg-emerald-950/30"
        >
            <CardHeader>
                <CardTitle class="flex items-center gap-2 text-base">
                    <Sparkles class="h-4 w-4 text-emerald-600" />
                    {{ t('products.wizard.success.title') }}
                </CardTitle>
                <CardDescription>
                    {{ t('products.wizard.success.description') }}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div class="flex flex-wrap gap-2">
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
                </div>
            </CardContent>
        </Card>
    </div>
</template>
