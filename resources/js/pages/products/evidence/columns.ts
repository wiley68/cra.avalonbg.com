import { router } from '@inertiajs/vue3';
import {
    ArrowUpDown,
    ClipboardList,
    Download,
    Pencil,
    Trash2,
} from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Button } from '@/components/ui/button';
import { create as packagesCreate } from '@/routes/auditor/packages';
import {
    download as downloadEvidence,
    edit as editEvidence,
} from '@/routes/products/evidence';

export type EvidenceListItem = {
    id: number;
    title: string;
    type: string;
    freshness_status: string;
    version_number: string | null;
    owner_name: string | null;
    collected_at: string | null;
    checksum_short: string | null;
    has_file: boolean;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

export function createEvidenceColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        select: t('products.evidence.columns.select'),
        id: t('products.evidence.columns.id'),
        title: t('products.evidence.columns.title'),
        type: t('products.evidence.columns.type'),
        freshness_status: t('products.evidence.columns.freshness'),
        version_number: t('products.evidence.columns.version'),
        owner_name: t('products.evidence.columns.owner'),
        collected_at: t('products.evidence.columns.collected_at'),
        checksum_short: t('products.evidence.columns.checksum'),
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
    const key = `products.evidence.${group}.${value}`;
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

export const createEvidenceColumns = ({
    t,
    productId,
    canManage,
    canCreateReviewPackage,
    selectedIds,
    onToggleSelect,
    onDelete,
}: {
    t: TranslateFn;
    productId: number;
    canManage: boolean;
    canCreateReviewPackage: boolean;
    selectedIds: number[];
    onToggleSelect: (evidenceId: number, checked: boolean) => void;
    onDelete: (evidenceId: number) => void;
}): ColumnDef<EvidenceListItem>[] => {
    const columns: ColumnDef<EvidenceListItem>[] = [];

    if (canCreateReviewPackage) {
        columns.push({
            id: 'select',
            enableHiding: false,
            enableSorting: false,
            header: () => t('products.evidence.columns.select'),
            cell: ({ row }) =>
                h('input', {
                    type: 'checkbox',
                    class: 'h-4 w-4 accent-primary',
                    checked: selectedIds.includes(row.original.id),
                    onChange: (event: Event) => {
                        onToggleSelect(
                            row.original.id,
                            (event.target as HTMLInputElement).checked,
                        );
                    },
                    'aria-label': t('products.evidence.select_row'),
                }),
        });
    }

    columns.push(
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('products.evidence.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'title',
            header: ({ column }) =>
                sortableHeader(t('products.evidence.columns.title'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, row.getValue('title')),
        },
        {
            accessorKey: 'type',
            header: ({ column }) =>
                sortableHeader(t('products.evidence.columns.type'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {},
                    enumLabel(t, 'types', String(row.getValue('type'))),
                ),
        },
        {
            accessorKey: 'freshness_status',
            header: ({ column }) =>
                sortableHeader(
                    t('products.evidence.columns.freshness'),
                    column,
                ),
            cell: ({ row }) => {
                const value = String(row.getValue('freshness_status'));
                const stale = ['expired', 'review_due', 'invalid'].includes(
                    value,
                );

                return h(
                    'div',
                    { class: stale ? 'font-medium text-destructive' : '' },
                    enumLabel(t, 'freshness_statuses', value),
                );
            },
        },
        {
            id: 'version_number',
            accessorKey: 'version_number',
            enableSorting: false,
            header: () => t('products.evidence.columns.version'),
            cell: ({ row }) => h('div', {}, row.original.version_number ?? '—'),
        },
        {
            id: 'owner_name',
            accessorKey: 'owner_name',
            enableSorting: false,
            header: () => t('products.evidence.columns.owner'),
            cell: ({ row }) => h('div', {}, row.original.owner_name ?? '—'),
        },
        {
            accessorKey: 'collected_at',
            header: ({ column }) =>
                sortableHeader(
                    t('products.evidence.columns.collected_at'),
                    column,
                ),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'text-muted-foreground' },
                    formatDateTime(
                        row.getValue('collected_at') as string | null,
                    ),
                ),
        },
        {
            id: 'checksum_short',
            accessorKey: 'checksum_short',
            enableSorting: false,
            header: () => t('products.evidence.columns.checksum'),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'font-mono text-xs text-muted-foreground' },
                    row.original.checksum_short ?? '—',
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
                                editEvidence({
                                    product: productId,
                                    evidence: row.original.id,
                                }).url,
                            );
                        },
                    },
                ];

                if (row.original.has_file) {
                    actions.push({
                        label: t('products.evidence.download'),
                        icon: Download,
                        onSelect: () => {
                            window.location.href = downloadEvidence({
                                product: productId,
                                evidence: row.original.id,
                            }).url;
                        },
                    });
                }

                if (canCreateReviewPackage) {
                    actions.push({
                        label: t('products.evidence.add_to_review_package'),
                        icon: ClipboardList,
                        onSelect: () => {
                            router.visit(
                                packagesCreate.url({
                                    query: {
                                        product_id: productId,
                                        evidence_ids: String(row.original.id),
                                    },
                                }),
                            );
                        },
                    });
                }

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
    );

    return columns;
};
