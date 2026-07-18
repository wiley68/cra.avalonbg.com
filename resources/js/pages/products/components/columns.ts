import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProductComponent } from '@/routes/products/components';

export type ProductComponentListItem = {
    id: number;
    name: string;
    version: string | null;
    package_ecosystem: string;
    licence: string | null;
    is_direct: boolean;
    is_dev: boolean;
    support_status: string;
    product_version_id: number;
    version_number: string | null;
    purl: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductComponentColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.components.columns.id'),
        name: t('products.components.columns.name'),
        version: t('products.components.columns.version'),
        package_ecosystem: t('products.components.columns.package_ecosystem'),
        version_number: t('products.components.columns.product_version'),
        support_status: t('products.components.columns.support_status'),
        licence: t('products.components.columns.licence'),
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
    const key = `products.components.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductComponentColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (componentId: number) => void;
}): ColumnDef<ProductComponentListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.components.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'name',
            header: ({ column }) =>
                sortableHeader(t('products.components.columns.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('name')),
        },
        {
            accessorKey: 'version',
            header: ({ column }) =>
                sortableHeader(
                    t('products.components.columns.version'),
                    column,
                ),
            cell: ({ row }) => {
                const value = row.getValue('version') as string | null;

                return h('div', {}, value ?? '—');
            },
        },
        {
            accessorKey: 'package_ecosystem',
            header: ({ column }) =>
                sortableHeader(
                    t('products.components.columns.package_ecosystem'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'ecosystems',
                        String(row.getValue('package_ecosystem')),
                    ),
                ),
        },
        {
            id: 'version_number',
            accessorKey: 'version_number',
            enableSorting: false,
            header: () => t('products.components.columns.product_version'),
            cell: ({ row }) => {
                const value = row.original.version_number;

                return h('div', {}, value ?? '—');
            },
        },
        {
            accessorKey: 'support_status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.components.columns.support_status'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'support_statuses',
                        String(row.getValue('support_status')),
                    ),
                ),
        },
        {
            accessorKey: 'licence',
            enableSorting: false,
            header: () => t('products.components.columns.licence'),
            cell: ({ row }) => {
                const value = row.getValue('licence') as string | null;

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
                                editProductComponent({
                                    product: productId,
                                    component: row.original.id,
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
