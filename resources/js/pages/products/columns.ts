import { router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    Boxes,
    Bug,
    CheckSquare,
    ClipboardCheck,
    FileCheck,
    GitBranch,
    IdCard,
    ListChecks,
    Pencil,
    Shield,
    ShieldAlert,
    Trash2,
} from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { edit as editProduct } from '@/routes/products';
import { index as productComponentsIndex } from '@/routes/products/components';
import { index as productControlsIndex } from '@/routes/products/controls';
import { index as productEvidenceIndex } from '@/routes/products/evidence';
import { show as productReadinessShow } from '@/routes/products/readiness';
import { index as requirementsIndex } from '@/routes/products/requirements';
import { index as productRisksIndex } from '@/routes/products/risks';
import { index as productTasksIndex } from '@/routes/products/tasks';
import { index as versionsIndex } from '@/routes/products/versions';
import { index as productVulnerabilitiesIndex } from '@/routes/products/vulnerabilities';
import { show as productPassportShow } from '@/routes/products/passport';

export type ProductListItem = {
    id: number;
    name: string;
    slug: string;
    product_type: string;
    classification_status: string;
    scope_status: string;
    product_line: string | null;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createProductColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('products.columns.id'),
        name: t('common.name'),
        slug: t('products.columns.slug'),
        product_line: t('products.columns.product_line'),
        product_type: t('products.columns.product_type'),
        scope_status: t('products.columns.scope_status'),
        classification_status: t('products.columns.classification_status'),
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
    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
};

export const createProductColumns = ({
    t,
    canManage,
    onDelete,
}: {
    t: TranslateFn;
    canManage: boolean;
    onDelete: (productId: number) => void;
}): ColumnDef<ProductListItem>[] => {
    const columns: ColumnDef<ProductListItem>[] = [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.columns.id'), column),
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
                sortableHeader(t('products.columns.slug'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    row.getValue('slug'),
                ),
        },
        {
            accessorKey: 'product_type',
            header: ({ column }) =>
                sortableHeader(t('products.columns.product_type'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'types', String(row.getValue('product_type'))),
                ),
        },
        {
            accessorKey: 'scope_status',
            header: ({ column }) =>
                sortableHeader(t('products.columns.scope_status'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'scope', String(row.getValue('scope_status'))),
                ),
        },
        {
            accessorKey: 'classification_status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.columns.classification_status'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(
                        t,
                        'classification',
                        String(row.getValue('classification_status')),
                    ),
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
                        label: t('products.versions_link'),
                        icon: GitBranch,
                        onSelect: () => {
                            router.visit(versionsIndex(row.original.id).url);
                        },
                    },
                    {
                        label: t('products.requirements_link'),
                        icon: ListChecks,
                        onSelect: () => {
                            router.visit(
                                requirementsIndex(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.controls_link'),
                        icon: Shield,
                        onSelect: () => {
                            router.visit(
                                productControlsIndex(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.risks_link'),
                        icon: ShieldAlert,
                        onSelect: () => {
                            router.visit(
                                productRisksIndex(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.components_link'),
                        icon: Boxes,
                        onSelect: () => {
                            router.visit(
                                productComponentsIndex(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.vulnerabilities_link'),
                        icon: Bug,
                        onSelect: () => {
                            router.visit(
                                productVulnerabilitiesIndex(row.original.id)
                                    .url,
                            );
                        },
                    },
                    {
                        label: t('products.evidence_link'),
                        icon: FileCheck,
                        onSelect: () => {
                            router.visit(
                                productEvidenceIndex(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.tasks_link'),
                        icon: CheckSquare,
                        onSelect: () => {
                            router.visit(
                                productTasksIndex(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.passport_link'),
                        icon: IdCard,
                        onSelect: () => {
                            router.visit(
                                productPassportShow(row.original.id).url,
                            );
                        },
                    },
                    {
                        label: t('products.readiness_link'),
                        icon: ClipboardCheck,
                        onSelect: () => {
                            router.visit(
                                productReadinessShow(row.original.id).url,
                            );
                        },
                    },
                ];

                if (canManage) {
                    actions.unshift({
                        label: t('common.edit'),
                        icon: Pencil,
                        onSelect: () => {
                            router.visit(editProduct(row.original.id).url);
                        },
                    });
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
