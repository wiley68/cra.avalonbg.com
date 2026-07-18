import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProductRisk } from '@/routes/products/risks';

export type ProductRiskListItem = {
    id: number;
    title: string;
    category: string;
    status: string;
    treatment: string;
    initial_risk: string;
    residual_risk: string | null;
    owner_name: string | null;
    deadline: string | null;
    reviewed_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductRiskColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.risks.columns.id'),
        title: t('products.risks.columns.title'),
        category: t('products.risks.columns.category'),
        status: t('products.risks.columns.status'),
        treatment: t('products.risks.columns.treatment'),
        initial_risk: t('products.risks.columns.initial_risk'),
        residual_risk: t('products.risks.columns.residual_risk'),
        deadline: t('products.risks.columns.deadline'),
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

const enumLabel = (t: TranslateFn, group: string, value: string): string => {
    const key = `products.risks.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductRiskColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (riskId: number) => void;
}): ColumnDef<ProductRiskListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.risks.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(t('products.risks.columns.title'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'category',
            header: ({ column }) =>
                sortableHeader(t('products.risks.columns.category'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'categories',
                        String(row.getValue('category')),
                    ),
                ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(t('products.risks.columns.status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'statuses', String(row.getValue('status'))),
                ),
        },
        {
            accessorKey: 'initial_risk',
            header: ({ column }) =>
                sortableHeader(
                    t('products.risks.columns.initial_risk'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'font-medium' },
                    enumLabel(
                        t,
                        'levels',
                        String(row.getValue('initial_risk')),
                    ),
                ),
        },
        {
            accessorKey: 'residual_risk',
            enableSorting: false,
            header: () => t('products.risks.columns.residual_risk'),
            cell: ({ row }) => {
                const value = row.getValue('residual_risk') as string | null;

                return h(
                    'div',
                    {},
                    value ? enumLabel(t, 'levels', value) : '—',
                );
            },
        },
        {
            accessorKey: 'deadline',
            header: ({ column }) =>
                sortableHeader(t('products.risks.columns.deadline'), column),
            cell: ({ row }) => {
                const value = row.getValue('deadline') as string | null;

                return h(
                    'div',
                    { class: 'text-muted-foreground' },
                    value ?? '—',
                );
            },
        },
        {
            id: 'actions',
            enableHiding: false,
            enableSorting: false,
            header: () => t('common.actions'),
            cell: ({ row }) => {
                const actions: {
                    label: string;
                    icon: typeof Pencil;
                    variant?: 'default' | 'destructive';
                    onSelect: () => void;
                }[] = [
                    {
                        label: t('common.edit'),
                        icon: Pencil,
                        onSelect: () => {
                            router.visit(
                                editProductRisk({
                                    product: productId,
                                    risk: row.original.id,
                                }).url,
                            );
                        },
                    },
                ];

                if (canManage) {
                    actions.push({
                        label: t('common.delete'),
                        icon: Trash2,
                        variant: 'destructive',
                        onSelect: () => onDelete(row.original.id),
                    });
                }

                return h(TableRowActionsMenu, {
                    label: t('common.manage'),
                    actions,
                });
            },
        },
    ];
};
