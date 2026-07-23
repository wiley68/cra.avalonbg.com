import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editPackage } from '@/routes/products/technical-documentation';

export type TechnicalDocumentationListItem = {
    id: number;
    title: string;
    status: string;
    version_label: string;
    locale: string;
    product_version_id: number | null;
    product_version_number: string | null;
    published_at: string | null;
    updated_at: string | null;
    sections_count: number;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createTechnicalDocumentationColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.technical_documentation.columns.id'),
        title: t('products.technical_documentation.columns.title'),
        status: t('products.technical_documentation.columns.status'),
        version_label: t(
            'products.technical_documentation.columns.version_label',
        ),
        product_version_number: t(
            'products.technical_documentation.columns.product_version_number',
        ),
        locale: t('products.technical_documentation.columns.locale'),
        sections_count: t(
            'products.technical_documentation.columns.sections_count',
        ),
        updated_at: t('products.technical_documentation.columns.updated_at'),
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
    const key = `products.technical_documentation.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createTechnicalDocumentationColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (packageId: number) => void;
}): ColumnDef<TechnicalDocumentationListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(
                    t('products.technical_documentation.columns.id'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(
                    t('products.technical_documentation.columns.title'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.technical_documentation.columns.status'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', {}, statusLabel(t, String(row.getValue('status')))),
        },
        {
            accessorKey: 'version_label',
            header: ({ column }) =>
                sortableHeader(
                    t('products.technical_documentation.columns.version_label'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', {}, String(row.getValue('version_label'))),
        },
        {
            accessorKey: 'product_version_number',
            header: ({ column }) =>
                sortableHeader(
                    t(
                        'products.technical_documentation.columns.product_version_number',
                    ),
                    column,
                ),
            cell: ({ row }) => {
                const value = row.original.product_version_number;

                return h(
                    'div',
                    { class: 'text-muted-foreground' },
                    value ?? t('products.technical_documentation.product_wide'),
                );
            },
        },
        {
            accessorKey: 'locale',
            header: ({ column }) =>
                sortableHeader(
                    t('products.technical_documentation.columns.locale'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'uppercase text-muted-foreground' },
                    String(row.getValue('locale')),
                ),
        },
        {
            accessorKey: 'sections_count',
            header: () =>
                t('products.technical_documentation.columns.sections_count'),
            enableSorting: false,
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    String(row.original.sections_count),
                ),
        },
        {
            accessorKey: 'updated_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.technical_documentation.columns.updated_at'),
                    column,
                ),
            cell: ({ row }) => {
                const value = row.getValue('updated_at') as string | null;

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
                                editPackage({
                                    product: productId,
                                    package: row.original.id,
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
