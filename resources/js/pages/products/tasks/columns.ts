import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProductTask } from '@/routes/products/tasks';

export type ProductTaskListItem = {
    id: number;
    title: string;
    status: string;
    priority: string;
    approval_status: string;
    assignee_name: string | null;
    due_at: string | null;
    subject_type: string | null;
    approved_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductTaskColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.tasks.columns.id'),
        title: t('products.tasks.columns.title'),
        status: t('products.tasks.columns.status'),
        priority: t('products.tasks.columns.priority'),
        approval_status: t('products.tasks.columns.approval_status'),
        assignee_name: t('products.tasks.columns.assignee'),
        due_at: t('products.tasks.columns.due_at'),
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
    const key = `products.tasks.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductTaskColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (taskId: number) => void;
}): ColumnDef<ProductTaskListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.tasks.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(t('products.tasks.columns.title'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(t('products.tasks.columns.status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'statuses', String(row.getValue('status'))),
                ),
        },
        {
            accessorKey: 'priority',
            header: ({ column }) =>
                sortableHeader(t('products.tasks.columns.priority'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'priorities',
                        String(row.getValue('priority')),
                    ),
                ),
        },
        {
            accessorKey: 'approval_status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.tasks.columns.approval_status'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'approval_statuses',
                        String(row.getValue('approval_status')),
                    ),
                ),
        },
        {
            accessorKey: 'assignee_name',
            enableSorting: false,
            header: () => t('products.tasks.columns.assignee'),
            cell: ({ row }) => {
                const value = row.getValue('assignee_name') as string | null;

                return h('div', {}, value ?? '—');
            },
        },
        {
            accessorKey: 'due_at',
            header: ({ column }) =>
                sortableHeader(t('products.tasks.columns.due_at'), column),
            cell: ({ row }) => {
                const value = row.getValue('due_at') as string | null;

                return h(
                    'div',
                    { class: 'text-muted-foreground' },
                    value ? value.slice(0, 10) : '—',
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
                                editProductTask({
                                    product: productId,
                                    task: row.original.id,
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
