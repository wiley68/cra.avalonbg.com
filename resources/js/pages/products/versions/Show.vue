<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Eye, FileUp, Pencil, RefreshCcw } from '@lucide/vue';
import { computed, ref } from 'vue';
import AppAlertDialog from '@/components/AppAlertDialog.vue';
import MergedPrAiNarrative from '@/components/products/MergedPrAiNarrative.vue';
import { Button } from '@/components/ui/button';
import { usePageBreadcrumbs } from '@/composables/usePageBreadcrumbs';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editEvidence } from '@/routes/products/evidence';
import { edit as editProduct, index as productsIndex } from '@/routes/products';
import {
    edit as versionsEdit,
    index as versionsIndex,
    show as versionsShow,
} from '@/routes/products/versions';
import {
    refresh as refreshMergedPrs,
    saveEvidence as saveMergedPrsEvidence,
} from '@/routes/products/versions/merged-prs';

type ProductSummary = { id: number; name: string; slug: string };

type VersionDetail = {
    id: number;
    version_number: string;
    release_date: string | null;
    state: string;
    support_status: string;
    security_support_deadline: string | null;
    git_ref: string | null;
    build_identifier: string | null;
    artifact_hash: string | null;
    changelog: string | null;
    previous_version_id: number | null;
};

type MergedPr = {
    number: number;
    title: string;
    html_url: string;
    merged_at: string | null;
    user_login: string | null;
};

type MergedPrSummary = {
    available: boolean;
    reason: string | null;
    provider: string | null;
    repository_full_name: string | null;
    window: {
        from: string;
        to: string;
        mode: string;
        anchor_date: string | null;
    };
    cached_at: string | null;
    from_cache: boolean;
    count: number;
    truncated: boolean;
    prs: MergedPr[];
    error: string | null;
};

type MergedPrEvidence = {
    id: number;
    title: string;
};

const props = defineProps<{
    product: ProductSummary;
    version: VersionDetail;
    mergedPrSummary: MergedPrSummary;
    mergedPrEvidence: MergedPrEvidence | null;
    canManage: boolean;
    aiEnabled: boolean;
}>();

const { t } = useTranslations();
const showSaveEvidenceDialog = ref(false);

usePageBreadcrumbs(() => [
    { titleKey: 'nav.products', href: productsIndex() },
    { title: props.product.name, href: editProduct(props.product.id) },
    {
        titleKey: 'products.versions.index_title',
        href: versionsIndex(props.product.id),
    },
    {
        title: props.version.version_number,
        href: versionsShow({
            product: props.product.id,
            version: props.version.id,
        }),
    },
]);

const routeArgs = {
    product: props.product.id,
    version: props.version.id,
};

