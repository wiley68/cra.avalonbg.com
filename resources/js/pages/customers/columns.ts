import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editCustomer } from '@/routes/customers';

export type CustomerListItem = {
    id: number;
    name: string;
    external_ref: string | null;
    primary_contact: string | null;
    criticality: string;
    is_active: boolean;
    deployments_count: number;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createCustomerColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('customers.columns.id'),
        name: t('common.name'),
        external_ref: t('customers.columns.external_ref'),
        primary_contact: t('customers.columns.primary_contact'),
        criticality: t('customers.columns.criticality'),
        is_active: t('customers.columns.status'),
        deployments_count: t('customers.columns.deployments'),
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

const criticalityLabel = (t: TranslateFn, value: string): string => {
    const key = `customers.criticalities.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createCustomerColumns = ({
    t,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    canManage: boolean;
    onDelete: (customerId: number) => void;
}): ColumnDef<CustomerListItem>[] => {
    const columns: ColumnDef<CustomerListItem>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('customers.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'name',
            header: ({ column }) => sortableHeader(t('common.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('name')),
        },
        {
            accessorKey: 'external_ref',
            header: ({ column }) =>
                sortableHeader(t('customers.columns.external_ref'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    String(row.getValue('external_ref') ?? '—'),
                ),
        },
        {
            accessorKey: 'primary_contact',
            header: ({ column }) =>
                sortableHeader(t('customers.columns.primary_contact'), column),
            cell: ({ row }) =>
                h('div', {}, String(row.getValue('primary_contact') ?? '—')),
        },
        {
            accessorKey: 'criticality',
            header: ({ column }) =>
                sortableHeader(t('customers.columns.criticality'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    criticalityLabel(t, String(row.getValue('criticality'))),
                ),
        },
        {
            accessorKey: 'is_active',
            header: ({ column }) =>
                sortableHeader(t('customers.columns.status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    row.getValue('is_active')
                        ? t('customers.active')
                        : t('customers.inactive'),
                ),
        },
        {
            accessorKey: 'deployments_count',
            enableSorting: false,
            header: () => t('customers.columns.deployments'),
            cell: ({ row }) =>
                h('div', {}, String(row.getValue('deployments_count') ?? 0)),
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
                            router.visit(editCustomer(row.original.id).url);
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

    return columns;
};
