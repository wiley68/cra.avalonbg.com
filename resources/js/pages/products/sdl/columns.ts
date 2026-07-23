import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editSdlRun } from '@/routes/products/sdl';

export type SdlRunListItem = {
    id: number;
    title: string;
    status: string;
    current_stage: string;
    version_number: string | null;
    owner_name: string | null;
    approved_at: string | null;
    updated_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createSdlRunColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.sdl.columns.id'),
        title: t('products.sdl.columns.title'),
        status: t('products.sdl.columns.status'),
        current_stage: t('products.sdl.columns.current_stage'),
        version_number: t('products.sdl.columns.version'),
        updated_at: t('products.sdl.columns.updated_at'),
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
    const key = `products.sdl.${group}.${value}`;
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

export const createSdlRunColumns = ({
    t,
    productId,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onDelete: (runId: number) => void;
}): ColumnDef<SdlRunListItem>[] => {
    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.sdl.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(t('products.sdl.columns.title'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'status',
            header: ({ column }) =>
                sortableHeader(t('products.sdl.columns.status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'statuses', String(row.getValue('status'))),
                ),
        },
        {
            accessorKey: 'current_stage',
            header: ({ column }) =>
                sortableHeader(t('products.sdl.columns.current_stage'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'stages',
                        String(row.getValue('current_stage')),
                    ),
                ),
        },
        {
            accessorKey: 'version_number',
            enableSorting: false,
            header: () => t('products.sdl.columns.version'),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    (row.getValue('version_number') as string | null) ?? '—',
                ),
        },
        {
            accessorKey: 'updated_at',
            header: ({ column }) =>
                sortableHeader(t('products.sdl.columns.updated_at'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    formatDateTime(row.getValue('updated_at') as string | null),
                ),
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
                                editSdlRun({
                                    product: productId,
                                    sdlRun: row.original.id,
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
