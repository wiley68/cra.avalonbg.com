import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editPolicy } from '@/routes/policies';

export type PolicyListItem = {
    id: number;
    policy_type: string;
    title: string;
    status: string;
    version_label: string;
    approved_at: string | null;
    updated_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createPolicyColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('policies.columns.id'),
        title: t('policies.columns.title'),
        policy_type: t('policies.columns.policy_type'),
        status: t('policies.columns.status'),
        version_label: t('policies.columns.version'),
        updated_at: t('policies.columns.updated_at'),
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

const typeLabel = (t: TranslateFn, value: string): string => {
    const key = `policies.types.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const statusLabel = (t: TranslateFn, value: string): string => {
    const key = `policies.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createPolicyColumns = ({
    t,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    canManage: boolean;
    onDelete: (policyId: number) => void;
}): ColumnDef<PolicyListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('policies.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'title',
        header: ({ column }) =>
            sortableHeader(t('policies.columns.title'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('title')),
    },
    {
        accessorKey: 'policy_type',
        header: ({ column }) =>
            sortableHeader(t('policies.columns.policy_type'), column),
        cell: ({ row }) =>
            h('div', {}, typeLabel(t, String(row.getValue('policy_type')))),
    },
    {
        accessorKey: 'status',
        header: ({ column }) =>
            sortableHeader(t('policies.columns.status'), column),
        cell: ({ row }) =>
            h('div', {}, statusLabel(t, String(row.getValue('status')))),
    },
    {
        accessorKey: 'version_label',
        header: ({ column }) =>
            sortableHeader(t('policies.columns.version'), column),
        cell: ({ row }) => h('div', {}, String(row.getValue('version_label'))),
    },
    {
        accessorKey: 'updated_at',
        header: ({ column }) =>
            sortableHeader(t('policies.columns.updated_at'), column),
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
                        router.visit(editPolicy(row.original.id).url);
                    },
                },
            ];

            if (canManage && row.original.status !== 'approved') {
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
