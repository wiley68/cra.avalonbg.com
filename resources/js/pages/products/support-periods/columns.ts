import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/products/support-periods';

export type ProductSupportPeriodListItem = {
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

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createSupportPeriodColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.support_periods.columns.id'),
        type: t('products.support_periods.columns.type'),
        start_basis: t('products.support_periods.columns.start_basis'),
        duration_months: t('products.support_periods.columns.duration_months'),
        status: t('products.support_periods.columns.status'),
        versions: t('products.support_periods.columns.versions'),
        actions: t('common.actions'),
    };
}

const sortableHeader = (
    label: string,
    column: {
        toggleSorting: (desc: boolean) => void;
        getIsSorted: () => false | 'asc' | 'desc';
    },
) =>
    h(
        Button,
        {
            variant: 'ghost',
            onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
            class: 'h-8 px-2 lg:px-3',
        },
        () => [label, h(ArrowUpDown, { class: 'ml-2 h-4 w-4' })],
    );

const typeLabel = (t: TranslateFn, type: string): string => {
    const key = `products.support_periods.types.${type}`;
    const translated = t(key);

    return translated === key ? type : translated;
};

const startBasisLabel = (t: TranslateFn, basis: string): string => {
    const key = `products.support_periods.start_bases.${basis}`;
    const translated = t(key);

    return translated === key ? basis : translated;
};

const statusNode = (t: TranslateFn, period: ProductSupportPeriodListItem) => {
    if (!period.schedule_resolved) {
        return h(
            'span',
            { class: 'text-muted-foreground' },
            t('products.support_periods.policy_only'),
        );
    }

    const children = [
        h(
            'span',
            {},
            period.is_active
                ? t('products.support_periods.active')
                : t('products.support_periods.inactive'),
        ),
    ];

    if (
        period.is_active &&
        period.days_until_end !== null &&
        period.days_until_end <= 90
    ) {
        children.push(
            h(
                'span',
                { class: 'ml-2 text-xs text-amber-600' },
                t('products.support_periods.ending_soon', {
                    days: String(period.days_until_end),
                }),
            ),
        );
    }

    return h('div', {}, children);
};

export const createSupportPeriodColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (periodId: number) => void;
}): ColumnDef<ProductSupportPeriodListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('products.support_periods.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'type',
        header: ({ column }) =>
            sortableHeader(t('products.support_periods.columns.type'), column),
        cell: ({ row }) => {
            const period = row.original;
            const label = typeLabel(t, period.type);

            if (!period.is_extended) {
                return h('div', { class: 'font-medium' }, label);
            }

            return h('div', { class: 'font-medium' }, [
                label,
                h(
                    'span',
                    { class: 'ml-2 text-xs font-normal text-muted-foreground' },
                    t('products.support_periods.extended'),
                ),
            ]);
        },
    },
    {
        accessorKey: 'start_basis',
        header: ({ column }) =>
            sortableHeader(
                t('products.support_periods.columns.start_basis'),
                column,
            ),
        cell: ({ row }) => {
            const period = row.original;
            const children = [
                h('div', {}, startBasisLabel(t, period.start_basis)),
            ];

            if (
                period.schedule_resolved &&
                period.effective_starts_at &&
                period.effective_ends_at
            ) {
                children.push(
                    h(
                        'div',
                        { class: 'text-xs text-muted-foreground' },
                        `${period.effective_starts_at} → ${period.effective_ends_at}`,
                    ),
                );
            }

            return h('div', {}, children);
        },
    },
    {
        accessorKey: 'duration_months',
        header: ({ column }) =>
            sortableHeader(
                t('products.support_periods.columns.duration_months'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                {},
                t('products.support_periods.duration_months_label', {
                    count: String(row.getValue('duration_months')),
                }),
            ),
    },
    {
        id: 'status',
        accessorFn: (row) =>
            row.schedule_resolved
                ? row.is_active
                    ? 'active'
                    : 'inactive'
                : 'policy',
        enableSorting: false,
        header: () => t('products.support_periods.columns.status'),
        cell: ({ row }) => statusNode(t, row.original),
    },
    {
        id: 'versions',
        accessorFn: (row) =>
            row.versions.map((version) => version.version_number).join(', '),
        enableSorting: false,
        header: () => t('products.support_periods.columns.versions'),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                row.original.versions
                    .map((version) => version.version_number)
                    .join(', ') || '—',
            ),
    },
    {
        id: 'actions',
        enableHiding: false,
        enableSorting: false,
        header: () => t('common.actions'),
        cell: ({ row }) => {
            if (!canManage) {
                return h('div', { class: 'text-muted-foreground' }, '—');
            }

            return h(TableRowActionsMenu, {
                label: t('common.manage'),
                actions: [
                    {
                        label: t('common.edit'),
                        icon: Pencil,
                        onSelect: () => {
                            router.visit(
                                edit({
                                    product: productId,
                                    support_period: row.original.id,
                                }).url,
                            );
                        },
                    },
                    {
                        label: t('common.delete'),
                        icon: Trash2,
                        variant: 'destructive',
                        onSelect: () => onDelete(row.original.id),
                    },
                ],
            });
        },
    },
];
