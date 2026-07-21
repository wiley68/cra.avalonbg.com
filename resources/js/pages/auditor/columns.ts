import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Eye, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import {
    edit as packagesEdit,
    show as packagesShow,
} from '@/routes/auditor/packages';

export type AuditorPackageListItem = {
    id: number;
    title: string;
    status: string;
    product_id: number;
    product_name: string;
    shared_at: string | null;
    closed_at: string | null;
    evidence_count: number;
    findings_count: number;
    updated_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createAuditorColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('auditor.columns.id'),
        title: t('auditor.columns.title'),
        product_name: t('auditor.columns.product'),
        status: t('auditor.columns.status'),
        evidence_count: t('auditor.columns.evidence'),
        updated_at: t('auditor.columns.updated_at'),
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
    const key = `auditor.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createAuditorColumns = ({
    t,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    canManage: boolean;
    onDelete: (packageId: number) => void;
}): ColumnDef<AuditorPackageListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) => sortableHeader(t('auditor.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'title',
        header: ({ column }) =>
            sortableHeader(t('auditor.columns.title'), column),
        cell: ({ row }) =>
            h(
                'button',
                {
                    type: 'button',
                    class: 'font-medium text-left hover:underline',
                    onClick: () => {
                        router.visit(packagesShow(row.original.id).url);
                    },
                },
                row.getValue('title') as string,
            ),
    },
    {
        accessorKey: 'product_name',
        header: ({ column }) =>
            sortableHeader(t('auditor.columns.product'), column),
        cell: ({ row }) => h('div', {}, String(row.getValue('product_name'))),
    },
    {
        accessorKey: 'status',
        header: ({ column }) =>
            sortableHeader(t('auditor.columns.status'), column),
        cell: ({ row }) =>
            h('div', {}, statusLabel(t, String(row.getValue('status')))),
    },
    {
        accessorKey: 'evidence_count',
        header: ({ column }) =>
            sortableHeader(t('auditor.columns.evidence'), column),
        cell: ({ row }) => h('div', {}, String(row.getValue('evidence_count'))),
    },
    {
        accessorKey: 'updated_at',
        header: ({ column }) =>
            sortableHeader(t('auditor.columns.updated_at'), column),
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
                    label: t('auditor.open_review'),
                    icon: Eye,
                    onSelect: () => {
                        router.visit(packagesShow(row.original.id).url);
                    },
                },
            ];

            if (canManage) {
                actions.push({
                    label: t('common.edit'),
                    icon: Pencil,
                    onSelect: () => {
                        router.visit(packagesEdit(row.original.id).url);
                    },
                });
            }

            if (canManage && row.original.status === 'draft') {
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
