import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProductControl } from '@/routes/products/controls';

export type ProductControlListItem = {
    id: number;
    control_id: number;
    code: string;
    name: string;
    status: string;
    notes: string | null;
    reviewed_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductControlColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.controls.columns.id'),
        code: t('products.controls.columns.code'),
        name: t('common.name'),
        status: t('products.controls.columns.status'),
        reviewed_at: t('products.controls.columns.reviewed_at'),
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

const statusLabel = (t: TranslateFn, value: string): string => {
    const key = `products.controls.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductControlColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (productControlId: number) => void;
}): ColumnDef<ProductControlListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.controls.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'code',
            header: ({ column }) =>
                sortableHeader(t('products.controls.columns.code'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('code')),
        },
        {
            accessorKey: 'name',
            header: ({ column }) => sortableHeader(t('common.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('name')),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(t('products.controls.columns.status'), column),
            cell: ({ row }) =>
                h('div', {}, statusLabel(t, String(row.getValue('status')))),
        },
        {
            accessorKey: 'reviewed_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.controls.columns.reviewed_at'),
                    column,
                ),
            cell: ({ row }) => {
                const value = row.getValue('reviewed_at') as string | null;

                return h(
                    'div',
                    { class: 'text-muted-foreground' },
                    value ? new Date(value).toLocaleString() : '—',
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
                                editProductControl({
                                    product: productId,
                                    product_control: row.original.id,
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
