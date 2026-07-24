import { router } from '@inertiajs/vue3';
import { ArrowUpDown, ExternalLink } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProduct } from '@/routes/products';

export type IntegrationHealthListItem = {
    id: string;
    source: 'integration' | 'vcs' | string;
    source_id: number;
    provider: string;
    product_id: number;
    product_name: string;
    target: string;
    connection_status: string;
    last_synced_at: string | null;
    health: 'ok' | 'soft_fail' | 'failed' | 'never' | string;
    last_error: string | null;
    pending_suggestions: number;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createIntegrationHealthColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        provider: t('integrations.health.columns.provider'),
        product_name: t('integrations.health.columns.product'),
        target: t('integrations.health.columns.target'),
        connection_status: t('integrations.health.columns.connection_status'),
        last_synced_at: t('integrations.health.columns.last_synced_at'),
        health: t('integrations.health.columns.health'),
        last_error: t('integrations.health.columns.last_error'),
        pending_suggestions: t(
            'integrations.health.columns.pending_suggestions',
        ),
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

const healthClass = (health: string): string => {
    switch (health) {
        case 'ok':
            return 'bg-emerald-600 text-white';
        case 'soft_fail':
            return 'bg-amber-500 text-white';
        case 'failed':
            return 'bg-destructive text-white';
        default:
            return 'bg-muted text-muted-foreground';
    }
};

export const createIntegrationHealthColumns = (
    t: TranslateFn,
): ColumnDef<IntegrationHealthListItem>[] => [
    {
        accessorKey: 'provider',
        header: ({ column }) =>
            sortableHeader(t('integrations.health.columns.provider'), column),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'font-medium capitalize' },
                String(row.getValue('provider')).replaceAll('_', ' '),
            ),
    },
    {
        accessorKey: 'product_name',
        header: ({ column }) =>
            sortableHeader(t('integrations.health.columns.product'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('product_name')),
    },
    {
        accessorKey: 'target',
        header: ({ column }) =>
            sortableHeader(t('integrations.health.columns.target'), column),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'max-w-[220px] truncate text-muted-foreground' },
                row.getValue('target'),
            ),
    },
    {
        accessorKey: 'connection_status',
        header: ({ column }) =>
            sortableHeader(
                t('integrations.health.columns.connection_status'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'capitalize text-muted-foreground' },
                String(row.getValue('connection_status')),
            ),
    },
    {
        accessorKey: 'last_synced_at',
        header: ({ column }) =>
            sortableHeader(
                t('integrations.health.columns.last_synced_at'),
                column,
            ),
        cell: ({ row }) => {
            const value = row.getValue('last_synced_at') as string | null;

            return h(
                'div',
                { class: 'text-muted-foreground text-sm' },
                value ? new Date(value).toLocaleString() : '—',
            );
        },
    },
    {
        accessorKey: 'health',
        header: ({ column }) =>
            sortableHeader(t('integrations.health.columns.health'), column),
        cell: ({ row }) => {
            const health = String(row.getValue('health'));
            const labelKey = `integrations.health.status.${health}`;
            const label = t(labelKey);

            return h(
                'span',
                {
                    class: `inline-block min-w-[70px] rounded px-2 py-1 text-center text-xs font-medium ${healthClass(health)}`,
                },
                label === labelKey ? health : label,
            );
        },
    },
    {
        accessorKey: 'last_error',
        header: ({ column }) =>
            sortableHeader(t('integrations.health.columns.last_error'), column),
        cell: ({ row }) => {
            const error = row.getValue('last_error') as string | null;

            return h(
                'div',
                {
                    class: 'max-w-[260px] truncate text-sm text-muted-foreground',
                    title: error ?? undefined,
                },
                error || '—',
            );
        },
    },
    {
        accessorKey: 'pending_suggestions',
        header: ({ column }) =>
            sortableHeader(
                t('integrations.health.columns.pending_suggestions'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-center tabular-nums' },
                String(row.getValue('pending_suggestions')),
            ),
    },
    {
        id: 'actions',
        enableHiding: false,
        header: () => t('common.actions'),
        cell: ({ row }) => {
            const item = row.original;

            return h(TableRowActionsMenu, {
                actions: [
                    {
                        label: t('integrations.health.open_product'),
                        icon: ExternalLink,
                        onSelect: () => {
                            router.visit(editProduct(item.product_id).url);
                        },
                    },
                ],
            });
        },
    },
];
