import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/admin/requirements';

export type RequirementListItem = {
    id: number;
    code: string;
    article_ref: string | null;
    sort_order: number;
    is_active: boolean;
    regulation_code: string | null;
    plain_language: string | null;
    version: number | null;
    created_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createRequirementColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('admin.requirements.columns.id'),
        code: t('admin.requirements.columns.code'),
        article_ref: t('admin.requirements.columns.article_ref'),
        regulation_code: t('admin.requirements.columns.regulation'),
        plain_language: t('admin.requirements.columns.plain_language'),
        version: t('admin.requirements.columns.version'),
        is_active: t('admin.requirements.columns.status'),
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

export const createRequirementColumns = (
    t: TranslateFn,
): ColumnDef<RequirementListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('admin.requirements.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'code',
        header: ({ column }) =>
            sortableHeader(t('admin.requirements.columns.code'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('code')),
    },
    {
        accessorKey: 'article_ref',
        header: ({ column }) =>
            sortableHeader(t('admin.requirements.columns.article_ref'), column),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                (row.getValue('article_ref') as string | null) ?? '—',
            ),
    },
    {
        accessorKey: 'regulation_code',
        header: () => t('admin.requirements.columns.regulation'),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                row.original.regulation_code ?? '—',
            ),
    },
    {
        accessorKey: 'plain_language',
        header: () => t('admin.requirements.columns.plain_language'),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'max-w-md truncate text-sm text-muted-foreground' },
                row.original.plain_language ?? '—',
            ),
    },
    {
        accessorKey: 'version',
        header: () => t('admin.requirements.columns.version'),
        cell: ({ row }) =>
            h(
                'div',
                {},
                row.original.version != null
                    ? String(row.original.version)
                    : '—',
            ),
    },
    {
        accessorKey: 'is_active',
        header: ({ column }) =>
            sortableHeader(t('admin.requirements.columns.status'), column),
        cell: ({ row }) =>
            h(
                'div',
                {},
                row.original.is_active
                    ? t('admin.requirements.active')
                    : t('admin.requirements.inactive'),
            ),
    },
    {
        id: 'actions',
        enableHiding: false,
        cell: ({ row }) =>
            h(TableRowActionsMenu, {
                actions: [
                    {
                        label: t('common.edit'),
                        icon: Pencil,
                        onSelect: () => router.visit(edit(row.original.id).url),
                    },
                ],
            }),
    },
];
