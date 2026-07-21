import { router } from '@inertiajs/vue3';
import { ArrowUpDown, Pencil } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { edit as editDeployment } from '@/routes/products/deployments';

export type UnsupportedDeploymentListItem = {
    id: number;
    customer_id: number;
    customer_name: string;
    product_version_id: number | null;
    version_number: string | null;
    environment: string;
    installation_date: string | null;
    internet_exposure: boolean;
    support_status: string | null;
    security_support_deadline: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createUnsupportedDeploymentColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.deployments.columns.id'),
        customer_name: t('products.deployments.columns.customer'),
        version_number: t('products.deployments.columns.version'),
        environment: t('products.deployments.columns.environment'),
        support_status: t('products.deployments.columns.support_status'),
        security_support_deadline: t(
            'products.deployments.columns.security_support_deadline',
        ),
        internet_exposure: t('products.deployments.columns.internet_exposure'),
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

const environmentLabel = (t: TranslateFn, value: string): string => {
    const key = `products.deployments.environments.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

const supportStatusLabel = (t: TranslateFn, value: string | null): string => {
    if (!value) {
        return '—';
    }

    const key = `products.versions.support.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createUnsupportedDeploymentColumns = ({
    t,
    productId,
    canManage,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
}): ColumnDef<UnsupportedDeploymentListItem>[] => [
    {
        accessorKey: 'id',
        header: ({ column }) =>
            sortableHeader(t('products.deployments.columns.id'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, String(row.getValue('id'))),
    },
    {
        accessorKey: 'customer_name',
        header: ({ column }) =>
            sortableHeader(t('products.deployments.columns.customer'), column),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('customer_name')),
    },
    {
        accessorKey: 'version_number',
        header: ({ column }) =>
            sortableHeader(t('products.deployments.columns.version'), column),
        cell: ({ row }) =>
            h('div', {}, String(row.getValue('version_number') ?? '—')),
    },
    {
        accessorKey: 'environment',
        header: ({ column }) =>
            sortableHeader(
                t('products.deployments.columns.environment'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                {},
                environmentLabel(t, String(row.getValue('environment'))),
            ),
    },
    {
        accessorKey: 'support_status',
        header: ({ column }) =>
            sortableHeader(
                t('products.deployments.columns.support_status'),
                column,
            ),
        cell: ({ row }) => {
            const status = row.getValue('support_status') as string | null;

            return h(
                Badge,
                {
                    variant:
                        status === 'unsupported' ? 'destructive' : 'secondary',
                },
                () => supportStatusLabel(t, status),
            );
        },
    },
    {
        accessorKey: 'security_support_deadline',
        header: ({ column }) =>
            sortableHeader(
                t('products.deployments.columns.security_support_deadline'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                { class: 'text-muted-foreground' },
                String(row.getValue('security_support_deadline') ?? '—'),
            ),
    },
    {
        accessorKey: 'internet_exposure',
        header: ({ column }) =>
            sortableHeader(
                t('products.deployments.columns.internet_exposure'),
                column,
            ),
        cell: ({ row }) =>
            h(
                'div',
                {},
                row.getValue('internet_exposure')
                    ? t('common.yes')
                    : t('common.no'),
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
                onSelect: () => void;
            }[] = [
                {
                    label: t('common.edit'),
                    icon: Pencil,
                    onSelect: () => {
                        router.visit(
                            editDeployment({
                                product: productId,
                                deployment: row.original.id,
                            }).url,
                        );
                    },
                },
            ];

            if (!canManage) {
                return null;
            }

            return h(TableRowActionsMenu, {
                label: t('common.manage'),
                actions,
            });
        },
    },
];
