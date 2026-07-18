<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import { ArrowLeft, Plus } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import { useProductModuleBack } from '@/composables/useProductModuleBack';
import { useTranslations } from '@/composables/useTranslations';
import { edit as editProduct } from '@/routes/products';
import { create, destroy, edit } from '@/routes/products/support-periods';

type OrganizationSummary = {
    id: number;
    name: string;
    slug: string;
};

type ProductSummary = {
    id: number;
    name: string;
    slug: string;
};

type PeriodRow = {
    id: number;
    type: string;
    start_basis: string;
    duration_months: number;
    basis: string | null;
    is_extended: boolean;
    schedule_resolved: boolean;
    effective_starts_at: string | null;
    effective_ends_at: string | null;
    is_active: boolean | null;
    days_until_end: number | null;
    versions: Array<{ id: number; version_number: string }>;
};

const props = defineProps<{
    organization: OrganizationSummary;
    product: ProductSummary;
    periods: PeriodRow[];
    canManage: boolean;
}>();

const { t } = useTranslations();
const { backHref } = useProductModuleBack(props.product.id);

const typeLabel = (type: string): string => {
    const key = `products.support_periods.types.${type}`;
    const translated = t(key);

    return translated === key ? type : translated;
};

const startBasisLabel = (basis: string): string => {
    const key = `products.support_periods.start_bases.${basis}`;
    const translated = t(key);

    return translated === key ? basis : translated;
};

const remove = (periodId: number): void => {
    if (!confirm(t('products.support_periods.confirm_delete'))) {
        return;
    }

    router.delete(
        destroy({
            product: props.product.id,
            support_period: periodId,
        }).url,
    );
};
</script>

<template>
    <Head :title="t('products.support_periods.index_title')" />

    <div class="space-y-6">
        <div class="flex items-center justify-between gap-4">
            <div>
                <p class="text-sm text-muted-foreground">
                    <Link
                        :href="editProduct(props.product.id)"
                        class="hover:underline"
                        >{{ props.product.name }}</Link
                    >
                </p>
                <h1 class="text-xl font-semibold">
                    {{ t('products.support_periods.title') }}
                </h1>
                <p class="text-sm text-muted-foreground">
                    {{ t('products.support_periods.subtitle') }}
                </p>
            </div>

            <div class="flex gap-2">
                <Button as-child variant="outline">
                    <Link :href="backHref">
                        <ArrowLeft class="h-4 w-4" />
                        {{ t('common.back') }}
                    </Link>
                </Button>
                <Button v-if="canManage" as-child>
                    <Link :href="create(props.product.id)">
                        <Plus class="h-4 w-4" />
                        {{ t('products.support_periods.create') }}
                    </Link>
                </Button>
            </div>
        </div>

        <div class="overflow-hidden rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left">
                    <tr>
                        <th class="px-4 py-3">
                            {{ t('products.support_periods.fields.type') }}
                        </th>
                        <th class="px-4 py-3">
                            {{
                                t('products.support_periods.fields.start_basis')
                            }}
                        </th>
                        <th class="px-4 py-3">
                            {{
                                t(
                                    'products.support_periods.fields.duration_months',
                                )
                            }}
                        </th>
                        <th class="px-4 py-3">
                            {{ t('products.support_periods.fields.status') }}
                        </th>
                        <th class="px-4 py-3">
                            {{ t('products.support_periods.fields.versions') }}
                        </th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="period in periods"
                        :key="period.id"
                        class="border-t"
                    >
                        <td class="px-4 py-3">
                            {{ typeLabel(period.type) }}
                            <span
                                v-if="period.is_extended"
                                class="ml-2 text-xs text-muted-foreground"
                                >{{
                                    t('products.support_periods.extended')
                                }}</span
                            >
                        </td>
                        <td class="px-4 py-3">
                            {{ startBasisLabel(period.start_basis) }}
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
                        </td>
                        <td class="px-4 py-3">
                            {{
                                t(
                                    'products.support_periods.duration_months_label',
                                    {
                                        count: String(period.duration_months),
                                    },
                                )
                            }}
                        </td>
                        <td class="px-4 py-3">
                            <template v-if="period.schedule_resolved">
                                <span v-if="period.is_active">{{
                                    t('products.support_periods.active')
                                }}</span>
                                <span v-else>{{
                                    t('products.support_periods.inactive')
                                }}</span>
                                <span
                                    v-if="
                                        period.is_active &&
                                        period.days_until_end !== null &&
                                        period.days_until_end <= 90
                                    "
                                    class="ml-2 text-xs text-amber-600"
                                >
                                    {{
                                        t(
                                            'products.support_periods.ending_soon',
                                            {
                                                days: String(
                                                    period.days_until_end,
                                                ),
                                            },
                                        )
                                    }}
                                </span>
                            </template>
                            <span v-else class="text-muted-foreground">{{
                                t('products.support_periods.policy_only')
                            }}</span>
                        </td>
                        <td class="px-4 py-3">
                            {{
                                period.versions
                                    .map((version) => version.version_number)
                                    .join(', ') || '—'
                            }}
                        </td>
                        <td
                            v-if="canManage"
                            class="space-x-2 px-4 py-3 text-right"
                        >
                            <Button as-child variant="ghost" size="sm">
                                <Link
                                    :href="
                                        edit({
                                            product: props.product.id,
                                            support_period: period.id,
                                        })
                                    "
                                    >{{ t('common.edit') }}</Link
                                >
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                @click="remove(period.id)"
                            >
                                {{ t('common.delete') }}
                            </Button>
                        </td>
                    </tr>
                    <tr v-if="periods.length === 0">
                        <td
                            colspan="6"
                            class="px-4 py-8 text-center text-muted-foreground"
                        >
                            {{ t('products.support_periods.empty') }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</template>
