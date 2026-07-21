import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Eye, Pencil, Play, Trash2 } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import {
    edit as editCampaign,
    show as showCampaign,
} from '@/routes/products/campaigns';

export type PatchCampaignListItem = {
    id: number;
    title: string;
    status: string;
    target_version_id: number;
    target_version_number: string | null;
    product_vulnerability_id: number | null;
    vulnerability_title: string | null;
    targets_count: number;
    started_at: string | null;
    completed_at: string | null;
    created_at: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createCampaignColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.campaigns.columns.id'),
        title: t('products.campaigns.columns.title'),
        status: t('products.campaigns.columns.status'),
        target_version_number: t('products.campaigns.columns.target_version'),
        targets_count: t('products.campaigns.columns.targets'),
        started_at: t('products.campaigns.columns.started_at'),
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
    const key = `products.campaigns.statuses.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createCampaignColumns = ({
    t,
    productId,
    canManage,
    onActivate,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    onActivate: (campaignId: number) => void;
    onDelete: (campaignId: number) => void;
}): ColumnDef<PatchCampaignListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('products.campaigns.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'title',
        header: ({ column }) =>
            sortableHeader(t('products.campaigns.columns.title'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('title')),
    },
    {
        accessorKey: 'status',
        header: ({ column }) =>
            sortableHeader(t('products.campaigns.columns.status'), column),
        cell: ({ row }) =>
            h('div', {}, statusLabel(t, String(row.getValue('status')))),
    },
    {
        accessorKey: 'target_version_number',
        header: ({ column }) =>
            sortableHeader(
                t('products.campaigns.columns.target_version'),
                column,
            ),
        cell: ({ row }) =>
            h('div', {}, String(row.getValue('target_version_number') ?? '—')),
    },
    {
        accessorKey: 'targets_count',
        header: ({ column }) =>
            sortableHeader(t('products.campaigns.columns.targets'), column),
        cell: ({ row }) =>
            h('div', {}, String(row.getValue('targets_count') ?? 0)),
    },
    {
        accessorKey: 'started_at',
        header: ({ column }) =>
            sortableHeader(t('products.campaigns.columns.started_at'), column),
        cell: ({ row }) => {
            const value = row.getValue('started_at') as string | null;

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
            const isDraft = row.original.status === 'draft';

            const actions: {
                label: string;
                icon: typeof Eye;
                variant?: 'default' | 'destructive';
                onSelect: () => void;
            }[] = [
                {
                    label: t('common.view'),
                    icon: Eye,
                    onSelect: () => {
                        router.visit(
                            showCampaign({
                                product: productId,
                                campaign: row.original.id,
                            }).url,
                        );
                    },
                },
            ];

            if (canManage && isDraft) {
                actions.push(
                    {
                        label: t('common.edit'),
                        icon: Pencil,
                        onSelect: () => {
                            router.visit(
                                editCampaign({
                                    product: productId,
                                    campaign: row.original.id,
                                }).url,
                            );
                        },
                    },
                    {
                        label: t('products.campaigns.activate'),
                        icon: Play,
                        onSelect: () => onActivate(row.original.id),
                    },
                    {
                        label: t('common.delete'),
                        icon: Trash2,
                        variant: 'destructive',
                        onSelect: () => onDelete(row.original.id),
                    },
                );
            }

            return h(TableRowActionsMenu, {
                label: t('common.manage'),
                actions,
            });
        },
    },
];
