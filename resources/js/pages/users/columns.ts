import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/users';

export type UserListItem = {
    id: number;
    name: string;
    email: string;
    role_id: number;
    role_slug: string;
    must_change_password: boolean;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createUserColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('users.columns.id'),
        name: t('common.name'),
        email: t('common.email'),
        role_slug: t('common.role'),
        must_change_password: t('common.flags'),
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

const roleLabel = (t: TranslateFn, slug: string): string => {
    const key = `roles.${slug}`;
    const translated = t(key);

    return translated === key ? t('common.unknown') : translated;
};

export const createUserColumns = (
    t: TranslateFn,
): ColumnDef<UserListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) => sortableHeader(t('users.columns.id'), column),
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
        accessorKey: 'email',
        header: ({ column }) => sortableHeader(t('common.email'), column),
        cell: ({ row }) =>
            h('div', { class: 'text-muted-foreground' }, row.getValue('email')),
    },
    {
        accessorKey: 'role_slug',
        header: ({ column }) => sortableHeader(t('common.role'), column),
        cell: ({ row }) =>
            h('div', {}, roleLabel(t, String(row.getValue('role_slug')))),
    },
    {
        accessorKey: 'must_change_password',
        header: ({ column }) => sortableHeader(t('common.flags'), column),
        cell: ({ row }) => {
            const mustChange = Boolean(row.getValue('must_change_password'));

            return h(
                'div',
                { class: 'text-muted-foreground' },
                mustChange ? t('admin.users.flag_force_password') : '—',
            );
        },
    },
    {
        id: 'actions',
        enableHiding: false,
        enableSorting: false,
        header: () => t('common.actions'),
        cell: ({ row }) =>
            h(TableRowActionsMenu, {
                label: t('common.manage'),
                actions: [
                    {
                        label: t('common.edit'),
                        icon: Pencil,
                        onSelect: () => {
                            router.visit(edit(row.original.id).url);
                        },
                    },
                ],
            }),
    },
];
