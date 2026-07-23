import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProductIncident } from '@/routes/products/incidents';

export type OrgIncidentListItem = {
    id: number;
    title: string;
    status: string;
    severity: string;
    product_id: number;
    product_name: string;
    owner_name: string | null;
    awareness_at: string | null;
    detected_at: string | null;
    classified_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createOrgIncidentColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.incidents.columns.id'),
        title: t('products.incidents.columns.title'),
        product_name: t('incidents.columns.product'),
        status: t('products.incidents.columns.status'),
        severity: t('products.incidents.columns.severity'),
        awareness_at: t('products.incidents.columns.awareness_at'),
        detected_at: t('products.incidents.columns.detected_at'),
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
    const key = `products.incidents.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const formatDateTime = (value: string | null): string => {
    if (!value) {
        return '—';
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return value;
    }

    return date.toLocaleString();
};

export const createOrgIncidentColumns = ({
    t,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    canManage: boolean;
    onDelete: (incidentId: number, productId: number) => void;
}): ColumnDef<OrgIncidentListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.incidents.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(t('products.incidents.columns.title'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'product_name',
            header: ({ column }) =>
                sortableHeader(t('incidents.columns.product'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    String(row.getValue('product_name') || '—'),
                ),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(t('products.incidents.columns.status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'statuses', String(row.getValue('status'))),
                ),
        },
        {
            accessorKey: 'severity',
            header: ({ column }) =>
                sortableHeader(
                    t('products.incidents.columns.severity'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'font-medium' },
                    enumLabel(
                        t,
                        'severities',
                        String(row.getValue('severity')),
                    ),
                ),
        },
        {
            accessorKey: 'awareness_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.incidents.columns.awareness_at'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    formatDateTime(
                        row.getValue('awareness_at') as string | null,
                    ),
                ),
        },
        {
            accessorKey: 'detected_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.incidents.columns.detected_at'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    formatDateTime(
                        row.getValue('detected_at') as string | null,
                    ),
                ),
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
                                editProductIncident({
                                    product: row.original.product_id,
                                    incident: row.original.id,
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
                        onSelect: () =>
                            onDelete(row.original.id, row.original.product_id),
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
