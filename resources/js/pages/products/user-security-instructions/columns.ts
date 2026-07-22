import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editInstruction } from '@/routes/products/security-instructions';

export type UserSecurityInstructionListItem = {
    id: number;
    title: string;
    status: string;
    version_label: string;
    locale: string;
    published_at: string | null;
    updated_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createUserSecurityInstructionColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.user_security_instructions.columns.id'),
        title: t('products.user_security_instructions.columns.title'),
        status: t('products.user_security_instructions.columns.status'),
        version_label: t(
            'products.user_security_instructions.columns.version_label',
        ),
        locale: t('products.user_security_instructions.columns.locale'),
        updated_at: t('products.user_security_instructions.columns.updated_at'),
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
    const key = `products.user_security_instructions.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createUserSecurityInstructionColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (instructionId: number) => void;
}): ColumnDef<UserSecurityInstructionListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(
                    t('products.user_security_instructions.columns.id'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(
                    t('products.user_security_instructions.columns.title'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.user_security_instructions.columns.status'),
                    column,
                ),
            cell: ({ row }) =>
                h('div', {}, statusLabel(t, String(row.getValue('status')))),
        },
        {
            accessorKey: 'version_label',
            header: ({ column }) =>
                sortableHeader(
                    t(
                        'products.user_security_instructions.columns.version_label',
                    ),
                    column,
                ),
            cell: ({ row }) =>
                h('div', {}, String(row.getValue('version_label'))),
        },
        {
            accessorKey: 'locale',
            header: ({ column }) =>
                sortableHeader(
                    t('products.user_security_instructions.columns.locale'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'uppercase text-muted-foreground' },
                    String(row.getValue('locale')),
                ),
        },
        {
            accessorKey: 'updated_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.user_security_instructions.columns.updated_at'),
                    column,
                ),
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
                            router.visit(
                                editInstruction({
                                    product: productId,
                                    instruction: row.original.id,
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
