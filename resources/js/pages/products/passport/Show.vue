<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft, ClipboardCheck, FileDown } from '@lucide/vue';
import { computed } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { edit as editProduct } from '@/routes/products';
import {
    exportMethod as readinessExport,
    show as readinessShow,
} from '@/routes/products/readiness';
import { index as productsIndex } from '@/routes/products';
import { show as passportShow } from '@/routes/products/passport';
import { index as technicalDocumentationIndex } from '@/routes/products/technical-documentation';

type OrganizationSummary = { id: number; name: string; slug: string };

type Person = { id: number; name: string; email: string } | null;

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
    metrics?: Record<string, number | string | boolean | null>;
};

type ReadinessReport = {
    generated_at: string;
    product: { id: number; name: string; slug: string };
    sections: ReadinessSection[];
    gaps: Array<{ section: string; status: string; message_key: string }>;
    metrics: Record<string, number | null>;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductPassport;
    report: ReadinessReport;
}>();

const { t } = useTranslations();

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    { titleKey: 'breadcrumbs.passport', href: passportShow(props.product.id) },
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

const enumLabel = (group: string, value: string | null): string => {
    if (!value) {
        return t('products.passport.empty');
    }

    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const failCount = computed(
    () => props.report.gaps.filter((gap) => gap.status === 'fail').length,
);
const warnCount = computed(
    () => props.report.gaps.filter((gap) => gap.status === 'warn').length,
);

const techDocsSection = computed(
    () =>
        props.report.sections.find(
            (section) => section.key === 'technical_documentation',
        ) ?? null,
);

const techDocsOutlineKeys = [
    'published_package',
    'sections_complete_flag',
    'linked_usi',
    'linked_sdl',
] as const;

const techDocsOutline = computed(() => {
    const metrics = techDocsSection.value?.metrics ?? {};

    return techDocsOutlineKeys.map((key) => ({
        key,
        label: t(`products.passport.tech_docs.outline.${key}`),
        done: Boolean(metrics[key]),
    }));
});

const techDocsHref = computed(
    () => technicalDocumentationIndex(props.product.id).url,
);

const exportUrl = computed(() => readinessExport(props.product.id).url);
const readinessUrl = computed(() => readinessShow(props.product.id).url);
</script>

<template>
    <Head :title="t('products.passport.title')" />

    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ props.organization.name }}
                </p>
                <h1 class="text-2xl font-semibold tracking-tight">
                    {{ props.product.name }}
                </h1>
                <p class="mt-1 text-sm text-muted-foreground">
                    {{ t('products.passport.subtitle') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <Link :href="readinessUrl">
                        <ClipboardCheck class="h-4 w-4" />
                        {{ t('products.readiness_link') }}
                    </Link>
                </Button>
                <Button as-child variant="outline">
                    <a :href="exportUrl" target="_blank" rel="noopener">
                        <FileDown class="h-4 w-4" />
                        {{ t('products.readiness.export') }}
                    </a>
                </Button>
            </div>
        </div>

        <p
            class="rounded-md border bg-muted/40 px-4 py-3 text-sm text-muted-foreground"
        >
            {{ t('products.passport.disclaimer') }}
        </p>

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
                    {{ new Date(props.report.generated_at).toLocaleString() }}
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
                            props.product.manufacturer ||
                            t('products.passport.empty')
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.product_type') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ enumLabel('types', props.product.product_type) }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.licensing_model') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{
                            enumLabel(
                                'licensing',
                                props.product.licensing_model,
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.passport.scope') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{ enumLabel('scope', props.product.scope_status) }}
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
                                props.product.classification_status,
                            )
                        }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.trademark') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        {{
                            props.product.trademark ||
                            t('products.passport.empty')
                        }}
                    </dd>
                </div>
            </dl>
            <div v-if="props.product.intended_purpose">
                <p class="text-xs text-muted-foreground">
                    {{ t('products.fields.intended_purpose') }}
                </p>
                <p class="mt-1 text-sm">{{ props.product.intended_purpose }}</p>
            </div>
        </section>

        <section class="grid gap-4 lg:grid-cols-2">
            <div class="space-y-3 rounded-lg border p-5">
                <h2 class="text-lg font-semibold">
                    {{ t('products.passport.versions_title') }}
                </h2>
                <ul
                    v-if="props.product.versions.length"
                    class="space-y-2 text-sm"
                >
                    <li
                        v-for="version in props.product.versions"
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
                    v-if="props.product.support_periods.length"
                    class="space-y-2 text-sm"
                >
                    <li
                        v-for="period in props.product.support_periods"
                        :key="period.id"
                        class="border-b pb-2 last:border-0 last:pb-0"
                    >
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-medium">
                                {{
                                    t(
                                        `products.support_periods.types.${period.type}`,
                                    )
                                }}
                            </span>
                            <Badge
                                v-if="period.is_extended"
                                variant="secondary"
                            >
                                {{ t('products.support_periods.extended') }}
                            </Badge>
                        </div>
                        <p class="text-muted-foreground">
                            {{
                                t(
                                    `products.support_periods.start_bases.${period.start_basis}`,
                                )
                            }}
                            ·
                            {{
                                t(
                                    'products.support_periods.duration_months_label',
                                    {
                                        count: String(period.duration_months),
                                    },
                                )
                            }}
                        </p>
                        <p
                            v-if="
                                period.schedule_resolved &&
                                period.effective_starts_at &&
                                period.effective_ends_at
                            "
                            class="text-xs text-muted-foreground"
                        >
                            {{ period.effective_starts_at }} →
                            {{ period.effective_ends_at }}
                        </p>
                    </li>
                </ul>
                <p v-else class="text-sm text-muted-foreground">
                    {{ t('products.passport.empty') }}
                </p>
            </div>
        </section>

        <section class="space-y-3 rounded-lg border p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">
                    {{ t('products.passport.tech_docs_title') }}
                </h2>
                <Badge
                    v-if="techDocsSection"
                    :variant="statusVariant(techDocsSection.status)"
                >
                    {{
                        t(`products.readiness.status.${techDocsSection.status}`)
                    }}
                </Badge>
            </div>
            <p class="text-sm text-muted-foreground">
                {{ t('products.passport.tech_docs_intro') }}
            </p>
            <ul class="space-y-2 text-sm">
                <li
                    v-for="item in techDocsOutline"
                    :key="item.key"
                    class="flex items-start gap-2"
                >
                    <span
                        class="mt-0.5 inline-block size-2 shrink-0 rounded-full"
                        :class="
                            item.done
                                ? 'bg-emerald-500'
                                : 'bg-muted-foreground/40'
                        "
                    />
                    <span
                        :class="
                            item.done
                                ? 'text-foreground'
                                : 'text-muted-foreground'
                        "
                    >
                        {{ item.label }}
                    </span>
                </li>
            </ul>
            <div>
                <Button as-child variant="outline" size="sm">
                    <Link :href="techDocsHref">
                        {{ t('products.passport.tech_docs_open') }}
                    </Link>
                </Button>
            </div>
        </section>

        <section class="space-y-3 rounded-lg border p-5">
            <h2 class="text-lg font-semibold">
                {{ t('products.passport.people_title') }}
            </h2>
            <dl class="grid gap-4 sm:grid-cols-2">
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.product_owner') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        <template v-if="props.product.product_owner">
                            {{ props.product.product_owner.name }}
                            <span class="text-muted-foreground">
                                ({{ props.product.product_owner.email }})
                            </span>
                        </template>
                        <template v-else>
                            {{ t('products.passport.empty') }}
                        </template>
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-muted-foreground">
                        {{ t('products.fields.security_contact') }}
                    </dt>
                    <dd class="mt-1 text-sm font-medium">
                        <template v-if="props.product.security_contact">
                            {{ props.product.security_contact.name }}
                            <span class="text-muted-foreground">
                                ({{ props.product.security_contact.email }})
                            </span>
                        </template>
                        <template v-else>
                            {{ t('products.passport.empty') }}
                        </template>
                    </dd>
                </div>
            </dl>
        </section>

        <section class="space-y-3">
            <div class="flex items-center justify-between gap-2">
                <h2 class="text-lg font-semibold">
                    {{ t('products.passport.sections_title') }}
                </h2>
                <Button as-child variant="link" class="h-auto p-0">
                    <Link :href="editProduct(props.product.id)">
                        {{ t('common.edit') }}
                    </Link>
                </Button>
            </div>
            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                <div
                    v-for="section in props.report.sections"
                    :key="section.key"
                    class="rounded-lg border p-4"
                >
                    <div class="flex items-start justify-between gap-2">
                        <h3 class="text-sm font-semibold">
                            {{ sectionTitle(section.key) }}
                        </h3>
                        <Badge :variant="statusVariant(section.status)">
                            {{
                                t(`products.readiness.status.${section.status}`)
                            }}
                        </Badge>
                    </div>
                    <p class="mt-2 text-sm text-muted-foreground">
                        {{ summaryLabel(section) }}
                    </p>
                </div>
            </div>
        </section>
    </div>
</template>