const enumLabel = (group: string, value: string): string => {
    const key = `products.versions.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const windowLabel = computed(() => {
    const window = props.mergedPrSummary.window;

    if (window.mode === 'release_window' && window.anchor_date) {
        return t('products.versions.merged_prs.window_release', {
            from: window.from,
            to: window.to,
            anchor: window.anchor_date,
        });
    }

    return t('products.versions.merged_prs.window_rolling', {
        from: window.from,
        to: window.to,
    });
});

const unavailableMessage = computed(() => {
    if (props.mergedPrSummary.error) {
        return props.mergedPrSummary.error;
    }

    const reason = props.mergedPrSummary.reason;

    if (!reason) {
        return null;
    }

    const key = `products.versions.merged_prs.reasons.${reason}`;
    const translated = t(key);

    return translated === key ? reason : translated;
});

const canSaveEvidence = computed(
    () => props.canManage && props.mergedPrSummary.available,
);

const evidenceHref = computed(() => {
    if (props.mergedPrEvidence === null) {
        return null;
    }

    return editEvidence({
        product: props.product.id,
        evidence: props.mergedPrEvidence.id,
    }).url;
});

const doRefresh = () => {
    if (!props.canManage) {
        return;
    }

    router.post(refreshMergedPrs(routeArgs).url, {}, { preserveScroll: true });
};

const doSaveEvidence = () => {
    if (!canSaveEvidence.value) {
        return;
    }

    showSaveEvidenceDialog.value = false;
    router.post(
        saveMergedPrsEvidence(routeArgs).url,
        {},
        { preserveScroll: true },
    );
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    try {
        return new Date(value).toLocaleString();
    } catch {
        return value;
    }
};
</script>

<template>
    <Head :title="version.version_number" />

    <div class="mx-auto max-w-3xl space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    {{ product.name }}
                </p>
                <h1 class="text-xl font-semibold">
                    {{ version.version_number }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ enumLabel('states', version.state) }}
                    ·
                    {{ enumLabel('support', version.support_status) }}
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <Button as-child variant="outline">
                    <Link :href="versionsIndex(product.id)">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child variant="outline">
                    <Link :href="versionsEdit(routeArgs)">
                        <Pencil class="h-4 w-4" />
                        {{ t('common.edit') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div class="space-y-3 rounded-lg border p-4">
            <dl class="grid gap-3 text-sm sm:grid-cols-2">
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('products.versions.fields.release_date') }}
                    </dt>
                    <dd>{{ version.release_date ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{
                            t(
                                'products.versions.fields.security_support_deadline',
                            )
                        }}
                    </dt>
                    <dd>{{ version.security_support_deadline ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('products.versions.fields.git_ref') }}
                    </dt>
                    <dd class="font-mono text-xs">
                        {{ version.git_ref ?? '—' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-muted-foreground">
                        {{ t('products.versions.fields.build_identifier') }}
                    </dt>
                    <dd>{{ version.build_identifier ?? '—' }}</dd>
                </div>
            </dl>
            <div v-if="version.changelog" class="border-t pt-3 text-sm">
                <p class="mb-1 text-muted-foreground">
                    {{ t('products.versions.fields.changelog') }}
                </p>
                <p class="whitespace-pre-wrap">{{ version.changelog }}</p>
            </div>
        </div>

        <div class="space-y-4 rounded-lg border p-4">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-lg font-medium">
                        <Eye class="h-4 w-4" />
                        {{ t('products.versions.merged_prs.title') }}
                    </h2>
                    <p class="text-sm text-muted-foreground">
                        {{ t('products.versions.merged_prs.subtitle') }}
                    </p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <Button
                        v-if="canManage"
                        type="button"
                        variant="outline"
                        @click="doRefresh"
                    >
                        <RefreshCcw class="h-4 w-4" />
                        {{ t('products.versions.merged_prs.refresh') }}
                    </Button>
                    <Button
                        v-if="canSaveEvidence"
                        type="button"
                        variant="outline"
                        @click="showSaveEvidenceDialog = true"
                    >
                        <FileUp class="h-4 w-4" />
                        {{ t('products.versions.merged_prs.save_as_evidence') }}
                    </Button>
                    <Button v-if="evidenceHref" as-child variant="outline">
                        <Link :href="evidenceHref">
                            <Pencil class="h-4 w-4" />
                            {{
                                t('products.versions.merged_prs.view_evidence')
                            }}
                        </Link>
                    </Button>
                </div>
            </div>

            <p class="text-sm text-muted-foreground">{{ windowLabel }}</p>

            <p
                v-if="mergedPrSummary.repository_full_name"
                class="text-sm text-muted-foreground"
            >
                {{ t('products.versions.merged_prs.repository') }}:
                <span class="font-mono text-foreground">{{
                    mergedPrSummary.repository_full_name
                }}</span>
            </p>

            <p
                v-if="mergedPrSummary.cached_at"
                class="text-xs text-muted-foreground"
            >
                {{ t('products.versions.merged_prs.cached_at') }}:
                {{ formatDateTime(mergedPrSummary.cached_at) }}
            </p>

            <p
                v-if="!mergedPrSummary.available"
                class="rounded-md border border-dashed px-3 py-2 text-sm text-muted-foreground"
            >
                {{ unavailableMessage }}
            </p>

            <template v-else>
                <p
                    v-if="mergedPrSummary.truncated"
                    class="text-xs text-muted-foreground"
                >
                    {{
                        t('products.versions.merged_prs.truncated', {
                            count: String(mergedPrSummary.count),
                        })
                    }}
                </p>

                <p
                    v-if="mergedPrSummary.prs.length === 0"
                    class="text-sm text-muted-foreground"
                >
                    {{ t('products.versions.merged_prs.empty') }}
                </p>

                <ul v-else class="divide-y rounded-md border">
                    <li
                        v-for="pr in mergedPrSummary.prs"
                        :key="pr.number"
                        class="flex flex-col gap-1 px-3 py-3 text-sm sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="min-w-0">
                            <a
                                :href="pr.html_url"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="font-medium text-primary hover:underline"
                            >
                                #{{ pr.number }} · {{ pr.title }}
                            </a>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    t(
                                        'products.versions.merged_prs.columns.author',
                                    )
                                }}:
                                {{ pr.user_login ?? '—' }}
                            </p>
                        </div>
                        <p
                            class="shrink-0 text-xs text-muted-foreground sm:text-right"
                        >
                            {{
                                t(
                                    'products.versions.merged_prs.columns.merged_at',
                                )
                            }}:
                            {{ formatDateTime(pr.merged_at) }}
                        </p>
                    </li>
                </ul>

                <MergedPrAiNarrative
                    v-if="canManage && aiEnabled"
                    :product-id="product.id"
                    :version-id="version.id"
                />
            </template>
        </div>

        <AppAlertDialog
            v-model:open="showSaveEvidenceDialog"
            variant="default"
            :title="
                t('products.versions.merged_prs.confirm_save_evidence_title')
            "
            :description="
                t('products.versions.merged_prs.confirm_save_evidence')
            "
            :confirm-label="t('products.versions.merged_prs.confirm_save')"
            @confirm="doSaveEvidence"
            @cancel="showSaveEvidenceDialog = false"
        />
    </div>
</template>
