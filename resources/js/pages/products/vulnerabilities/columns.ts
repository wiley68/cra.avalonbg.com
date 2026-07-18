import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProductVulnerability } from '@/routes/products/vulnerabilities';

export type ProductVulnerabilityListItem = {
    id: number;
    title: string;
    cve_id: string | null;
    status: string;
    business_severity: string;
    exploitation_status: string;
    owner_name: string | null;
    awareness_at: string | null;
    deadline_24h: string | null;
    deadline_72h: string | null;
    overdue_24h: boolean;
    overdue_72h: boolean;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductVulnerabilityColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.vulnerabilities.columns.id'),
        title: t('products.vulnerabilities.columns.title'),
        cve_id: t('products.vulnerabilities.columns.cve_id'),
        status: t('products.vulnerabilities.columns.status'),
        business_severity: t(
            'products.vulnerabilities.columns.business_severity',
        ),
        awareness_at: t('products.vulnerabilities.columns.awareness_at'),
        deadline_72h: t('products.vulnerabilities.columns.deadline_72h'),
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
    const key = `products.vulnerabilities.${group}.${value}`;
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

export const createProductVulnerabilityColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (vulnerabilityId: number) => void;
}): ColumnDef<ProductVulnerabilityListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(
                    t('products.vulnerabilities.columns.id'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(
                    t('products.vulnerabilities.columns.title'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'cve_id',
            header: ({ column }) =>
                sortableHeader(
                    t('products.vulnerabilities.columns.cve_id'),
                    column,
                ),
            cell: ({ row }) => {
                const value = row.getValue('cve_id') as string | null;

                return h('div', {}, value ?? '—');
            },
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.vulnerabilities.columns.status'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'statuses', String(row.getValue('status'))),
                ),
        },
        {
            accessorKey: 'business_severity',
            header: ({ column }) =>
                sortableHeader(
                    t('products.vulnerabilities.columns.business_severity'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'font-medium' },
                    enumLabel(
                        t,
                        'severities',
                        String(row.getValue('business_severity')),
                    ),
                ),
        },
        {
            accessorKey: 'awareness_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.vulnerabilities.columns.awareness_at'),
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
            id: 'deadline_72h',
            accessorKey: 'deadline_72h',
            enableSorting: false,
            header: () => t('products.vulnerabilities.columns.deadline_72h'),
            cell: ({ row }) => {
                const overdue = row.original.overdue_72h;
                const label = formatDateTime(row.original.deadline_72h);

                return h(
                    'div',
                    {
                        class: overdue
                            ? 'font-medium text-destructive'
                            : 'text-muted-foreground',
                    },
                    overdue
                        ? `${label} (${t('products.vulnerabilities.overdue')})`
                        : label,
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
                                editProductVulnerability({
                                    product: productId,
                                    vulnerability: row.original.id,
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
