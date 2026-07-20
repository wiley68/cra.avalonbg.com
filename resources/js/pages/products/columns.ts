import {
    Boxes,
    Bug,
    CalendarRange,
    CheckSquare,
    ClipboardCheck,
    FileCheck,
    GitBranch,
    IdCard,
    ListChecks,
    Shield,
    ShieldAlert,
} from '@lucide/vue';
import type { LucideIcon } from '@lucide/vue';
import { index as productComponentsIndex } from '@/routes/products/components';
import { index as productControlsIndex } from '@/routes/products/controls';
import { index as productEvidenceIndex } from '@/routes/products/evidence';
import { show as productPassportShow } from '@/routes/products/passport';
import { show as productReadinessShow } from '@/routes/products/readiness';
import { index as requirementsIndex } from '@/routes/products/requirements';
import { index as productRisksIndex } from '@/routes/products/risks';
import { index as supportPeriodsIndex } from '@/routes/products/support-periods';
import { index as productTasksIndex } from '@/routes/products/tasks';
import { index as versionsIndex } from '@/routes/products/versions';
import { index as productVulnerabilitiesIndex } from '@/routes/products/vulnerabilities';

export type ProductModuleStatus = 'empty' | 'complete' | 'incomplete';

export type ProductListItem = {
    id: number;
    name: string;
    slug: string;
    product_type: string;
    classification_status: string;
    scope_status: string;
    product_line: string | null;
    module_statuses: Record<string, ProductModuleStatus>;
};

export type ProductModuleKey =
    | 'versions'
    | 'support_periods'
    | 'requirements'
    | 'controls'
    | 'risks'
    | 'components'
    | 'vulnerabilities'
    | 'evidence'
    | 'tasks'
    | 'passport'
    | 'readiness';

export type ProductModuleDefinition = {
    key: ProductModuleKey;
    labelKey: string;
    descriptionKey: string;
    icon: LucideIcon;
    href: (productId: number) => string;
    /**
     * Auth flag on shared Inertia user (e.g. can_view_vulnerabilities).
     * When omitted, module is treated as accessible if the user can view products.
     */
    canViewFlag?:
        | 'can_view_products'
        | 'can_view_requirements'
        | 'can_view_controls'
        | 'can_view_risks'
        | 'can_view_components'
        | 'can_view_vulnerabilities'
        | 'can_view_evidence'
        | 'can_view_tasks';
};

export const productModules: ProductModuleDefinition[] = [
    {
        key: 'versions',
        labelKey: 'products.versions_link',
        descriptionKey: 'products.modules.versions.description',
        icon: GitBranch,
        href: (productId) => versionsIndex(productId).url,
        canViewFlag: 'can_view_products',
    },
    {
        key: 'support_periods',
        labelKey: 'products.support_periods_link',
        descriptionKey: 'products.modules.support_periods.description',
        icon: CalendarRange,
        href: (productId) => supportPeriodsIndex(productId).url,
        canViewFlag: 'can_view_products',
    },
    {
        key: 'requirements',
        labelKey: 'products.requirements_link',
        descriptionKey: 'products.modules.requirements.description',
        icon: ListChecks,
        href: (productId) => requirementsIndex(productId).url,
        canViewFlag: 'can_view_requirements',
    },
    {
        key: 'controls',
        labelKey: 'products.controls_link',
        descriptionKey: 'products.modules.controls.description',
        icon: Shield,
        href: (productId) => productControlsIndex(productId).url,
        canViewFlag: 'can_view_controls',
    },
    {
        key: 'risks',
        labelKey: 'products.risks_link',
        descriptionKey: 'products.modules.risks.description',
        icon: ShieldAlert,
        href: (productId) => productRisksIndex(productId).url,
        canViewFlag: 'can_view_risks',
    },
    {
        key: 'components',
        labelKey: 'products.components_link',
        descriptionKey: 'products.modules.components.description',
        icon: Boxes,
        href: (productId) => productComponentsIndex(productId).url,
        canViewFlag: 'can_view_components',
    },
    {
        key: 'vulnerabilities',
        labelKey: 'products.vulnerabilities_link',
        descriptionKey: 'products.modules.vulnerabilities.description',
        icon: Bug,
        href: (productId) => productVulnerabilitiesIndex(productId).url,
        canViewFlag: 'can_view_vulnerabilities',
    },
    {
        key: 'evidence',
        labelKey: 'products.evidence_link',
        descriptionKey: 'products.modules.evidence.description',
        icon: FileCheck,
        href: (productId) => productEvidenceIndex(productId).url,
        canViewFlag: 'can_view_evidence',
    },
    {
        key: 'tasks',
        labelKey: 'products.tasks_link',
        descriptionKey: 'products.modules.tasks.description',
        icon: CheckSquare,
        href: (productId) => productTasksIndex(productId).url,
        canViewFlag: 'can_view_tasks',
    },
    {
        key: 'passport',
        labelKey: 'products.passport_link',
        descriptionKey: 'products.modules.passport.description',
        icon: IdCard,
        href: (productId) => productPassportShow(productId).url,
        canViewFlag: 'can_view_products',
    },
    {
        key: 'readiness',
        labelKey: 'products.readiness_link',
        descriptionKey: 'products.modules.readiness.description',
        icon: ClipboardCheck,
        href: (productId) => productReadinessShow(productId).url,
        canViewFlag: 'can_view_products',
    },
];

export function canAccessProductModule(
    module: ProductModuleDefinition,
    user: { [key: string]: unknown } | null | undefined,
): boolean {
    if (!user) {
        return false;
    }

    const flag = module.canViewFlag ?? 'can_view_products';

    return user[flag] === true;
}
export function productEnumLabel(
    t: (key: string, replace?: Record<string, string>) => string,
    group: string,
    value: string,
): string {
    const key = `products.${group}.${value}`;
    const translated = t(key);

    return translated === key ? value : translated;
}

export function productModuleStatusClass(
    status: ProductModuleStatus | undefined,
): string {
    if (status === 'complete') {
        return 'text-emerald-600 focus:text-emerald-600 dark:text-emerald-400 dark:focus:text-emerald-400';
    }

    if (status === 'incomplete') {
        return 'text-orange-600 focus:text-orange-600 dark:text-orange-400 dark:focus:text-orange-400';
    }

    return 'text-foreground focus:text-foreground';
}
