import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Users } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit } from '@/routes/admin/organizations';
import { index as organizationUsersIndex } from '@/routes/admin/organizations/users';

export type OrganizationListItem = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    billing_email: string | null;
    subscription_plan: string | null;
    users_count: number;
    created_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createOrganizationColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('admin.organizations.columns.id'),
        name: t('common.name'),
        slug: t('admin.organizations.slug'),
        is_active: t('admin.organizations.status'),
        users_count: t('admin.organizations.users_count'),
        billing_email: t('admin.organizations.billing_email'),
        created_at: t('admin.organizations.columns.created_at'),
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

export const createOrganizationColumns = (
    t: TranslateFn,
): ColumnDef<OrganizationListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('admin.organizations.columns.id'), column),
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
        accessorKey: 'slug',
        header: ({ column }) =>
            sortableHeader(t('admin.organizations.slug'), column),
        cell: ({ row }) =>
            h('div', { class: 'text-muted-foreground' }, row.getValue('slug')),
    },
    {
        accessorKey: 'is_active',
        header: ({ column }) =>
            sortableHeader(t('admin.organizations.status'), column),
        cell: ({ row }) => {
            const isActive = Boolean(row.getValue('is_active'));

            return h(
                'span',
                {
                    class: `inline-block min-w-[70px] rounded px-2 py-1 text-center text-xs font-medium ${
                        isActive
                            ? 'bg-emerald-600 text-white'
                            : 'bg-muted text-muted-foreground'
                    }`,
                },
                isActive
                    ? t('admin.organizations.active')
                    : t('admin.organizations.inactive'),
            );
        },
    },
    {
        accessorKey: 'users_count',
        header: ({ column }) =>
            sortableHeader(t('admin.organizations.users_count'), column),
        cell: ({ row }) => h('div', {}, String(row.getValue('users_count'))),
    },
    {
        accessorKey: 'billing_email',
        header: ({ column }) =>
            sortableHeader(t('admin.organizations.billing_email'), column),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                (row.getValue('billing_email') as string | null) ?? '—',
            ),
    },
    {
        accessorKey: 'created_at',
        header: ({ column }) =>
            sortableHeader(t('admin.organizations.columns.created_at'), column),
        cell: ({ row }) => {
            const value = row.getValue('created_at') as string | null;

            return h(
                'div',
                { class: 'text-muted-foreground' },
                value ? new Date(value).toLocaleDateString() : '—',
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
                        label: t('nav.users'),
                        icon: Users,
                        onSelect: () => {
                            router.visit(
                                organizationUsersIndex(row.original.id).url,
                            );
                        },
                    },
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
