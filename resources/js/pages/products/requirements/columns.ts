import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editRequirement } from '@/routes/products/requirements';

export type ProductRequirementListItem = {
    id: number;
    code: string;
    article_ref: string | null;
    regulation_code: string | null;
    status: string;
    plain_language: string | null;
    version: number | null;
    owner_name: string | null;
    reviewed_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductRequirementColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.requirements.columns.id'),
        code: t('products.requirements.columns.code'),
        article_ref: t('products.requirements.columns.article_ref'),
        status: t('products.requirements.columns.status'),
        owner_name: t('products.requirements.columns.owner'),
        reviewed_at: t('products.requirements.columns.reviewed_at'),
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
    const key = `products.requirements.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductRequirementColumns = ({
    t,
    productId,
    canManage,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
}): ColumnDef<ProductRequirementListItem>[] => {
    const columns: ColumnDef<ProductRequirementListItem>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.requirements.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'code',
            header: ({ column }) =>
                sortableHeader(t('products.requirements.columns.code'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('code')),
        },
        {
            accessorKey: 'article_ref',
            header: ({ column }) =>
                sortableHeader(
                    t('products.requirements.columns.article_ref'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    (row.getValue('article_ref') as string | null) ?? '—',
                ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.requirements.columns.status'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', {}, statusLabel(t, row.original.status)),
        },
        {
            accessorKey: 'owner_name',
            header: () => t('products.requirements.columns.owner'),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    row.original.owner_name ?? '—',
                ),
        },
        {
            accessorKey: 'reviewed_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.requirements.columns.reviewed_at'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground text-sm' },
                    row.original.reviewed_at
                        ? new Date(row.original.reviewed_at).toLocaleString()
                        : '—',
                ),
        },
    ];

    if (canManage) {
        columns.push({
            id: 'actions',
            enableHiding: false,
            cell: ({ row }) =>
                h(TableRowActionsMenu, {
                    actions: [
                        {
                            label: t('common.edit'),
                            icon: Pencil,
                            onSelect: () =>
                                router.visit(
                                    editRequirement({
                                        product: productId,
                                        requirement: row.original.id,
                                    }).url,
                                ),
                        },
                    ],
                }),
        });
    }

    return columns;
};
