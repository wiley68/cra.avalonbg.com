<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, FileDown, IdCard } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslations } from '@/composables/useTranslations';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { edit as editProduct } from '@/routes/products';
import { index as productComponentsIndex } from '@/routes/products/components';
import { index as productControlsIndex } from '@/routes/products/controls';
import { index as productEvidenceIndex } from '@/routes/products/evidence';
import { exportMethod as readinessExport } from '@/routes/products/readiness';
import { index as requirementsIndex } from '@/routes/products/requirements';
import { index as productRisksIndex } from '@/routes/products/risks';
import { index as productTasksIndex } from '@/routes/products/tasks';
import { index as versionsIndex } from '@/routes/products/versions';
import { index as productVulnerabilitiesIndex } from '@/routes/products/vulnerabilities';
import { show as passportShow } from '@/routes/products/passport';
import { index as supportPeriodsIndex } from '@/routes/products/support-periods';
import { index as productsIndex } from '@/routes/products';
import { show as readinessShow } from '@/routes/products/readiness';

type OrganizationSummary = { id: number; name: string; slug: string };
type ProductSummary = { id: number; name: string; slug: string };

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
    product: ProductSummary;
    sections: ReadinessSection[];
    gaps: ReadinessGap[];
    metrics: Record<string, number | null>;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    report: ReadinessReport;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'breadcrumbs.readiness', href: readinessShow(props.product.id) },
]);
const { backHref } = useProductModuleBack(props.product.id);

const statusVariant = (
    status: ReadinessSection['status'],
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

const resolveLink = (link: string | null): string | null => {
    if (!link) {
        return null;
    }

    const id = props.product.id;

    switch (link) {
        case 'edit':
            return editProduct(id).url;
        case 'versions':
            return versionsIndex(id).url;
        case 'support-periods':
            return supportPeriodsIndex(id).url;
        case 'requirements':
            return requirementsIndex(id).url;
        case 'controls':
            return productControlsIndex(id).url;
        case 'risks':
            return productRisksIndex(id).url;
        case 'components':
            return productComponentsIndex(id).url;
        case 'vulnerabilities':
            return productVulnerabilitiesIndex(id).url;
        case 'evidence':
            return productEvidenceIndex(id).url;
        case 'tasks':
            return productTasksIndex(id).url;
        default:
            return null;
    }
};

const failCount = computed(
    () => props.report.gaps.filter((gap) => gap.status === 'fail').length,
);
const warnCount = computed(
    () => props.report.gaps.filter((gap) => gap.status === 'warn').length,
);

const exportUrl = computed(() => readinessExport(props.product.id).url);
</script>

<template>
    <Head :title="t('products.readiness.title')" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.readiness.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.readiness.subtitle') }}
                </p>
            </div>

            <div class="flex items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="passportShow(props.product.id)">
                        <IdCard class="h-4 w-4" />
                        {{ t('products.passport_link') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <a
                        :href="exportUrl"
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <FileDown class="h-4 w-4" />
                        {{ t('products.readiness.export') }}
                    </a>
                </Button>
            </div>
        </div>

        <div
            class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/40 dark:text-amber-100"
        >
            {{ t('products.readiness.disclaimer') }}
        </div>

        <div class="grid gap-3 sm:grid-cols-3">
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">
                    {{ t('products.readiness.metrics.failures') }}
                </p>
                <p class="text-2xl font-semibold">{{ failCount }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">
                    {{ t('products.readiness.metrics.warnings') }}
                </p>
                <p class="text-2xl font-semibold">{{ warnCount }}</p>
            </div>
            <div class="rounded-lg border p-4">
                <p class="text-xs text-muted-foreground">
                    {{ t('products.readiness.metrics.generated') }}
                </p>
                <p class="text-sm font-medium">
                    {{ props.report.generated_at }}
                </p>
            </div>
        </div>

        <section v-if="props.report.gaps.length" class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ t('products.readiness.gaps_title') }}
            </h2>
            <ul class="space-y-2">
                <li
                    v-for="(gap, index) in props.report.gaps"
                    :key="`${gap.section}-${index}`"
                    class="flex flex-wrap items-center justify-between gap-2 rounded-md border px-3 py-2"
                >
                    <div class="flex items-center gap-2">
                        <Badge :variant="statusVariant(gap.status)">
                            {{ t(`products.readiness.status.${gap.status}`) }}
                        </Badge>
                        <span class="text-sm">{{ gapMessage(gap) }}</span>
                    </div>
                    <Button
                        v-if="resolveLink(gap.link)"
                        as-child
                        size="sm"
                        variant="outline"
                    >
                        <Link :href="resolveLink(gap.link)!">
                            {{ t('products.readiness.open_module') }}
                        </Link>
                    </Button>
                </li>
            </ul>
        </section>

        <section class="space-y-3">
            <h2 class="text-lg font-semibold">
                {{ t('products.readiness.sections_title') }}
            </h2>
            <div class="grid gap-3 md:grid-cols-2">
                <article
                    v-for="section in props.report.sections"
                    :key="section.key"
                    class="space-y-2 rounded-lg border p-4"
                >
                    <div class="flex items-center justify-between gap-2">
                        <h3 class="font-medium">
                            {{ sectionTitle(section.key) }}
                        </h3>
                        <Badge :variant="statusVariant(section.status)">
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
    </div>
</template>
