import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/products/versions';

export type ProductVersionListItem = {
    id: number;
    version_number: string;
    state: string;
    support_status: string;
    release_date: string | null;
    security_support_deadline: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductVersionColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.versions.columns.id'),
        version_number: t('products.versions.columns.version_number'),
        state: t('products.versions.columns.state'),
        support_status: t('products.versions.columns.support_status'),
        release_date: t('products.versions.columns.release_date'),
        security_support_deadline: t(
            'products.versions.columns.security_support_deadline',
        ),
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
    const key = `products.versions.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductVersionColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (versionId: number) => void;
}): ColumnDef<ProductVersionListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('products.versions.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'version_number',
        header: ({ column }) =>
            sortableHeader(
                t('products.versions.columns.version_number'),
                column,
            ),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('version_number')),
    },
    {
        accessorKey: 'state',
        header: ({ column }) =>
            sortableHeader(t('products.versions.columns.state'), column),
        cell: ({ row }) =>
            h('div', {}, enumLabel(t, 'states', String(row.getValue('state')))),
    },
    {
        accessorKey: 'support_status',
        header: ({ column }) =>
            sortableHeader(
                t('products.versions.columns.support_status'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                {},
                enumLabel(t, 'support', String(row.getValue('support_status'))),
            ),
    },
    {
        accessorKey: 'release_date',
        header: ({ column }) =>
            sortableHeader(t('products.versions.columns.release_date'), column),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                row.getValue('release_date') ?? '—',
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
                                    version: row.original.id,
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
