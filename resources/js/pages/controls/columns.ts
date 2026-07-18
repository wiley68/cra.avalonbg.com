import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editControl } from '@/routes/controls';

export type ControlListItem = {
    id: number;
    code: string;
    name: string;
    automation_level: string;
    frequency: string;
    is_active: boolean;
    owner_name: string | null;
    requirements_count: number;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createControlColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('controls.columns.id'),
        code: t('controls.columns.code'),
        name: t('common.name'),
        automation_level: t('controls.columns.automation_level'),
        frequency: t('controls.columns.frequency'),
        is_active: t('controls.columns.status'),
        requirements_count: t('controls.columns.requirements'),
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
    const key = `controls.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createControlColumns = ({
    t,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    canManage: boolean;
    onDelete: (controlId: number) => void;
}): ColumnDef<ControlListItem>[] => {
    const columns: ColumnDef<ControlListItem>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('controls.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'code',
            header: ({ column }) =>
                sortableHeader(t('controls.columns.code'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('code')),
        },
        {
            accessorKey: 'name',
            header: ({ column }) => sortableHeader(t('common.name'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('name')),
        },
        {
            accessorKey: 'automation_level',
            header: ({ column }) =>
                sortableHeader(t('controls.columns.automation_level'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'automation_levels',
                        String(row.getValue('automation_level')),
                    ),
                ),
        },
        {
            accessorKey: 'frequency',
            header: ({ column }) =>
                sortableHeader(t('controls.columns.frequency'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'frequencies',
                        String(row.getValue('frequency')),
                    ),
                ),
        },
        {
            accessorKey: 'is_active',
            header: ({ column }) =>
                sortableHeader(t('controls.columns.status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    row.getValue('is_active')
                        ? t('controls.active')
                        : t('controls.inactive'),
                ),
        },
        {
            accessorKey: 'requirements_count',
            enableSorting: false,
            header: () => t('controls.columns.requirements'),
            cell: ({ row }) =>
                h('div', {}, String(row.getValue('requirements_count') ?? 0)),
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
                            router.visit(editControl(row.original.id).url);
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
