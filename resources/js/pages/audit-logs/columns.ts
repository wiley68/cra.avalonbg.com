import { ArrowUpDown, Eye } from '@lucide/vue';
import type { ColumnDef } from '@tanstack/vue-table';
import { h } from 'vue';
import TableRowActionsMenu from '@/components/table/TableRowActionsMenu.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

export type AuditLogDetail = {
    field: string;
    value?: string | null;
    initial_value?: string | null;
    final_value?: string | null;
};

export type AuditLog = {
    id: number;
    occurred_at: string;
    event_type: string;
    event_type_label: string;
    event_source: 'workspace' | 'api';
    event_source_label: string;
    is_success: boolean;
    organization_id?: number | null;
    product_id?: number | null;
    user_id: number | null;
    user_email: string;
    user_name: string;
    details: AuditLogDetail[];
    details_count: number;
};

type TranslateFn = (key: string, replace?: Record<string, string>) => string;

const auditFieldTranslationKeys: Record<string, string> = {
    email: 'audit_logs.fields.email',
    reason: 'audit_logs.fields.reason',
    product_id: 'audit_logs.fields.product_id',
    name: 'audit_logs.fields.name',
    slug: 'audit_logs.fields.slug',
    risk_id: 'audit_logs.fields.risk_id',
    title: 'audit_logs.fields.title',
    status: 'audit_logs.fields.status',
    evidence_id: 'audit_logs.fields.evidence_id',
    type: 'audit_logs.fields.type',
    task_id: 'audit_logs.fields.task_id',
    comment: 'audit_logs.fields.comment',
};

const auditReasonTranslationKeys: Record<string, string> = {
    invalid_credentials: 'audit_logs.reasons.invalid_credentials',
    invalid_mfa_code: 'audit_logs.reasons.invalid_mfa_code',
};

export function getAuditFieldLabel(t: TranslateFn, field: string): string {
    const key = auditFieldTranslationKeys[field];

    return key ? t(key) : field;
}

export function formatAuditDetailValue(
    t: TranslateFn,
    field: string,
    value: string | null | undefined,
): string {
    if (value === null || value === undefined || value === '') {
        return '—';
    }

    if (field === 'reason') {
        const reasonKey = auditReasonTranslationKeys[value];

        if (reasonKey) {
            return t(reasonKey);
        }
    }

    return value;
}

export function createAuditLogColumnTitleMap(
    t: TranslateFn,
): Record<string, string> {
    return {
        id: t('audit_logs.columns.id'),
        occurred_at: t('audit_logs.columns.occurred_at'),
        event_type: t('audit_logs.columns.event_type'),
        event_source: t('audit_logs.columns.event_source'),
        is_success: t('audit_logs.columns.is_success'),
        user_name: t('audit_logs.columns.user_name'),
        user_email: t('audit_logs.columns.user_email'),
        details_count: t('audit_logs.columns.details_count'),
        actions: t('audit_logs.columns.actions'),
    };
}

type AuditLogColumnOptions = {
    t: TranslateFn;
    onViewDetails: (log: AuditLog) => void;
};

const eventTypeVariant = (type: string) => {
    switch (type) {
        case 'login_success':
        case 'two_factor_challenge_success':
        case 'task_approved':
        case 'product_created':
        case 'risk_created':
        case 'evidence_created':
        case 'task_created':
            return 'default';
        case 'login_failed':
        case 'two_factor_challenge_failed':
        case 'task_rejected':
        case 'product_deleted':
        case 'risk_deleted':
        case 'evidence_deleted':
        case 'task_deleted':
            return 'destructive';
        default:
            return 'outline';
    }
};

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

export const createAuditLogColumns = (
    options: AuditLogColumnOptions,
): ColumnDef<AuditLog>[] => {
    const { t } = options;

    return [
        {
            accessorKey: 'id',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.id'), column),
            cell: ({ row }) =>
                h('div', { class: 'font-medium' }, String(row.getValue('id'))),
        },
        {
            accessorKey: 'occurred_at',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.occurred_at'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    { class: 'whitespace-nowrap' },
                    row.getValue('occurred_at'),
                ),
        },
        {
            accessorKey: 'event_type',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.event_type'), column),
            cell: ({ row }) => {
                const log = row.original;

                return h(
                    Badge,
                    { variant: eventTypeVariant(log.event_type) },
                    () => log.event_type_label,
                );
            },
        },
        {
            accessorKey: 'event_source',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.event_source'), column),
            cell: ({ row }) => h('div', row.original.event_source_label),
        },
        {
            accessorKey: 'is_success',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.is_success'), column),
            cell: ({ row }) => {
                const success = Boolean(row.getValue('is_success'));

                return h(
                    Badge,
                    { variant: success ? 'default' : 'destructive' },
                    () =>
                        success
                            ? t('audit_logs.success')
                            : t('audit_logs.failure'),
                );
            },
        },
        {
            accessorKey: 'user_name',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.user_name'), column),
            cell: ({ row }) => h('div', row.getValue('user_name')),
        },
        {
            accessorKey: 'user_email',
            header: ({ column }) =>
                sortableHeader(t('audit_logs.columns.user_email'), column),
            cell: ({ row }) =>
                h(
                    'div',
                    {
                        class: 'max-w-[220px] truncate',
                        title: String(row.getValue('user_email')),
                    },
                    row.getValue('user_email'),
                ),
        },
        {
            accessorKey: 'details_count',
            enableSorting: false,
            header: () => t('audit_logs.columns.details_count'),
            cell: ({ row }) => h('div', String(row.getValue('details_count'))),
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
                            label: t('audit_logs.view'),
                            icon: Eye,
                            onSelect: () => options.onViewDetails(row.original),
                        },
                    ],
                }),
        },
    ];
};
